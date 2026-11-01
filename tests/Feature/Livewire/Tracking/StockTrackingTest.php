<?php

declare(strict_types=1);

use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Livewire\Tracking\Arrivals;
use App\Livewire\Tracking\Dispatch;
use App\Livewire\Tracking\Lookup;
use App\Livewire\Tracking\Receive;
use App\Livewire\Tracking\Station;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    $this->actingAs(User::factory()->create()->assignRole('admin'));

    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->branch = Warehouse::factory()->create(['name' => 'Kansanga Branch']);
    $this->product = Product::factory()->create([
        'code' => '6001067004516',
        'category_id' => Category::factory()->create()->id,
    ]);

    // The factory stocks every warehouse; start from nothing.
    ProductWarehouse::query()->where('product_id', $this->product->id)->delete();
});

function qtyAt(Product $product, Warehouse $warehouse): int
{
    return (int) ProductWarehouse::query()
        ->where('product_id', $product->id)
        ->where('warehouse_id', $warehouse->id)
        ->value('qty');
}

/** Scan the product barcode at head office and record N arrivals. */
function arrive(int $qty): array
{
    Livewire::test(Arrivals::class)
        ->set('warehouseId', test()->hq->id)
        ->set('mode', 'loose')
        ->set('productId', test()->product->id)
        ->call('addLine')
        ->call('setLine', test()->product->id, $qty)
        ->call('save')
        ->assertSet('flash.type', 'success');

    return StockItem::query()->orderBy('id')->pluck('code')->all();
}

function dispatchCodes(array $codes): Transfer
{
    $component = Livewire::test(Dispatch::class)
        ->set('fromWarehouseId', test()->hq->id)
        ->set('toWarehouseId', test()->branch->id);

    foreach ($codes as $code) {
        $component->call('scanned', $code);
    }

    $component->call('dispatchTransfer')->assertRedirect(route('tracking.transfers'));

    return Transfer::query()->where('status', TransferStatus::IN_TRANSIT)->latest('dispatched_at')->firstOrFail();
}

function receiveCodes(Transfer $transfer, array $codes): void
{
    $component = Livewire::test(Receive::class, ['transfer' => $transfer]);

    foreach ($codes as $code) {
        $component->call('scanned', $code);
    }

    $component->call('confirm')->assertRedirect(route('tracking.transfers'));
}

it('creates one labelled item per unit arriving at head office', function () {
    $codes = arrive(3);

    expect($codes)->toHaveCount(3)
        ->and($codes[0])->toStartWith('SP-')
        ->and(StockItem::query()->where('warehouse_id', $this->hq->id)->where('status', ItemStatus::IN_STOCK)->count())->toBe(3)
        ->and(qtyAt($this->product, $this->hq))->toBe(3)
        ->and(StockScan::query()->where('action', ScanAction::RECEIVED_STOCK)->count())->toBe(3);
});

it('marks scanned items as in transit and takes them out of head office', function () {
    [$a, $b] = arrive(3);
    $transfer = dispatchCodes([$a, $b]);

    $item = StockItem::query()->where('code', $a)->first();

    expect($transfer->status)->toBe(TransferStatus::IN_TRANSIT)
        ->and($transfer->total_qty)->toBe(2)
        ->and($item->status)->toBe(ItemStatus::IN_TRANSIT)
        ->and($item->warehouse_id)->toBeNull()
        ->and($item->whereabouts())->toBe('On the way to Kansanga Branch')
        ->and(qtyAt($this->product, $this->hq))->toBe(1);
});

it('only dispatches items that are at the source', function () {
    [$a] = arrive(1);
    dispatchCodes([$a]);

    Livewire::test(Dispatch::class)
        ->set('fromWarehouseId', $this->hq->id)
        ->call('scanned', $a)
        ->assertSet('flash.type', 'error')
        ->assertSet('items', []);
});

it('tells staff to scan the item label, not the product barcode', function () {
    arrive(1);

    Livewire::test(Dispatch::class)
        ->set('fromWarehouseId', $this->hq->id)
        ->call('scanned', '6001067004516')
        ->assertSet('flash.type', 'error')
        ->assertSee('product barcode');
});

it('places received items at the branch', function () {
    $codes = arrive(2);
    receiveCodes(dispatchCodes($codes), $codes);

    expect(StockItem::query()->where('warehouse_id', $this->branch->id)->where('status', ItemStatus::IN_STOCK)->count())->toBe(2)
        ->and(Transfer::query()->sole()->status)->toBe(TransferStatus::RECEIVED)
        ->and(qtyAt($this->product, $this->branch))->toBe(2)
        ->and(StockItem::query()->first()->whereabouts())->toBe('Kansanga Branch');
});

it('marks the exact unscanned item as missing', function () {
    [$a, $b, $c] = arrive(3);
    $transfer = dispatchCodes([$a, $b, $c]);
    receiveCodes($transfer, [$a, $c]);

    expect($transfer->fresh()->status)->toBe(TransferStatus::DISCREPANCY)
        ->and(StockItem::query()->where('code', $b)->first()->status)->toBe(ItemStatus::MISSING)
        ->and(qtyAt($this->product, $this->branch))->toBe(2)
        ->and($transfer->transferDetails()->first()->received_quantity)->toBe(2);
});

