<?php

declare(strict_types=1);

namespace App\Livewire\Tracking;

use App\Models\StockBox;
use App\Models\StockItem;
use App\Services\StockTrackingService;
use App\Support\Branches;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app', ['plain' => true])]
#[Title('Where is this item?')]
class Lookup extends Component
{
    #[Url]
    public string $code = '';

    public ?array $flash = null;

    public string $undoReason = '';

    public function scanned(string $code, StockTrackingService $service): void
    {
        $this->flash = null;

        if ($box = $service->findBox($code)) {
            if (! self::boxVisible($box)) {
                $this->code = '';
                $this->flash = ['type' => 'error', 'message' => __(':code is not one of your branch\'s boxes.', ['code' => $box->code])];

                return;
            }

            $this->code = $box->code;

            return;
        }

        $item = $service->findItem($code);

        if ($item && ! self::visible($item)) {
            $this->code = '';
            $this->flash = ['type' => 'error', 'message' => __(':code is not one of your branch\'s items.', ['code' => $item->code])];

            return;
        }

        if ($item) {
            $this->code = $item->code;

            return;
        }

        $product = $service->findProduct($code);
        $this->code = '';
        $this->flash = $product
            ? ['type' => 'warning', 'message' => __('That is the product barcode for :name, shared by every unit. Scan the unit\'s SP- label to see where that unit is.', ['name' => $product->name])]
            : ['type' => 'error', 'message' => __('No item found for code :code', ['code' => $code])];
    }

    /** The box (or loose unit) on screen, if its delivery can still be undone by this user. */
    private function undoTarget(StockTrackingService $service): StockBox|StockItem|null
    {
        $box = str_starts_with($this->code, 'BX-') ? StockBox::query()->where('code', $this->code)->first() : null;
        $item = $box ? null : StockItem::query()->where('code', $this->code)->first();
        $target = $box ?? ($item && $item->box_id === null ? $item : null);

        if (! $target || Gate::denies('adjustment_create') || ! Branches::allows($target->warehouse_id)) {
            return null;
        }

        return $service->undoBlocker($service->deliveryItems($target), $box) === null ? $target : null;
    }

    public function undoDelivery(StockTrackingService $service): void
    {
        $target = $this->undoTarget($service);
        abort_unless($target !== null, 403);

        $this->validate(['undoReason' => ['required', 'string', 'min:3', 'max:300']], [], ['undoReason' => __('reason')]);

        try {
            $service->undoDelivery($target, $this->undoReason);
        } catch (Throwable $throwable) {
            $this->flash = ['type' => 'error', 'message' => $throwable->getMessage()];

            return;
        }

        $this->flash = ['type' => 'success', 'message' => __('Delivery entry :code undone and removed from stock. Throw away its printed labels.', ['code' => $this->code])];
        $this->reset('code', 'undoReason');
    }

    /** Branch admins see items at, travelling to/from, or ever scanned at their branches. */
    private static function visible(StockItem $item): bool
    {
        if (Branches::ids() === null || Branches::allows($item->warehouse_id)) {
            return true;
        }

        if ($item->transfer && (Branches::allows($item->transfer->from_warehouse_id) || Branches::allows($item->transfer->to_warehouse_id))) {
            return true;
        }

        return Branches::scope($item->scans())->exists();
    }

    private static function boxVisible(StockBox $box): bool
    {
        return Branches::ids() === null
            || Branches::allows($box->warehouse_id)
            || ($box->transfer && (Branches::allows($box->transfer->from_warehouse_id) || Branches::allows($box->transfer->to_warehouse_id)))
            || Branches::scope($box->scans())->exists();
    }

    public function render()
    {
        $canUndo = $this->code !== '' && $this->undoTarget(app(StockTrackingService::class)) !== null;

        $box = str_starts_with($this->code, 'BX-')
            ? StockBox::query()->with('warehouse', 'transfer.toWarehouse', 'items.product:id,name')->where('code', $this->code)->first()
            : null;

        if ($box && ! self::boxVisible($box)) {
            $box = null;
        }

        if ($box) {
            return view('livewire.tracking.lookup', [
                'canUndo' => $canUndo,
                'item' => null,
                'box' => $box,
                // One row per movement of the box (its units move together).
                'history' => $box->scans()->with('warehouse:id,name', 'user:id,name', 'transfer:id,reference')->latest('id')->get()
                    ->unique(fn ($s) => $s->getRawOriginal('action') . '|' . $s->transfer_id . '|' . $s->warehouse_id . '|' . $s->created_at->format('YmdHi'))
                    ->values(),
                'missing' => Overview::missingItems()->with('product:id,name')->latest('updated_at')->limit(20)->get(),
            ]);
        }

        abort_if(Gate::denies('transfer_access'), 403);

        $item = $this->code !== ''
            ? StockItem::query()->with('product', 'warehouse', 'transfer.toWarehouse')->where('code', $this->code)->first()
            : null;

        // The code can also arrive in the URL, so check visibility here too.
        if ($item && ! self::visible($item)) {
            $item = null;
            $this->flash = ['type' => 'error', 'message' => __(':code is not one of your branch\'s items.', ['code' => $this->code])];
        }

        return view('livewire.tracking.lookup', [
            'canUndo' => $canUndo,
            'box' => null,
            'item' => $item,
            'analytics' => $item ? new \App\Services\ItemAnalytics($item) : null,
            'history' => $item?->scans()->with('warehouse:id,name', 'user:id,name', 'transfer:id,reference')->latest('id')->get() ?? collect(),
            'missing' => Overview::missingItems()->with('product:id,name')->latest('updated_at')->limit(20)->get(),
        ]);
    }
}
