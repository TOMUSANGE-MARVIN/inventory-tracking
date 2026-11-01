<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Models\Warehouse;
use App\Services\StockTrackingService;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;
use Throwable;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Dispatch to branch')]
class Dispatch extends Component
{
    public ?int $fromWarehouseId = null;

    public ?int $toWarehouseId = null;

    /** @var array<int, array{code:string, product:string}> loose units keyed by item id */
    public array $items = [];

    /** @var array<int, array{code:string, summary:string, units:int}> whole boxes keyed by box id */
    public array $boxes = [];

    public string $note = '';

    public ?array $flash = null;

    public function mount(StockTrackingService $service): void
    {
        abort_if(Gate::denies('transfer_create'), 403);

        $this->fromWarehouseId = Branches::isSuperAdmin() ? $service->headOffice()?->id : Branches::warehouses()->first()?->id;
    }

    public function updatedFromWarehouseId(): void
    {
        Branches::authorize((int) $this->fromWarehouseId);

        // Boxes and items are only valid for the location they were scanned at.
        $this->items = [];
        $this->boxes = [];
    }

    public function scanned(string $code, StockTrackingService $service): void
    {
        if ($box = $service->findBox($code)) {
            $this->addBox($box, $service);

            return;
        }

        $item = $service->findItem($code);

        if (! $item) {
            $this->flash = ['type' => 'error', 'message' => $service->findProduct($code)
                ? __('That is the product barcode. Scan the item\'s own SP- label instead.')
                : __('No item found for code :code', ['code' => $code])];

            return;
        }

        if (isset($this->items[$item->id])) {
            $this->flash = ['type' => 'warning', 'message' => __(':code is already on this dispatch.', ['code' => $item->code])];

            return;
        }

        try {
            $service->assertDispatchable($item, (int) $this->fromWarehouseId);
        } catch (RuntimeException $runtimeException) {
            $this->flash = ['type' => 'error', 'message' => $runtimeException->getMessage()];

            return;
        }

        if ($item->box_id && isset($this->boxes[$item->box_id])) {
            $this->flash = ['type' => 'warning', 'message' => __(':code is already going inside box :box.', ['code' => $item->code, 'box' => $this->boxes[$item->box_id]['code']])];

            return;
        }

        $this->items[$item->id] = ['code' => $item->code, 'product' => $item->product->name];
        $this->flash = $item->box_id
            ? ['type' => 'warning', 'message' => __('Added :code on its own – it will be taken out of its box.', ['code' => $item->code])]
            : ['type' => 'success', 'message' => __('Added :code – :name', ['code' => $item->code, 'name' => $item->product->name])];
    }

    private function addBox(\App\Models\StockBox $box, StockTrackingService $service): void
    {
        if (isset($this->boxes[$box->id])) {
            $this->flash = ['type' => 'warning', 'message' => __(':code is already on this dispatch.', ['code' => $box->code])];

            return;
        }

        try {
            $service->assertBoxDispatchable($box, (int) $this->fromWarehouseId);
        } catch (RuntimeException $runtimeException) {
            $this->flash = ['type' => 'error', 'message' => $runtimeException->getMessage()];

            return;
        }

        $box->load('items.product:id,name');
        $inside = $box->items->where('status', \App\Enums\ItemStatus::IN_STOCK);
        // Units already scanned loose from this box now travel inside it.
        foreach ($inside as $unit) {
            unset($this->items[$unit->id]);
        }

        $this->boxes[$box->id] = ['code' => $box->code, 'summary' => $box->summary(), 'units' => $inside->count()];
        $this->flash = ['type' => 'success', 'message' => __('Added box :code – :n units', ['code' => $box->code, 'n' => $inside->count()])];
    }

    public function removeBox(int $boxId): void
    {
        unset($this->boxes[$boxId]);
    }

    public function remove(int $itemId): void
    {
        unset($this->items[$itemId]);
    }

    public function dispatchTransfer(StockTrackingService $service): void
    {
        abort_if(Gate::denies('transfer_create'), 403);
        Branches::authorize((int) $this->fromWarehouseId);

        try {
            $transfer = $service->dispatch((int) $this->fromWarehouseId, (int) $this->toWarehouseId, array_keys($this->items), $this->note ?: null, array_keys($this->boxes));
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        session()->flash('tracking', ['type' => 'success', 'message' => __('Transfer :ref dispatched. The branch scans the boxes and items in to receive them.', ['ref' => $transfer->reference])]);

        $this->redirectRoute('tracking.transfers', navigate: true);
    }

    public function render()
    {
        return view('livewire.tracking.dispatch', [
            'fromWarehouses' => Branches::warehouses(),
            'warehouses' => Warehouse::query()->orderBy('id')->get(['id', 'name']),
            'grouped' => collect($this->items)->map(fn ($i, $id) => $i + ['id' => $id])->groupBy('product'),
        ]);
    }
}
