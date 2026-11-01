<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Enums\ItemStatus;
use App\Models\Product;
use App\Models\StockItem;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Reprint item labels, e.g. when one is damaged or for stock already on the shelf. */
#[Layout('layouts.app', ['plain' => true])]
#[Title('Print labels')]
class Labels extends Component
{
    #[Url]
    public string $productId = '';

    #[Url]
    public string $warehouseId = '';

    /** Units carry barcodes; boxes carry QR codes. */
    public string $type = 'barcode';

    #[Url]
    public string $what = 'units';

    #[Url]
    public string $boxId = '';

    public function render()
    {
        abort_if(Gate::denies('print_barcodes'), 403);

        $items = $this->productId === '' ? collect() : Branches::scope(StockItem::query())
            ->with('product:id,name')
            ->where('product_id', $this->productId)
            ->whereIn('status', [ItemStatus::IN_STOCK, ItemStatus::IN_USE, ItemStatus::FINISHED_REFILL])
            ->when($this->warehouseId !== '', fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->orderBy('id')
            ->limit(200)
            ->get();

        $boxes = Branches::scope(\App\Models\StockBox::query())
            ->whereIn('status', [\App\Enums\BoxStatus::PACKED, \App\Enums\BoxStatus::OPENED])
            ->when($this->warehouseId !== '', fn ($q) => $q->where('warehouse_id', $this->warehouseId))
            ->latest('id')->limit(200)->get(['id', 'code', 'status']);

        return view('livewire.tracking.labels', [
            'boxes' => $boxes,
            'box' => $this->boxId !== '' ? Branches::scope(\App\Models\StockBox::query())->with('items.product:id,name', 'warehouse:id,name')->find($this->boxId) : null,
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'warehouses' => Branches::warehouses(),
            'items' => $items,
        ]);
    }
}
