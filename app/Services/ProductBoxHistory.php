<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ScanAction;
use App\Models\StockBox;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Support\Branches;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything that happened to one product's boxes in a period: which boxes
 * held it, and each box's records (arrived, dispatched, received, missing…).
 *
 * The scan log is the source: every unit scan carries the box it was in at the
 * time, so a box's history survives units being taken out of it later. Branch
 * admins only see boxes that passed through their branches.
 */
class ProductBoxHistory
{
    public function __construct(
        private string $productId,
        private Carbon $from,
        private Carbon $to,
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /** Scans of this product in the period, at the user's locations. */
    private function productScans(): Builder
    {
        return Branches::scope(StockScan::query())
            ->where('product_id', $this->productId)
            ->whereBetween('created_at', [$this->from, $this->to]);
    }

    /** Boxes holding this product that had any record in the period, newest activity first. */
    public function boxes(int $perPage = 10): LengthAwarePaginator
    {
        $lastActivity = $this->productScans()->whereNotNull('box_id')
            ->selectRaw('box_id, MAX(created_at) as last_at')
            ->groupBy('box_id');

        return StockBox::query()
            ->joinSub($lastActivity, 'activity', 'activity.box_id', '=', 'stock_boxes.id')
            ->select('stock_boxes.*', 'activity.last_at')
            ->with('warehouse:id,name', 'transfer:id,reference,to_warehouse_id', 'transfer.toWarehouse:id,name')
            ->orderByDesc('activity.last_at')
            ->paginate($perPage, pageName: 'boxes');
    }

    /** Units of this product that arrived on their own (not in a box), e.g. jerrycans and drums. */
    private function looseIds()
    {
        return StockScan::query()
            ->select('stock_item_id')
            ->where('product_id', $this->productId)
            ->where('action', ScanAction::RECEIVED_STOCK)
            ->whereNull('box_id');
    }

    /** Loose units with any record in the period, newest activity first. */
    public function looseUnits(int $perPage = 15): LengthAwarePaginator
    {
        $lastActivity = $this->productScans()
            ->whereIn('stock_item_id', $this->looseIds())
            ->selectRaw('stock_item_id, MAX(created_at) as last_at')
            ->groupBy('stock_item_id');

        return StockItem::query()
            ->joinSub($lastActivity, 'activity', 'activity.stock_item_id', '=', 'stock_items.id')
            ->select('stock_items.*', 'activity.last_at')
            ->with('warehouse:id,name', 'transfer:id,reference,to_warehouse_id', 'transfer.toWarehouse:id,name')
            ->orderByDesc('activity.last_at')
            ->orderByDesc('stock_items.id')
            ->paginate($perPage, pageName: 'loose');
    }

    /**
     * Records for each loose unit on the page: what happened to it in the
     * period (customer scans grouped per day) and its customers over its life.
     *
     * @param iterable<StockItem> $items
     *
     * @return Collection<int, array>
     */
    public function looseDetails(iterable $items): Collection
    {
        $items = collect($items);
        $scans = StockScan::query()
            ->with('product:id,name', 'warehouse:id,name', 'user:id,name', 'transfer:id,reference,from_warehouse_id,to_warehouse_id', 'transfer.toWarehouse:id,name', 'transfer.fromWarehouse:id,name')
            ->whereIn('stock_item_id', $items->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('stock_item_id');

        return $items->mapWithKeys(function (StockItem $item) use ($scans) {
            $all = $scans->get($item->id, collect());
            $inPeriod = $all->filter(fn (StockScan $s) => $s->created_at->between($this->from, $this->to));
            $served = $all->where('action', ScanAction::SERVED_CLIENT);

            return [$item->id => [
                'clients_all' => (int) $served->sum('quantity'),
                'clients' => (int) $inPeriod->where('action', ScanAction::SERVED_CLIENT)->sum('quantity'),
                'arrived_at' => $all->firstWhere('action', ScanAction::RECEIVED_STOCK)?->created_at,
                'events' => $this->events($inPeriod->where('action', '!=', ScanAction::SERVED_CLIENT))
                    ->merge($this->usageEvents($inPeriod->where('action', ScanAction::SERVED_CLIENT)))
                    ->sortByDesc('when')->values(),
                'hidden_events' => $all->count() - $inPeriod->count(),
            ]];
        });
    }

    /**
     * Details for each box on the page: the units of this product that were
     * ever in it, what else it carried, and its records grouped into events.
     *
     * @param iterable<StockBox> $boxes
     *
     * @return Collection<int, array>
     */
    public function details(iterable $boxes): Collection
    {
        $boxes = collect($boxes);
        $ids = $boxes->pluck('id')->all();

        $scans = StockScan::query()
            ->with('product:id,name', 'warehouse:id,name', 'user:id,name', 'transfer:id,reference,from_warehouse_id,to_warehouse_id', 'transfer.toWarehouse:id,name', 'transfer.fromWarehouse:id,name')
            ->whereIn('box_id', $ids)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('box_id');

        $unitIds = $scans->flatten()->where('product_id', $this->productId)->pluck('stock_item_id')->unique()->filter();
        $units = StockItem::query()->with('warehouse:id,name')->whereIn('id', $unitIds)->get()->keyBy('id');

        // Client scans happen after a unit is opened (and out of its box), so follow the units.
        $served = StockScan::query()
            ->with('warehouse:id,name', 'user:id,name')
            ->where('action', ScanAction::SERVED_CLIENT)
            ->whereIn('stock_item_id', $scans->flatten()->pluck('stock_item_id')->unique()->filter())
            ->get()
            ->groupBy('stock_item_id');

        return $boxes->mapWithKeys(function (StockBox $box) use ($scans, $units, $served) {
            $boxScans = $scans->get($box->id, collect());
            $boxServed = $boxScans->pluck('stock_item_id')->unique()->flatMap(fn ($id) => $served->get($id, collect()));
            $servedInPeriod = $boxServed->filter(fn (StockScan $s) => $s->created_at->between($this->from, $this->to));

            // Every unit that was ever in the box, from the arrival records.
            $contents = $boxScans->unique('stock_item_id');
            $mine = $contents->where('product_id', $this->productId);
            $others = $contents->where('product_id', '!=', $this->productId)
                ->groupBy('product_id')
                ->map(fn ($s) => $s->count() . ' × ' . $s->first()->product?->name)
                ->values();

            $inPeriod = $boxScans->filter(fn (StockScan $s) => $s->created_at->between($this->from, $this->to));

            return [$box->id => [
                'units' => $mine->map(fn (StockScan $s) => [
                    'code' => $s->code,
                    'status' => $units->get($s->stock_item_id)?->status,
                    'location' => $units->get($s->stock_item_id)?->warehouse?->name,
                    'inside' => $units->get($s->stock_item_id)?->box_id === $box->id,
                    'clients' => (int) $served->get($s->stock_item_id, collect())->sum('quantity'),
                ])->values(),
                'clients' => (int) $servedInPeriod->sum('quantity'),
                'clients_all' => (int) $boxServed->sum('quantity'),
                'mine_count' => $mine->count(),
                'total_count' => $contents->count(),
                'others' => $others,
                'events' => $this->events($inPeriod->where('action', '!=', ScanAction::SERVED_CLIENT))
                    ->merge($this->usageEvents($servedInPeriod))
                    ->sortByDesc('when')->values(),
                'hidden_events' => $boxScans->count() - $inPeriod->count(),
            ]];
        });
    }

    /**
     * One row per thing that happened to the box (e.g. "Dispatched 12 units,
     * Head Office → Kansanga, by Grace") instead of one row per unit scan.
     */
    private function events(Collection $scans): Collection
    {
        return $scans
            ->groupBy(fn (StockScan $s) => implode('|', [
                $s->action->value, $s->warehouse_id, $s->transfer_id, $s->user_id, $s->created_at->format('Y-m-d H:i'),
            ]))
            ->map(function (Collection $group) {
                /** @var StockScan $first */
                $first = $group->first();
                $mine = $group->where('product_id', $this->productId);

                return [
                    'when' => $first->created_at,
                    'action' => $first->action,
                    'location' => $first->warehouse?->name,
                    'route' => match ($first->action) {
                        ScanAction::DISPATCHED => $first->transfer?->toWarehouse?->name,
                        ScanAction::RECEIVED_TRANSFER, ScanAction::MARKED_MISSING => $first->transfer?->fromWarehouse?->name,
                        default => null,
                    },
                    'transfer' => $first->transfer?->reference,
                    'by' => $first->user?->name,
                    'units' => $group->count(),
                    'mine' => $mine->count(),
                    'codes' => $mine->pluck('code')->all(),
                    'note' => $group->pluck('note')->filter()->unique()->join('; '),
                ];
            })
            ->sortByDesc('when')
            ->values();
    }

    /** Client scans of the box's units, one row per branch per day. */
    private function usageEvents(Collection $served): Collection
    {
        return $served
            ->groupBy(fn (StockScan $s) => $s->created_at->toDateString() . '|' . $s->warehouse_id)
            ->map(function (Collection $group) {
                $mine = $group->where('product_id', $this->productId);

                return [
                    'when' => $group->max('created_at'),
                    'action' => ScanAction::SERVED_CLIENT,
                    'location' => $group->first()->warehouse?->name,
                    'route' => null,
                    'transfer' => null,
                    'by' => $group->map(fn ($s) => $s->user?->name . ($s->station ? " ({$s->station})" : ''))->filter()->unique()->join(', '),
                    'units' => $group->pluck('stock_item_id')->unique()->count(),
                    'clients' => (int) $group->sum('quantity'),
                    'mine' => (int) $mine->sum('quantity'),
                    'codes' => $mine->pluck('code')->unique()->values()->all(),
                    'note' => '',
                ];
            })
            ->values();
    }

    /** Headline counts of this product's units in the period. */
    public function summary(): array
    {
        $counts = $this->productScans()
            ->selectRaw('action, COUNT(*) as n')
            ->groupBy('action')
            ->pluck('n', 'action');

        $count = fn (ScanAction ...$actions) => (int) collect($actions)->sum(fn ($a) => $counts[$a->value] ?? 0);

        return [
            'boxes' => (int) $this->productScans()->whereNotNull('box_id')->distinct()->count('box_id'),
            'arrived' => $count(ScanAction::RECEIVED_STOCK),
            'arrived_loose' => (int) $this->productScans()->where('action', ScanAction::RECEIVED_STOCK)->whereNull('box_id')->count(),
            'loose' => (int) $this->productScans()->whereIn('stock_item_id', $this->looseIds())->distinct()->count('stock_item_id'),
            'dispatched' => $count(ScanAction::DISPATCHED),
            'delivered' => $count(ScanAction::RECEIVED_TRANSFER),
            'missing' => $count(ScanAction::MARKED_MISSING),
            'opened' => $count(ScanAction::IN_USE),
            'finished' => $count(ScanAction::DISPOSED, ScanAction::RETURNED_FOR_REFILL),
            'clients' => (int) $this->productScans()->where('action', ScanAction::SERVED_CLIENT)->sum('quantity'),
        ];
    }
}