it('rejects an item that is not on the transfer', function () {
    [$a, $b] = arrive(2);
    $transfer = dispatchCodes([$a]);

    Livewire::test(Receive::class, ['transfer' => $transfer])
        ->call('scanned', $b)
        ->assertSet('flash.type', 'error')
        ->assertSet('scannedIds', []);
});

it('cannot receive the same transfer twice', function () {
    [$a] = arrive(1);
    $transfer = dispatchCodes([$a]);
    receiveCodes($transfer, [$a]);

    Livewire::test(Receive::class, ['transfer' => $transfer->fresh()])->call('confirm')->assertNoRedirect();

    expect(qtyAt($this->product, $this->branch))->toBe(1);
});

it('follows a refillable item through use, back to head office, refill and into stock again', function () {
    $this->product->update(['is_refillable' => true]);
    [$a] = arrive(1);
    receiveCodes(dispatchCodes([$a]), [$a]);

    // Opened, it still counts as the branch's stock until it is used to completion.
    Livewire::test(Station::class)->set('action', 'in_use')->call('scanned', $a)->assertSet('flash.type', 'success');
    expect(qtyAt($this->product, $this->branch))->toBe(1);

    Livewire::test(Station::class)->set('action', 'done')->call('scanned', $a)->assertSet('flash.type', 'success');
    expect(StockItem::query()->first()->status)->toBe(ItemStatus::FINISHED_REFILL)
        ->and(qtyAt($this->product, $this->branch))->toBe(0);

    // The empty container travels back to head office, arrives still empty, and is refilled there.
    Livewire::test(Dispatch::class)
        ->set('fromWarehouseId', $this->branch->id)
        ->set('toWarehouseId', $this->hq->id)
        ->call('scanned', $a)
        ->call('dispatchTransfer')
        ->assertRedirect(route('tracking.transfers'));
    receiveCodes(Transfer::query()->where('to_warehouse_id', $this->hq->id)->sole(), [$a]);

    $item = StockItem::query()->first();
    expect($item->status)->toBe(ItemStatus::FINISHED_REFILL)
        ->and($item->warehouse_id)->toBe($this->hq->id)
        ->and(qtyAt($this->product, $this->hq))->toBe(0);

    Livewire::test(App\Livewire\Tracking\Refills::class)->assertSee($a)->call('scanned', $a)->assertSet('flash.type', 'success');

    $item->refresh();
    expect($item->status)->toBe(ItemStatus::IN_STOCK)
        ->and($item->code)->toBe($a)
        ->and($item->refill_count)->toBe(1)
        ->and(qtyAt($this->product, $this->branch))->toBe(0)
        ->and(qtyAt($this->product, $this->hq))->toBe(1)
        ->and($item->scans()->count())->toBe(8);

    // ...and can go out and be used again with the same barcode.
    receiveCodes(dispatchCodes([$a]), [$a]);
    expect($item->fresh()->status)->toBe(ItemStatus::IN_STOCK)->and(qtyAt($this->product, $this->branch))->toBe(1);
});

it('will not finish an item that was never opened', function () {
    [$a] = arrive(1);

    Livewire::test(Station::class)->set('action', 'done')->call('scanned', $a)->assertSet('flash.type', 'error');
});

it('disposes of a finished item that is not refillable, and will not refill it', function () {
    [$a] = arrive(1);
    receiveCodes(dispatchCodes([$a]), [$a]);

    Livewire::test(Station::class)->call('scanned', $a)->set('action', 'done')->call('scanned', $a)->assertSet('flash.type', 'success');
    expect(StockItem::query()->first()->status)->toBe(ItemStatus::DISPOSED);

    Livewire::test(App\Livewire\Tracking\Refills::class)->call('scanned', $a)->assertSet('flash.type', 'error');
    expect(fn () => app(App\Services\StockTrackingService::class)->record(StockItem::query()->first(), ScanAction::RETURNED_FOR_REFILL))->toThrow(RuntimeException::class);
});

it('shows where an item is', function () {
    [$a] = arrive(1);
    receiveCodes(dispatchCodes([$a]), [$a]);

    Livewire::test(Lookup::class)->call('scanned', strtolower($a))
        ->assertSet('code', $a)
        ->assertSee('Kansanga Branch')
        ->assertSee('Received from transfer');
});

it('renders every tracking page', function (string $route, string $text) {
    $this->get(route($route))->assertOk()->assertSee($text);
})->with([
    ['dashboard', 'Units by location'],
    ['tracking.overview', 'Stock by location'],
    ['tracking.arrivals', 'Boxed delivery'],
    ['tracking.transfers', 'New dispatch'],
    ['tracking.dispatch', 'Scan barcode / QR code'],
    ['tracking.station', 'Open (put into use)'],
    ['tracking.refills', 'Waiting for a refill'],
    ['tracking.lookup', 'Where is this item?'],
    ['tracking.history', 'Movement history'],
    ['tracking.labels', 'Print labels'],
]);

it('renders the receive page for a dispatched transfer', function () {
    $codes = arrive(1);
    $transfer = dispatchCodes($codes);

    $this->get(route('tracking.receive', $transfer))->assertOk()->assertSee($codes[0])->assertSee($transfer->reference);
});
