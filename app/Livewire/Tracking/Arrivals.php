<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Models\Product;
use App\Models\StockBox;
use App\Models\StockItem;
use App\Services\StockTrackingService;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * New stock: either a sealed box (QR on the box, a barcode on every unit inside,
 * box closed again) or loose items such as jerrycans (a barcode each, no box).
 */
#[Layout('layouts.app', ['plain' => true])]
#[Title('Receive new stock')]
class Arrivals extends Component
{
    public string $mode = 'box';

    public ?int $warehouseId = null;

    /** @var array<string, int> units per product id */
    public array $lines = [];

    public ?string $productId = null;

    /** Last product scanned or added, highlighted at the top of the list. */
    public ?string $lastProductId = null;

    public string $batch = '';

    public string $note = '';

    public ?array $flash = null;

    public ?int $createdBoxId = null;

    /** @var array<int> units created by the last save, for label printing */
    public array $createdIds = [];

    public function mount(StockTrackingService $service): void
    {
        abort_if(Gate::denies('adjustment_create'), 403);

        $this->warehouseId = Branches::isSuperAdmin() ? $service->headOffice()?->id : Branches::warehouses()->first()?->id;
    }

    /** Scanning the manufacturer barcode adds one of that product. */
    public function scanned(string $code, StockTrackingService $service): void
    {
        $product = $service->findProduct($code);

        if (! $product) {
            $this->flash = ['type' => 'error', 'message' => __('No product found for code :code. Pick it from the list or add it under Products first.', ['code' => $code])];

            return;
        }

        // Move the product to the end (shown first) and count one more.
        $count = ($this->lines[$product->id] ?? 0) + 1;
        unset($this->lines[$product->id]);
        $this->lines[$product->id] = $count;
        $this->lastProductId = $product->id;
        $this->flash = ['type' => 'success', 'message' => __(':name – :qty', ['name' => $product->name, 'qty' => $this->lines[$product->id]])];
    }

    /** Add a product picked from the list (for products without a barcode). */
    public function addLine(): void
    {
        if (! $this->productId) {
            return;
        }

        $count = ($this->lines[$this->productId] ?? 0) + 1;
        unset($this->lines[$this->productId]);
        $this->lines[$this->productId] = $count;
        $this->lastProductId = $this->productId;
        $this->reset('productId');
    }

    public function setLine(string $productId, int $qty): void
    {
        if ($qty <= 0) {
            unset($this->lines[$productId]);

            return;
        }

        $this->lines[$productId] = min($qty, 500);
    }

    public function save(StockTrackingService $service): void
    {
        Branches::authorize((int) $this->warehouseId);

        if ($this->lines === []) {
            $this->flash = ['type' => 'error', 'message' => $this->mode === 'box' ? __('Add what was inside the box.') : __('Add the items that arrived.')];

            return;
        }

        try {
            if ($this->mode === 'box') {
                [$box, $items] = $service->receiveBoxedDelivery((int) $this->warehouseId, $this->lines, $this->batch ?: null, $this->note ?: null);
                $this->createdBoxId = $box->id;
                $message = __('Box :code recorded with :n units. Stick the QR on the box and a barcode on every unit, then close the box.', ['code' => $box->code, 'n' => $items->count()]);
            } else {
                $items = collect();
                foreach ($this->lines as $productId => $qty) {
                    $items = $items->merge($service->receiveArrival(Product::query()->findOrFail($productId), (int) $this->warehouseId, $qty, $this->batch ?: null, $this->note ?: null));
                }
                $this->createdBoxId = null;
                $message = __(':n loose units recorded. Stick a barcode on each one.', ['n' => $items->count()]);
            }
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->createdIds = $items->pluck('id')->all();
        $this->flash = ['type' => 'success', 'message' => $message];
        $this->reset('lines', 'batch', 'note', 'productId', 'lastProductId');
    }

    public string $undoReason = '';

    /** Undo the delivery just saved (wrong product or quantity). */
    public function undoLast(StockTrackingService $service): void
    {
        $this->validate(['undoReason' => ['required', 'string', 'min:3', 'max:300']], [], ['undoReason' => __('reason')]);

        $box = $this->createdBoxId ? StockBox::query()->find($this->createdBoxId) : null;
        $items = StockItem::query()->whereIn('id', $this->createdIds)->get();

        if ($items->isEmpty()) {
            return;
        }

        Branches::authorize((int) $items->first()->warehouse_id);

        try {
            $service->undoDelivery($box ?? $items, $this->undoReason);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->flash = ['type' => 'success', 'message' => trans_choice('Delivery undone: :n unit removed. Throw away its printed labels.|Delivery undone: :n units removed. Throw away their printed labels.', $items->count(), ['n' => $items->count()])];
        $this->reset('createdIds', 'createdBoxId', 'undoReason');
    }

    public function render()
    {
        return view('livewire.tracking.arrivals', [
            'warehouses' => Branches::warehouses(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'code']),
            'lineProducts' => Product::query()->whereIn('id', array_keys($this->lines))->get(['id', 'name', 'code'])->keyBy('id'),
            'createdBox' => $this->createdBoxId ? StockBox::query()->with('items.product:id,name', 'warehouse:id,name')->find($this->createdBoxId) : null,
            'created' => StockItem::query()->with('product:id,name')->whereIn('id', $this->createdIds)->orderBy('id')->get(),
        ]);
    }
}
