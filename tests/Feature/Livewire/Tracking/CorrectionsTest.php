<?php

declare(strict_types=1);

use App\Enums\BoxStatus;
use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Livewire\Tracking\Arrivals;
use App\Livewire\Tracking\Lookup;
use App\Livewire\Tracking\Receive;
use App\Livewire\Tracking\StockTakes;
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

// ------------------------------------------------------------------ Stock-take

it('counts a location, flags what is missing or unexpected, and marks missing items', function () {
    [, $loose] = [null, $this->service->receiveArrival($this->product, $this->hq->id, 2)];
    [$ntindaBox, $ntindaItems] = $this->service->receiveBoxedDelivery($this->ntinda->id, [$this->product->id => 1]);
    expect(stockAt($this->product, $this->hq))->toBe(6);

    $component = Livewire::test(StockTakes::class)
        ->set('warehouse', (string) $this->hq->id)
        ->call('start')
        ->assertSet('flash.type', 'success');

    // The sealed box counts its 4 units in one scan; one loose unit is counted; the other is not found.
    $component->call('scanned', $this->box->code)->assertSee('4 units counted')
        ->call('scanned', $loose->first()->code)->assertSet('flash.type', 'success')
        ->call('scanned', $loose->first()->code)->assertSee('already counted')
        // An item the system has at Ntinda turns up at Head Office.
        ->call('scanned', $ntindaItems->first()->code)->assertSee('not expected here')
        ->assertSee($loose->last()->code)
        ->set('markMissing', true)
        ->call('finish')
        ->assertSee('5 found, 1 not found, 1 unexpected');

    $missing = $loose->last()->fresh();
    expect($missing->status)->toBe(ItemStatus::MISSING)
        ->and(stockAt($this->product, $this->hq))->toBe(5)
        ->and(StockScan::query()->where('action', ScanAction::COUNTED)->count())->toBe(5)
        ->and(StockScan::query()->where('action', ScanAction::MARKED_MISSING)->where('stock_item_id', $missing->id)->value('note'))->toContain('Not found at stock-take')
        ->and(StockCorrection::query()->where('type', StockCorrection::STOCK_TAKE)->sole()->details['missing'])->toBe([$missing->code]);
});

it('limits a branch admin to stock-takes at their own branch', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(StockTakes::class)->set('warehouse', (string) $this->hq->id)->call('start')->assertForbidden();
    Livewire::test(StockTakes::class)->set('warehouse', (string) $this->kansanga->id)->call('start')->assertSet('flash.type', 'success');
});
