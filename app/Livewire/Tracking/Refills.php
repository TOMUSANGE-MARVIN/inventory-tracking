<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Services\StockTrackingService;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Refillable items that are used up, waiting to be refilled. Scanning one (or
 * tapping Refill) puts it back into stock where it is, with the same barcode.
 */
#[Layout('layouts.app', ['plain' => true])]
#[Title('Refills')]
class Refills extends Component
{
    #[Url]
    public string $warehouse = '';

    #[Url]
    public string $search = '';

    public ?array $flash = null;

    public function mount(): void
    {
        abort_if(Gate::denies('transfer_access'), 403);
    }

    public function scanned(string $code, StockTrackingService $service): void
    {
        $item = $service->findItem($code);

        if (! $item) {
            $this->flash = ['type' => 'error', 'message' => __('No item found for code :code.', ['code' => $code])];

            return;
        }

        $this->refillItem($item, $service);
    }

    public function refill(int $itemId, StockTrackingService $service): void
    {
        $this->refillItem(StockItem::query()->findOrFail($itemId), $service);
    }

    private function refillItem(StockItem $item, StockTrackingService $service): void
    {
        abort_if(Gate::denies('transfer_access'), 403);

        if (! Branches::allows($item->warehouse_id)) {
            $this->flash = ['type' => 'error', 'message' => __(':code is not at your branch (:where).', ['code' => $item->code, 'where' => $item->whereabouts() ?: '—'])];

            return;
        }

        try {
            $service->refill($item);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->flash = ['type' => 'success', 'message' => __(':code – :product refilled at :where and back in stock (refill no. :n).', [
            'code' => $item->code, 'product' => $item->product->name, 'where' => $item->warehouse?->name, 'n' => $item->refill_count,
        ])];
    }

    public function render()
    {
        abort_if(Gate::denies('transfer_access'), 403);

        $locations = Branches::warehouses();
        $warehouse = $this->warehouse !== '' && Branches::allows((int) $this->warehouse) ? (int) $this->warehouse : null;

        $waiting = Branches::scope(StockItem::query())
            ->with('product:id,name,code,unit', 'warehouse:id,name')
            ->where('status', ItemStatus::FINISHED_REFILL)
            ->when($warehouse, fn ($q) => $q->where('warehouse_id', $warehouse))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$this->search}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))))
            ->orderBy('warehouse_id')
            ->get();

        // When each item was emptied, and how many clients its last fill served.
        $emptied = StockScan::query()->where('action', ScanAction::RETURNED_FOR_REFILL)
            ->whereIn('stock_item_id', $waiting->pluck('id'))
            ->selectRaw('stock_item_id, MAX(created_at) as at')->groupBy('stock_item_id')->pluck('at', 'stock_item_id');
        // Order by time, then id for scans made in the same second.
        $order = fn ($scan) => $scan->created_at->format('YmdHis') . str_pad((string) $scan->id, 12, '0', STR_PAD_LEFT);
        $opened = StockScan::query()->where('action', ScanAction::IN_USE)
            ->whereIn('stock_item_id', $waiting->pluck('id'))
            ->get(['id', 'stock_item_id', 'created_at'])->groupBy('stock_item_id')
            ->map(fn ($g) => $g->map($order)->max());
        $served = StockScan::query()->where('action', ScanAction::SERVED_CLIENT)
            ->whereIn('stock_item_id', $waiting->pluck('id'))
            ->get(['id', 'stock_item_id', 'quantity', 'created_at'])->groupBy('stock_item_id');

        $rows = $waiting->map(fn (StockItem $item) => [
            'item' => $item,
            'emptied_at' => isset($emptied[$item->id]) ? \Illuminate\Support\Carbon::parse($emptied[$item->id]) : null,
            'clients' => (int) $served->get($item->id, collect())
                ->filter(fn ($s) => $order($s) > ($opened[$item->id] ?? ''))->sum('quantity'),
        ]);

        $refilled = Branches::scope(StockScan::query())
            ->with('product:id,name', 'warehouse:id,name', 'user:id,name', 'item:id,refill_count')
            ->where('action', ScanAction::REFILLED)
            ->latest('id')->limit(10)->get();

        return view('livewire.tracking.refills', [
            'locations' => $locations,
            'rows' => $rows,
            'byLocation' => $rows->groupBy(fn ($r) => $r['item']->warehouse?->name ?? '—'),
            'refilled' => $refilled,
            'refilledThisMonth' => Branches::scope(StockScan::query())->where('action', ScanAction::REFILLED)->where('created_at', '>=', today()->startOfMonth())->count(),
            'hqId' => app(StockTrackingService::class)->headOffice()?->id,
        ]);
    }
}
