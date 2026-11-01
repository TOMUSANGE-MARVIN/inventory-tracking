<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\Transfer;
use App\Support\Branches;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Dashboard')]
class Overview extends Component
{
    public const PERIODS = [7, 14, 30];

    #[Url]
    public int $period = 14;

    public string $search = '';

    public function updatedPeriod(): void
    {
        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = 14;
        }
    }

    public function render()
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $start = today()->subDays($this->period - 1);
        $previousStart = $start->copy()->subDays($this->period);
        $days = collect(CarbonPeriod::create($start, today()))->map(fn (Carbon $d) => $d->toDateString());

        $daily = $this->dailyCounts($previousStart);
        $series = fn (ScanAction $a) => $days->map(fn ($d) => (int) ($daily[$a->value][$d] ?? 0))->all();
        $previousTotal = fn (ScanAction $a) => collect($daily[$a->value] ?? [])
            ->filter(fn ($n, $d) => $d < $start->toDateString())->sum();

        // Branch admins see only their branches; the super admin sees every location.
        $warehouses = Branches::warehouses();
        $inStockBy = Branches::scope(StockItem::query())->whereIn('status', ItemStatus::countsAsStock())
            ->selectRaw('warehouse_id, COUNT(*) as n')->groupBy('warehouse_id')->pluck('n', 'warehouse_id');

        return view('livewire.tracking.overview', [
            'periods' => self::PERIODS,
            'scopeLabel' => Branches::isSuperAdmin() ? __('Stock across Head Office and all branches') : __('Stock at :where', ['where' => $warehouses->pluck('name')->join(', ')]),
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('j M'))->all(),
            // Each metric keeps one colour across the whole dashboard (see the colour map in the view).
            'tiles' => [
                $this->tile(__('Units in stock'), Branches::scope(StockItem::query())->whereIn('status', ItemStatus::countsAsStock())->count(), $this->stockTrend($days, $daily), null, 'boxes', 'aqua'),
                $this->tile(__('Units arrived'), array_sum($series(ScanAction::RECEIVED_STOCK)), $series(ScanAction::RECEIVED_STOCK), $previousTotal(ScanAction::RECEIVED_STOCK), 'box-open', 'violet'),
                $this->tile(__('Units dispatched'), array_sum($series(ScanAction::DISPATCHED)), $series(ScanAction::DISPATCHED), $previousTotal(ScanAction::DISPATCHED), 'truck', 'blue'),
                $this->tile(__('Units put into use'), array_sum($series(ScanAction::IN_USE)), $series(ScanAction::IN_USE), $previousTotal(ScanAction::IN_USE), 'cut', 'orange'),
            ],
            'movement' => [
                ['name' => __('Dispatched'), 'data' => $series(ScanAction::DISPATCHED), 'color' => 'blue'],
                ['name' => __('Received at branch'), 'data' => $series(ScanAction::RECEIVED_TRANSFER), 'color' => 'aqua'],
            ],
            'statuses' => $this->statusBreakdown(),
            'usage' => $series(ScanAction::IN_USE),
            'today' => $this->today(),
            'locations' => $warehouses->map(fn ($w) => ['name' => $w->name, 'units' => (int) ($inStockBy[$w->id] ?? 0)])->all(),
            'topProducts' => $this->topProducts(),
            'missing' => self::missingItems()->count(),
            'inTransit' => Branches::scopeTransfers(Transfer::query())->with('fromWarehouse:id,name', 'toWarehouse:id,name')
                ->where('status', TransferStatus::IN_TRANSIT)->oldest('dispatched_at')->limit(4)->get(),
            'flagged' => Branches::scopeTransfers(Transfer::query())->with('fromWarehouse:id,name', 'toWarehouse:id,name')
                ->where('status', TransferStatus::DISCREPANCY)->latest('received_at')->limit(3)->get(),
            'warehouses' => $warehouses,
            ...$this->stockTable(),
        ]);
    }

    /** @return array<string, array<string, int>> counts[action][Y-m-d] since $from */
    private function dailyCounts(Carbon $from): array
    {
        $rows = Branches::scope(StockScan::query())
            ->where('created_at', '>=', $from)
            ->selectRaw('action, DATE(created_at) as day, COUNT(*) as n')
            ->groupBy('action', 'day')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->getRawOriginal('action')][$row->day] = (int) $row->n;
        }

        // Dispatches of empty refill containers never left available stock.
        $out['_dispatched_from_stock'] = Branches::scope(DB::table('stock_scans'), 'stock_scans.warehouse_id')
            ->join('transfer_items', fn ($j) => $j->on('transfer_items.transfer_id', '=', 'stock_scans.transfer_id')
                ->on('transfer_items.stock_item_id', '=', 'stock_scans.stock_item_id'))
            ->where('stock_scans.action', ScanAction::DISPATCHED->value)
            ->where('transfer_items.from_stock', true)
            ->where('stock_scans.created_at', '>=', $from)
            ->selectRaw('DATE(stock_scans.created_at) as day, COUNT(*) as n')
            ->groupBy('day')
            ->pluck('n', 'day')->map(fn ($n) => (int) $n)->all();

        return $out;
    }

    /** End-of-day units in stock, rebuilt backwards from today's count. */
    private function stockTrend(Collection $days, array $daily): array
    {
        $net = fn (string $d) => ($daily[ScanAction::RECEIVED_STOCK->value][$d] ?? 0)
            + ($daily[ScanAction::RECEIVED_TRANSFER->value][$d] ?? 0)
            + ($daily[ScanAction::REFILLED->value][$d] ?? 0)
            + ($daily[ScanAction::DISPATCH_CANCELLED->value][$d] ?? 0)
            - ($daily['_dispatched_from_stock'][$d] ?? 0)
            - ($daily[ScanAction::DISPOSED->value][$d] ?? 0)
            - ($daily[ScanAction::RETURNED_FOR_REFILL->value][$d] ?? 0);

        $level = Branches::scope(StockItem::query())->whereIn('status', ItemStatus::countsAsStock())->count();
        $trend = [];
        foreach ($days->reverse() as $d) {
            $trend[$d] = $level;
            $level -= $net($d);
        }

        return array_values(array_reverse($trend));
    }

    private function tile(string $label, int $value, array $series, ?int $previous, string $icon, string $color): array
    {
        $change = null;
        if ($previous !== null && $previous > 0) {
            $change = (int) round(($value - $previous) / $previous * 100);
        }

        return compact('label', 'value', 'series', 'change', 'icon', 'color') + ['hasPrevious' => $previous !== null];
    }

    /** Where every live unit is right now, in the donut's validated ring order. */
    private function statusBreakdown(): array
    {
        $n = Branches::scope(StockItem::query())->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $count = fn (ItemStatus ...$s) => (int) collect($s)->sum(fn ($x) => $n[$x->value] ?? 0);
        // In-transit units have no location yet; count those on a transfer to or from the user's branches.
        $inTransit = StockItem::query()->where('status', ItemStatus::IN_TRANSIT)
            ->whereHas('transfer', fn ($t) => Branches::scopeTransfers($t))->count();

        return [
            ['label' => __('Sealed'), 'value' => $count(ItemStatus::IN_STOCK), 'color' => 'aqua'],
            ['label' => __('In use'), 'value' => $count(ItemStatus::IN_USE), 'color' => 'orange'],
            ['label' => __('In transit'), 'value' => $inTransit, 'color' => 'blue'],
            ['label' => __('Needs refill'), 'value' => $count(ItemStatus::FINISHED_REFILL), 'color' => 'yellow'],
            ['label' => __('Missing'), 'value' => self::missingItems()->count(), 'color' => 'red'],
        ];
    }

    /** Units marked missing, limited to those that never arrived at the user's branches. */
    public static function missingItems()
    {
        return StockItem::query()->where('status', ItemStatus::MISSING)
            ->when(Branches::ids() !== null, fn ($q) => $q->whereHas('scans', fn ($s) => Branches::scope(
                $s->where('action', ScanAction::MARKED_MISSING)
            )));
    }

    private function today(): array
    {
        $counts = Branches::scope(StockScan::query())->whereDate('created_at', today())
            ->selectRaw('action, COUNT(*) as n')->groupBy('action')->pluck('n', 'action');

        return collect([
            [ScanAction::RECEIVED_STOCK, 'violet'], [ScanAction::DISPATCHED, 'blue'],
            [ScanAction::RECEIVED_TRANSFER, 'aqua'], [ScanAction::IN_USE, 'orange'],
        ])->map(fn ($r) => ['label' => $r[0]->label(), 'value' => (int) ($counts[$r[0]->value] ?? 0), 'color' => $r[1]])->all();
    }

    private function topProducts(): array
    {
        return Branches::scope(StockItem::query(), 'stock_items.warehouse_id')
            ->join('products', 'products.id', '=', 'stock_items.product_id')
            ->whereIn('stock_items.status', ItemStatus::countsAsStock())
            ->whereNull('products.deleted_at')
            ->selectRaw('products.name, COUNT(*) as units')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units')
            ->limit(7)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'units' => (int) $r->units])
            ->all();
    }

    private function stockTable(): array
    {
        $products = Product::query()
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $qty = Branches::scope(ProductWarehouse::query())
            ->whereIn('product_id', $products->pluck('id'))
            ->get(['product_id', 'warehouse_id', 'qty'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('qty', 'warehouse_id'));

        $transitIds = Branches::scopeTransfers(Transfer::query())->where('status', TransferStatus::IN_TRANSIT)->pluck('id');
        $transitQty = StockItem::query()->whereIn('transfer_id', $transitIds)
            ->selectRaw('product_id, COUNT(*) as n')->groupBy('product_id')->pluck('n', 'product_id');

        return ['products' => $products, 'qty' => $qty, 'transitQty' => $transitQty];
    }
}
