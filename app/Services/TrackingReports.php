<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\Transfer;
use App\Models\Warehouse;
use App\Support\Branches;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Figures for the stock-tracking reports. The screen and the Excel/PDF
 * downloads both read from here, and everything is limited to the signed-in
 * user's branches (see Branches).
 */
class TrackingReports
{
    public function __construct(
        private Carbon $from,
        private Carbon $to,
        private ?int $warehouseId = null,
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /** Locations included: the user's branches, optionally narrowed to one. */
    public function locations(): Collection
    {
        return Branches::warehouses()->when($this->warehouseId, fn ($w) => $w->where('id', $this->warehouseId))->values();
    }

    private function locationIds(): array
    {
        return $this->locations()->pluck('id')->all();
    }

    // ---------------------------------------------------------------- Losses

    /** Units marked missing on arrival in the period, at the receiving location. */
    public function lossRows(): Collection
    {
        return StockScan::query()
            ->with('product:id,name,code,cost,price', 'warehouse:id,name', 'box:id,code', 'transfer:id,reference,dispatched_by,dispatched_at', 'transfer.dispatcher:id,name', 'user:id,name')
            ->where('action', ScanAction::MARKED_MISSING)
            ->whereIn('warehouse_id', $this->locationIds())
            ->whereBetween('created_at', [$this->from, $this->to])
            ->latest('created_at')
            ->get()
            ->map(fn (StockScan $s) => [
                'date' => $s->created_at,
                'code' => $s->code,
                'product' => $s->product?->name,
                'box' => $s->box?->code,
                'location' => $s->warehouse?->name,
                'transfer' => $s->transfer?->reference,
                'transfer_id' => $s->transfer_id,
                'dispatched_by' => $s->transfer?->dispatcher?->name,
                'received_by' => $s->user?->name,
                'cost' => (float) ($s->product?->cost ?? 0),
                'price' => (float) ($s->product?->price ?? 0),
            ]);
    }

    public function losses(): array
    {
        $rows = $this->lossRows();

        // Units sent to each location in the period, for a loss rate.
        $sent = StockScan::query()
            ->join('transfers', 'transfers.id', '=', 'stock_scans.transfer_id')
            ->where('stock_scans.action', ScanAction::DISPATCHED)
            ->where('transfers.status', '!=', TransferStatus::CANCELLED->value)
            ->whereIn('transfers.to_warehouse_id', $this->locationIds())
            ->whereBetween('stock_scans.created_at', [$this->from, $this->to])
            ->selectRaw('transfers.to_warehouse_id as wid, COUNT(*) as n')
            ->groupBy('wid')
            ->pluck('n', 'wid');

        $byLocation = $this->locations()->map(function (Warehouse $w) use ($rows, $sent) {
            $lost = $rows->where('location', $w->name);

            return [
                'location' => $w->name,
                'sent' => (int) ($sent[$w->id] ?? 0),
                'units' => $lost->count(),
                'cost' => $lost->sum('cost'),
                'price' => $lost->sum('price'),
                'rate' => ($sent[$w->id] ?? 0) > 0 ? round($lost->count() / $sent[$w->id] * 100, 1) : null,
            ];
        })->filter(fn ($r) => $r['sent'] > 0 || $r['units'] > 0)->sortByDesc('units')->values();

        $byProduct = $rows->groupBy('product')->map(fn ($g, $name) => [
            'product' => $name,
            'units' => $g->count(),
            'cost' => $g->sum('cost'),
            'price' => $g->sum('price'),
        ])->sortByDesc('cost')->values();

        return [
            'rows' => $rows,
            'byLocation' => $byLocation,
            'byProduct' => $byProduct,
            'totals' => [
                'units' => $rows->count(),
                'cost' => $rows->sum('cost'),
                'price' => $rows->sum('price'),
                'sent' => (int) $sent->sum(),
                'rate' => $sent->sum() > 0 ? round($rows->count() / $sent->sum() * 100, 1) : null,
                'transfers' => $rows->pluck('transfer_id')->filter()->unique()->count(),
            ],
        ];
    }

    // ------------------------------------------------------------- Transfers

    public function transferRows(): Collection
    {
        $ids = $this->locationIds();

        return Transfer::query()
            ->with('fromWarehouse:id,name', 'toWarehouse:id,name', 'dispatcher:id,name', 'receiver:id,name', 'transferDetails:id,transfer_id,quantity,received_quantity')
            ->whereNotNull('dispatched_at')
            ->where('status', '!=', TransferStatus::CANCELLED)
            ->whereBetween('dispatched_at', [$this->from, $this->to])
            ->where(fn ($q) => $q->whereIn('from_warehouse_id', $ids)->orWhereIn('to_warehouse_id', $ids))
            ->latest('dispatched_at')
            ->get()
            ->map(function (Transfer $t) {
                $received = $t->status === TransferStatus::IN_TRANSIT ? null : (int) $t->transferDetails->sum('received_quantity');

                return [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'from' => $t->fromWarehouse?->name,
                    'to' => $t->toWarehouse?->name,
                    'dispatched_at' => $t->dispatched_at,
                    'dispatched_by' => $t->dispatcher?->name,
                    'received_at' => $t->received_at,
                    'received_by' => $t->receiver?->name,
                    // Hours on the road; still counting for transfers not yet received.
                    'hours' => round(($t->received_at ?? now())->diffInMinutes($t->dispatched_at, true) / 60, 1),
                    'sent' => (int) $t->total_qty,
                    'received' => $received,
                    'missing' => $received === null ? null : (int) $t->total_qty - $received,
                    'status' => $t->status,
                ];
            });
    }

    public function transfers(): array
    {
        $rows = $this->transferRows();
        $done = $rows->whereNotNull('received_at');

        // Who dispatched and who received, and how many of their receipts came up short.
        $people = $rows->pluck('dispatched_by')->merge($done->pluck('received_by'))->filter()->unique()->values()
            ->map(fn ($name) => [
                'name' => $name,
                'dispatched' => $rows->where('dispatched_by', $name)->count(),
                'units_sent' => $rows->where('dispatched_by', $name)->sum('sent'),
                'received' => $done->where('received_by', $name)->count(),
                'short' => $done->where('received_by', $name)->where('missing', '>', 0)->count(),
            ])->sortByDesc(fn ($p) => $p['dispatched'] + $p['received'])->values();

        return [
            'rows' => $rows,
            'staff' => $people,
            'totals' => [
                'count' => $rows->count(),
                'units' => $rows->sum('sent'),
                'in_transit' => $rows->whereNull('received_at')->count(),
                'full' => $done->where('missing', 0)->count(),
                'short' => $done->where('missing', '>', 0)->count(),
                'full_rate' => $done->count() ? round($done->where('missing', 0)->count() / $done->count() * 100) : null,
                'avg_hours' => $done->count() ? round($done->avg('hours'), 1) : null,
                'slowest' => $done->sortByDesc('hours')->first(),
                'overdue' => $rows->whereNull('received_at')->where('hours', '>', 24)->count(),
            ],
        ];
    }

    // ----------------------------------------------------------- Stock value

    /** Value of the stock held right now – sealed and in-use items (independent of the date range). */
    public function stockValue(): array
    {
        $ids = $this->locationIds();

        $items = StockItem::query()
            ->join('products', 'products.id', '=', 'stock_items.product_id')
            ->whereIn('stock_items.status', ItemStatus::countsAsStock())
            ->whereIn('stock_items.warehouse_id', $ids)
            ->selectRaw("stock_items.warehouse_id as wid, products.id as pid, products.name, COUNT(*) as units, SUM(stock_items.status = 'in_use') as in_use, SUM(products.cost) as cost, SUM(products.price) as price")
            ->groupBy('wid', 'pid', 'products.name')
            ->get();

        $inTransit = StockItem::query()
            ->join('products', 'products.id', '=', 'stock_items.product_id')
            ->join('transfers', 'transfers.id', '=', 'stock_items.transfer_id')
            ->where('stock_items.status', ItemStatus::IN_TRANSIT)
            ->where(fn ($q) => $q->whereIn('transfers.to_warehouse_id', $ids)->orWhereIn('transfers.from_warehouse_id', $ids))
            ->selectRaw('COUNT(*) as units, COALESCE(SUM(products.cost), 0) as cost, COALESCE(SUM(products.price), 0) as price')
            ->first();

        $byLocation = $this->locations()->map(fn (Warehouse $w) => [
            'location' => $w->name,
            'units' => (int) $items->where('wid', $w->id)->sum('units'),
            'in_use' => (int) $items->where('wid', $w->id)->sum('in_use'),
            'cost' => (float) $items->where('wid', $w->id)->sum('cost'),
            'price' => (float) $items->where('wid', $w->id)->sum('price'),
        ])->values();

        $byProduct = $items->groupBy('pid')->map(fn ($g) => [
            'product' => $g->first()->name,
            'units' => (int) $g->sum('units'),
            'in_use' => (int) $g->sum('in_use'),
            'cost' => (float) $g->sum('cost'),
            'price' => (float) $g->sum('price'),
        ])->sortByDesc('cost')->values();

        return [
            'byLocation' => $byLocation,
            'byProduct' => $byProduct,
            'inTransit' => ['units' => (int) $inTransit->units, 'cost' => (float) $inTransit->cost, 'price' => (float) $inTransit->price],
            'totals' => [
                'units' => $byLocation->sum('units'),
                'in_use' => $byLocation->sum('in_use'),
                'cost' => $byLocation->sum('cost'),
                'price' => $byLocation->sum('price'),
            ],
        ];
    }

    // ----------------------------------------------------------------- Usage

    /**
     * Clients worked on with each item, from the scan station. "Clients per
     * unit" uses units finished in the period and the clients of that fill,
     * so a bottle opened last month and finished this month counts in full.
     */
    public function usage(): array
    {
        $ids = $this->locationIds();
        $served = fn () => StockScan::query()
            ->where('action', ScanAction::SERVED_CLIENT)
            ->whereIn('warehouse_id', $ids)
            ->whereBetween('created_at', [$this->from, $this->to]);

        // Per unit served in the period.
        $units = $served()
            ->selectRaw('stock_item_id, product_id, warehouse_id, SUM(quantity) as clients, MIN(created_at) as first_at, MAX(created_at) as last_at')
            ->groupBy('stock_item_id', 'product_id', 'warehouse_id')
            ->get();

        $lifetime = StockScan::query()->where('action', ScanAction::SERVED_CLIENT)
            ->whereIn('stock_item_id', $units->pluck('stock_item_id'))
            ->selectRaw('stock_item_id, SUM(quantity) as clients')->groupBy('stock_item_id')
            ->pluck('clients', 'stock_item_id');

        [$finished, $finishedClients] = $this->finishedFills($ids);

        $opened = StockScan::query()->where('action', ScanAction::IN_USE)
            ->whereIn('warehouse_id', $ids)->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('warehouse_id, product_id, COUNT(*) as n')->groupBy('warehouse_id', 'product_id')->get();

        $products = \App\Models\Product::withTrashed()
            ->whereIn('id', $units->pluck('product_id')->merge($finished->pluck('product_id'))->unique())
            ->get(['id', 'name', 'code', 'cost', 'unit'])->keyBy('id');
        $items = StockItem::query()->with('warehouse:id,name')->whereIn('id', $units->pluck('stock_item_id'))->get()->keyBy('id');
        $names = $this->locations()->pluck('name', 'id');

        $perUnit = function (Collection $finishedRows) use ($finishedClients): ?float {
            return $finishedRows->isEmpty() ? null
                : round($finishedRows->sum(fn ($f) => (int) ($finishedClients[$f->id] ?? 0)) / $finishedRows->count(), 1);
        };

        $byLocation = $this->locations()->map(fn (Warehouse $w) => [
            'location' => $w->name,
            'clients' => (int) $units->where('warehouse_id', $w->id)->sum('clients'),
            'units' => $units->where('warehouse_id', $w->id)->count(),
            'opened' => (int) $opened->where('warehouse_id', $w->id)->sum('n'),
            'finished' => $finished->where('warehouse_id', $w->id)->count(),
            'per_unit' => $perUnit($finished->where('warehouse_id', $w->id)),
        ])->values();

        $byProduct = $units->pluck('product_id')->merge($finished->pluck('product_id'))->unique()
            ->map(function ($pid) use ($units, $finished, $opened, $products, $perUnit) {
                $product = $products->get($pid);
                $avg = $perUnit($finished->where('product_id', $pid));

                return [
                    'product' => $product?->name,
                    'unit' => $product?->unit,
                    'clients' => (int) $units->where('product_id', $pid)->sum('clients'),
                    'units' => $units->where('product_id', $pid)->count(),
                    'opened' => (int) $opened->where('product_id', $pid)->sum('n'),
                    'finished' => $finished->where('product_id', $pid)->count(),
                    'per_unit' => $avg,
                    'cost_per_client' => $avg ? round((float) $product?->cost / $avg) : null,
                ];
            })
            ->sortByDesc('clients')->values();

        $rows = $units->map(fn ($u) => [
            'code' => $items->get($u->stock_item_id)?->code,
            'product' => $products->get($u->product_id)?->name,
            'location' => $names[$u->warehouse_id] ?? null,
            'status' => $items->get($u->stock_item_id)?->status,
            'clients' => (int) $u->clients,
            'lifetime' => (int) ($lifetime[$u->stock_item_id] ?? $u->clients),
            'first_at' => Carbon::parse($u->first_at),
            'last_at' => Carbon::parse($u->last_at),
        ])->sortByDesc('last_at')->values();

        $daily = $served()->selectRaw('DATE(created_at) as d, SUM(quantity) as n')->groupBy('d')->pluck('n', 'd');

        $byWorker = $served()->with('user:id,name', 'warehouse:id,name')
            ->selectRaw('user_id, station, warehouse_id, SUM(quantity) as clients, COUNT(DISTINCT stock_item_id) as items, MAX(created_at) as last_at')
            ->groupBy('user_id', 'station', 'warehouse_id')
            ->orderByDesc('clients')
            ->get()
            ->map(fn ($r) => [
                'worker' => $r->user?->name ?? '—',
                'station' => $r->station,
                'location' => $r->warehouse?->name,
                'clients' => (int) $r->clients,
                'items' => (int) $r->items,
                'last_at' => Carbon::parse($r->last_at),
            ]);

        return [
            'byLocation' => $byLocation,
            'byProduct' => $byProduct,
            'byWorker' => $byWorker,
            'rows' => $rows,
            'daily' => collect(\Carbon\CarbonPeriod::create($this->from, $this->to))->mapWithKeys(fn ($d) => [$d->format('j M') => (int) ($daily[$d->toDateString()] ?? 0)]),
            'totals' => [
                'clients' => $byLocation->sum('clients'),
                'units' => $units->count(),
                'opened' => $byLocation->sum('opened'),
                'finished' => $finished->count(),
                'per_unit' => $perUnit($finished),
            ],
        ];
    }

    /**
     * Items finished (disposed / emptied for refill) in the period at the given
     * locations, and the customers each one served on that fill. A refillable item
     * is finished once per fill, so each finish counts the customers since that
     * fill was opened.
     *
     * @return array{0: Collection, 1: Collection} finished scans, and customers keyed by finish scan id
     */
    private function finishedFills(array $ids): array
    {
        $finished = StockScan::query()
            ->whereIn('action', [ScanAction::DISPOSED, ScanAction::RETURNED_FOR_REFILL])
            ->whereIn('warehouse_id', $ids)
            ->whereBetween('created_at', [$this->from, $this->to])
            ->get(['id', 'stock_item_id', 'product_id', 'warehouse_id', 'created_at']);

        // Order by time, then id for scans made in the same second.
        $order = fn ($scan) => $scan->created_at->format('YmdHis') . str_pad((string) $scan->id, 12, '0', STR_PAD_LEFT);
        $history = StockScan::query()
            ->whereIn('stock_item_id', $finished->pluck('stock_item_id')->unique())
            ->whereIn('action', [ScanAction::IN_USE, ScanAction::SERVED_CLIENT])
            ->get(['id', 'stock_item_id', 'action', 'quantity', 'created_at'])
            ->groupBy('stock_item_id');

        $clients = $finished->mapWithKeys(function ($f) use ($history, $order) {
            $scans = $history->get($f->stock_item_id, collect())->filter(fn ($s) => $order($s) < $order($f));
            $opened = $scans->where('action', ScanAction::IN_USE)->map($order)->max() ?? '';

            return [$f->id => (int) $scans->where('action', ScanAction::SERVED_CLIENT)
                ->filter(fn ($s) => $order($s) > $opened)
                ->sum('quantity')];
        });

        return [$finished, $clients];
    }

    /**
     * Branch comparison: for each product, customers per item (per fill) at each
     * location, against the average across locations. Only fills finished in the
     * period are compared, so every figure is a complete item.
     */
    public function branchComparison(): array
    {
        $locations = $this->locations();
        [$finished, $clients] = $this->finishedFills($locations->pluck('id')->all());

        $served = StockScan::query()
            ->where('action', ScanAction::SERVED_CLIENT)
            ->whereIn('warehouse_id', $locations->pluck('id'))
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as n')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        $productIds = $finished->pluck('product_id')->merge($served->pluck('product_id'))->unique();
        $products = \App\Models\Product::withTrashed()->whereIn('id', $productIds)->get(['id', 'name', 'cost'])->keyBy('id');

        // Only show locations that used something in the period.
        $active = $locations->filter(fn ($w) => $finished->contains('warehouse_id', $w->id) || $served->contains('warehouse_id', $w->id))->values();

        $rows = $productIds->map(function ($pid) use ($active, $finished, $clients, $served, $products) {
            $cells = $active->mapWithKeys(function ($w) use ($pid, $finished, $clients, $served) {
                $fills = $finished->where('product_id', $pid)->where('warehouse_id', $w->id);

                return [$w->id => [
                    'fills' => $fills->count(),
                    'per_fill' => $fills->isEmpty() ? null : round($fills->sum(fn ($f) => $clients[$f->id] ?? 0) / $fills->count(), 1),
                    'customers' => (int) $served->where('product_id', $pid)->where('warehouse_id', $w->id)->sum('n'),
                ]];
            });

            // Average across locations, each location weighted equally.
            $values = $cells->pluck('per_fill')->filter(fn ($v) => $v !== null);
            $average = $values->isEmpty() ? null : round($values->avg(), 1);

            $cells = $cells->map(fn ($c) => $c + [
                'vs_average' => ($c['per_fill'] !== null && $average) ? (int) round(($c['per_fill'] - $average) / $average * 100) : null,
            ]);

            $best = $cells->filter(fn ($c) => $c['per_fill'] !== null)->sortByDesc('per_fill')->keys()->first();
            $worst = $cells->filter(fn ($c) => $c['per_fill'] !== null)->sortBy('per_fill')->keys()->first();
            $cost = (float) ($products->get($pid)?->cost ?? 0);

            return [
                'product_id' => $pid,
                'product' => $products->get($pid)?->name,
                'cells' => $cells,
                'average' => $average,
                'best' => $values->count() > 1 ? $best : null,
                'worst' => $values->count() > 1 ? $worst : null,
                // Extra cost per customer at the lowest branch compared with the best one.
                'cost_gap' => ($values->count() > 1 && $cost > 0 && $cells[$worst]['per_fill'] > 0 && $cells[$best]['per_fill'] > 0)
                    ? (int) round($cost / $cells[$worst]['per_fill'] - $cost / $cells[$best]['per_fill'])
                    : null,
                'customers' => (int) $cells->sum('customers'),
            ];
        })->sortByDesc('customers')->values();

        return ['locations' => $active, 'rows' => $rows];
    }

    // ----------------------------------------------------------- Corrections

    /** Undone deliveries, cancelled dispatches and stock-take adjustments, with who and why. */
    public function corrections(): Collection
    {
        return \App\Models\StockCorrection::query()
            ->with('user:id,name', 'warehouse:id,name')
            ->whereIn('warehouse_id', $this->locationIds())
            ->whereBetween('created_at', [$this->from, $this->to])
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'date' => $c->created_at,
                'type' => $c->label(),
                'reference' => $c->reference,
                'location' => $c->warehouse?->name,
                'by' => $c->user?->name,
                'reason' => $c->reason,
                'units' => count($c->details['units'] ?? $c->details['missing'] ?? []),
            ]);
    }

    // ------------------------------------------------------------- Movements

    public function movementsQuery(?string $action = null)
    {
        return StockScan::query()
            ->with('product:id,name', 'warehouse:id,name', 'user:id,name', 'transfer:id,reference', 'box:id,code')
            ->whereIn('warehouse_id', $this->locationIds())
            ->whereBetween('created_at', [$this->from, $this->to])
            ->when($action, fn ($q) => $q->where('action', $action))
            ->latest('id');
    }

    public function movementSummary(): Collection
    {
        $counts = StockScan::query()
            ->whereIn('warehouse_id', $this->locationIds())
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('action, COUNT(*) as n')->groupBy('action')->pluck('n', 'action');

        return collect(ScanAction::cases())->map(fn (ScanAction $a) => ['action' => $a, 'count' => (int) ($counts[$a->value] ?? 0)]);
    }
}
