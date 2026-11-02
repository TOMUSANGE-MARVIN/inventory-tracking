<?php

declare(strict_types=1);

use App\Enums\BoxStatus;
use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Livewire\Tracking\Arrivals;
use App\Livewire\Tracking\Lookup;
use App\Livewire\Tracking\Receive;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockBox;
use App\Models\StockCorrection;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTrackingService;
use Livewire\Livewire;

function stockAt(Product $product, Warehouse $warehouse): int
{
    return (int) ProductWarehouse::query()->where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('qty');
}

beforeEach(function () {
    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->kansanga = Warehouse::factory()->create(['name' => 'Kansanga Branch']);
    $this->ntinda = Warehouse::factory()->create(['name' => 'Ntinda Branch']);

    $this->product = Product::factory()->create(['name' => 'Shampoo 5L', 'category_id' => Category::factory()->create()->id]);
    ProductWarehouse::query()->where('product_id', $this->product->id)->delete();

    $this->super = User::factory()->create(['name' => 'Grace'])->assignRole('admin');
    $this->kansangaAdmin = User::factory()->create(['name' => 'Kansanga Admin'])->assignRole('branch-admin');
    $this->kansangaAdmin->warehouses()->sync([$this->kansanga->id]);

    $this->actingAs($this->super);
    $this->service = app(StockTrackingService::class);
    [$this->box, $this->items] = $this->service->receiveBoxedDelivery($this->hq->id, [$this->product->id => 4]);
});

// ------------------------------------------------------------------ Receiving rule

it('lets only the receiving branch scan in and confirm its delivery', function () {
    $transfer = $this->service->dispatch($this->hq->id, $this->kansanga->id, [], boxIds: [$this->box->id]);

    // Super admin: can view, not receive.
    Livewire::test(Receive::class, ['transfer' => $transfer])
        ->assertSee('Waiting for Kansanga Branch')
        ->call('scanned', $this->box->code)->assertSet('scannedBoxes', [])
        ->call('confirm')->assertForbidden();

    // The branch admin receives it.
    $this->actingAs($this->kansangaAdmin);
    $component = Livewire::test(Receive::class, ['transfer' => $transfer])->call('scanned', $this->box->code);
    foreach ($this->items as $item) {
        $component->call('scanned', $item->code);
    }
    $component->call('confirm')->assertRedirect(route('tracking.transfers'));

    expect($transfer->fresh()->status)->toBe(TransferStatus::RECEIVED)
        ->and($transfer->fresh()->received_by)->toBe($this->kansangaAdmin->id);
});

it('lets head office receive deliveries sent back to it', function () {
    $this->actingAs($this->super);
    [$box, $items] = $this->service->receiveBoxedDelivery($this->ntinda->id, [$this->product->id => 1]);
    $transfer = $this->service->dispatch($this->ntinda->id, $this->hq->id, [], boxIds: [$box->id]);

    Livewire::test(Receive::class, ['transfer' => $transfer])
        ->call('scanned', $box->code)->call('scanned', $items->first()->code)
        ->call('confirm')->assertRedirect(route('tracking.transfers'));
});

// ------------------------------------------------------------------ Cancel dispatch

it('cancels a dispatch and puts everything back where it was', function () {
    $transfer = $this->service->dispatch($this->hq->id, $this->kansanga->id, [], boxIds: [$this->box->id]);
    expect(stockAt($this->product, $this->hq))->toBe(0);

    // The receiving branch cannot cancel it.
    $this->actingAs($this->kansangaAdmin);
    Livewire::test(Receive::class, ['transfer' => $transfer])->assertDontSee('Cancel this dispatch');

    $this->actingAs($this->super);
    Livewire::test(Receive::class, ['transfer' => $transfer])
        ->call('cancelDispatch')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Sent to the wrong branch')
        ->call('cancelDispatch')->assertRedirect(route('tracking.transfers'));

    expect($transfer->fresh()->status)->toBe(TransferStatus::CANCELLED)
        ->and(stockAt($this->product, $this->hq))->toBe(4)
        ->and($this->box->fresh())->status->toBe(BoxStatus::PACKED)
        ->and($this->box->fresh()->warehouse_id)->toBe($this->hq->id)
        ->and(StockItem::query()->where('status', ItemStatus::IN_STOCK)->where('warehouse_id', $this->hq->id)->count())->toBe(4)
        ->and(StockScan::query()->where('action', ScanAction::DISPATCH_CANCELLED)->count())->toBe(4)
        ->and(StockCorrection::query()->sole())->type->toBe(StockCorrection::CANCEL_TRANSFER)->reason->toBe('Sent to the wrong branch');

    // It can be dispatched again, to the right branch.
    $again = $this->service->dispatch($this->hq->id, $this->ntinda->id, [], boxIds: [$this->box->id]);
    expect($again->status)->toBe(TransferStatus::IN_TRANSIT);
});

// ------------------------------------------------------------------ Undo delivery

it('undoes a delivery entered by mistake, straight after saving it', function () {
    $component = Livewire::test(Arrivals::class)
        ->set('warehouseId', $this->hq->id)
        ->call('scanned', $this->product->code)
        ->call('setLine', $this->product->id, 3)
        ->call('save')
        ->assertSee('Undo this delivery');

    expect(stockAt($this->product, $this->hq))->toBe(7);

    $component->call('undoLast')->assertHasErrors('undoReason')
        ->set('undoReason', 'Wrong quantity entered')
        ->call('undoLast')->assertSet('flash.type', 'success');

    expect(stockAt($this->product, $this->hq))->toBe(4)
        ->and(StockBox::query()->count())->toBe(1)
        ->and(StockItem::query()->count())->toBe(4)
        ->and(StockCorrection::query()->sole())->type->toBe(StockCorrection::UNDO_DELIVERY)->user_id->toBe($this->super->id);
});

it('only offers undo while nothing has happened to the delivery', function () {
    Livewire::test(Lookup::class)->set('code', $this->box->code)->assertSee('Undo this delivery entry');

    $this->service->dispatch($this->hq->id, $this->kansanga->id, [$this->items->first()->id]);

    Livewire::test(Lookup::class)->set('code', $this->box->code)->assertDontSee('Undo this delivery entry');
    expect(fn () => $this->service->undoDelivery($this->box->fresh(), 'test'))->toThrow(RuntimeException::class);
});
