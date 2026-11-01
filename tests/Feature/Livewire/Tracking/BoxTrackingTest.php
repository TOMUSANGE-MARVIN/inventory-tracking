<?php

declare(strict_types=1);

use App\Enums\BoxStatus;
use App\Enums\ItemStatus;
use App\Livewire\Tracking\Arrivals;
use App\Livewire\Tracking\Dispatch;
use App\Livewire\Tracking\Lookup;
use App\Livewire\Tracking\Receive;
use App\Livewire\Tracking\Station;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockBox;
use App\Models\StockItem;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create()->assignRole('admin'));

    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->branch = Warehouse::factory()->create(['name' => 'Kansanga Branch']);

    $category = Category::factory()->create();
    $this->shampoo = Product::factory()->create(['code' => '6001067004516', 'category_id' => $category->id]);
    $this->polish = Product::factory()->create(['code' => '8809557560041', 'category_id' => $category->id]);
    ProductWarehouse::query()->delete();
});

/** A sealed box arrives at Head Office holding 2 shampoo + 1 polish. */
function receiveBox(): StockBox
{
    Livewire::test(Arrivals::class)
        ->set('warehouseId', test()->hq->id)
        ->set('mode', 'box')
        ->call('scanned', '6001067004516')->call('scanned', '6001067004516')
        ->call('scanned', '8809557560041')
        ->call('save')
        ->assertSet('flash.type', 'success');

    return StockBox::query()->latest('id')->firstOrFail();
}

function dispatchBox(StockBox $box): Transfer
{
    Livewire::test(Dispatch::class)
        ->set('fromWarehouseId', test()->hq->id)
        ->set('toWarehouseId', test()->branch->id)
        ->call('scanned', $box->code)
        ->call('dispatchTransfer')
        ->assertRedirect(route('tracking.transfers'));

    return Transfer::query()->latest('dispatched_at')->firstOrFail();
}

it('labels a boxed delivery: one box QR, a unit barcode for everything inside', function () {
    $box = receiveBox();

    expect($box->code)->toStartWith('BX-')
        ->and($box->status)->toBe(BoxStatus::PACKED)
        ->and($box->items()->count())->toBe(3)
        ->and($box->items()->where('product_id', $this->shampoo->id)->count())->toBe(2)
        ->and($box->items()->first()->code)->toStartWith('SP-');
});

it('records loose items such as jerrycans without a box', function () {
    Livewire::test(Arrivals::class)->set('warehouseId', $this->hq->id)->set('mode', 'loose')
        ->call('scanned', '6001067004516')->call('save');

    expect(StockBox::query()->count())->toBe(0)
        ->and(StockItem::query()->whereNull('box_id')->count())->toBe(1);
});

it('sends every unit inside a box when the box QR is scanned', function () {
    $box = receiveBox();
    $transfer = dispatchBox($box);

    expect($transfer->total_qty)->toBe(3)
        ->and($box->fresh()->status)->toBe(BoxStatus::IN_TRANSIT)
        ->and($box->items()->where('status', ItemStatus::IN_TRANSIT)->count())->toBe(3);
});

it('takes a unit out of its box when it is dispatched on its own', function () {
    $box = receiveBox();
    $unit = $box->items()->first();

    Livewire::test(Dispatch::class)->set('fromWarehouseId', $this->hq->id)->set('toWarehouseId', $this->branch->id)
        ->call('scanned', $unit->code)->call('dispatchTransfer');

    expect($unit->fresh()->box_id)->toBeNull()
        ->and($box->fresh()->status)->toBe(BoxStatus::OPENED)
        ->and($box->items()->count())->toBe(2);
});

it('needs the box scanned before the units inside it', function () {
    $box = receiveBox();
    $transfer = dispatchBox($box);
    $unit = $box->items()->first();

    Livewire::test(Receive::class, ['transfer' => $transfer])
        ->call('scanned', $unit->code)->assertSet('flash.type', 'error')->assertSet('scannedIds', [])
        ->call('scanned', $box->code)->assertSet('scannedBoxes', [$box->code])->assertSet('scannedIds', [])
        ->call('scanned', $unit->code)->assertSet('scannedIds', [$unit->id]);
});

it('delivers a box once the box and every unit inside are scanned', function () {
    $box = receiveBox();
    $transfer = dispatchBox($box);

    $component = Livewire::test(Receive::class, ['transfer' => $transfer])->call('scanned', $box->code);
    foreach ($box->items()->pluck('code') as $code) {
        $component->call('scanned', $code);
    }
    $component->call('confirm')->assertRedirect(route('tracking.transfers'));

    $box->refresh();
    expect($transfer->fresh()->status->value)->toBe(2)
        ->and($box->status)->toBe(BoxStatus::PACKED)
        ->and($box->warehouse_id)->toBe($this->branch->id)
        ->and($box->items()->where('warehouse_id', $this->branch->id)->where('status', ItemStatus::IN_STOCK)->count())->toBe(3);
});

it('marks an unscanned unit missing and the box opened', function () {
    $box = receiveBox();
    $transfer = dispatchBox($box);
    [$a, $b, $c] = $box->items()->orderBy('id')->get()->all();

    Livewire::test(Receive::class, ['transfer' => $transfer])
        ->call('scanned', $box->code)->call('scanned', $a->code)->call('scanned', $b->code)
        ->call('confirm');

    expect($c->fresh()->status)->toBe(ItemStatus::MISSING)
        ->and($c->fresh()->box_id)->toBeNull()
        ->and($box->fresh()->status)->toBe(BoxStatus::OPENED)
        ->and($box->items()->count())->toBe(2);
});

it('marks a box that never arrived, and everything in it, as missing', function () {
    $box = receiveBox();
    $transfer = dispatchBox($box);

    Livewire::test(Receive::class, ['transfer' => $transfer])->call('confirm');

    expect($box->fresh()->status)->toBe(BoxStatus::MISSING)
        ->and(StockItem::query()->where('status', ItemStatus::MISSING)->count())->toBe(3);
});

it('takes units out of the box as they are used, until the box is empty', function () {
    $box = receiveBox();

    foreach ($box->items()->pluck('code') as $code) {
        Livewire::test(Station::class)->set('action', 'in_use')->call('scanned', $code)->assertSet('flash.type', 'success');
    }

    expect($box->fresh()->status)->toBe(BoxStatus::EMPTY)
        ->and($box->items()->count())->toBe(0)
        ->and($box->scans()->count())->toBe(6); // 3 arrivals + 3 put into use, all remembered against the box
});

it('looks up a box by its QR', function () {
    $box = receiveBox();

    Livewire::test(Lookup::class)->call('scanned', strtolower($box->code))
        ->assertSet('code', $box->code)
        ->assertSee('Inside the box')
        ->assertSee('Head Office');
});

it('adds a scanned product with quantity 1 and only the quantity is adjusted after that', function () {
    Livewire::test(Arrivals::class)->set('warehouseId', $this->hq->id)
        ->call('scanned', '6001067004516')
        ->assertSet('lines', [$this->shampoo->id => 1])
        ->assertSet('lastProductId', $this->shampoo->id)
        ->call('scanned', '8809557560041')
        ->call('setLine', $this->shampoo->id, 12)
        ->call('setLine', $this->polish->id, 0)
        ->assertSet('lines', [$this->shampoo->id => 12]);
});
