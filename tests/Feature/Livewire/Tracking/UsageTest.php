<?php

declare(strict_types=1);

use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Livewire\Team\Index as Team;
use App\Livewire\Tracking\Station;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockScan;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductBoxHistory;
use App\Services\StockTrackingService;
use App\Services\TrackingReports;
use Livewire\Livewire;

beforeEach(function () {
    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->kansanga = Warehouse::factory()->create(['name' => 'Kansanga Branch']);
    $this->ntinda = Warehouse::factory()->create(['name' => 'Ntinda Branch']);

    $this->product = Product::factory()->create(['name' => 'Shampoo 5L', 'cost' => 60000, 'category_id' => Category::factory()->create()->id]);
    ProductWarehouse::query()->where('product_id', $this->product->id)->delete();

    $this->actingAs(User::factory()->create()->assignRole('admin'));
    $service = app(StockTrackingService::class);
    [$this->box, $this->items] = $service->receiveBoxedDelivery($this->hq->id, [$this->product->id => 3]);
    $service->receive($service->dispatch($this->hq->id, $this->kansanga->id, [], boxIds: [$this->box->id]), $this->items->pluck('id')->all(), scannedBoxIds: [$this->box->id]);

    $this->worker = User::factory()->create(['name' => 'Aisha', 'station' => 'Hair chair 1'])->assignRole('worker');
    $this->worker->warehouses()->sync([$this->kansanga->id]);
});

it('records a client per scan, opening a sealed item on its first client', function () {
    $this->actingAs($this->worker);
    $item = $this->items->first()->fresh();

    Livewire::test(Station::class)
        ->call('scanned', $item->code)
        ->assertSet('flash.type', 'success')
        ->call('scanned', $item->code)
        ->assertSee('served 2 clients');

    expect($item->fresh()->status)->toBe(ItemStatus::IN_USE)
        ->and(StockScan::query()->where('action', ScanAction::SERVED_CLIENT)->where('stock_item_id', $item->id)->sum('quantity'))->toEqual(2)
        ->and(StockScan::query()->where('action', ScanAction::SERVED_CLIENT)->first())->toMatchArray(['station' => 'Hair chair 1', 'user_id' => $this->worker->id, 'warehouse_id' => $this->kansanga->id]);
});

it('lets a worker undo a double scan and record several clients at once', function () {
    $this->actingAs($this->worker);
    $code = $this->items->first()->code;

    Livewire::test(Station::class)
        ->set('clients', 3)->call('scanned', $code)
        ->call('undo')
        ->assertSet('flash.type', 'success');

    expect(StockScan::query()->where('action', ScanAction::SERVED_CLIENT)->count())->toBe(0);
});

it('lets a worker mark the item done after a client scan; refillable ones wait for a refill', function () {
    $this->product->update(['is_refillable' => true]);
    $this->actingAs($this->worker);
    $item = $this->items->first();

    Livewire::test(Station::class)
        ->call('scanned', $item->code)
        ->assertSee('needs refill')
        ->call('finishLast')
        ->assertSet('flash.type', 'success');

    expect($item->fresh()->status)->toBe(ItemStatus::FINISHED_REFILL);

    // Workers cannot open items or reach the refills page.
    Livewire::test(Station::class)->set('action', 'in_use')->assertSet('action', 'served_client');
    $this->get(route('tracking.refills'))->assertRedirect(route('tracking.station'));
});

it('counts clients per fill for refillable items', function () {
    $this->product->update(['is_refillable' => true]);
    $service = app(StockTrackingService::class);
    $item = $this->items->first();

    $this->actingAs($this->worker);
    foreach (range(1, 6) as $i) {
        $service->serveClient($item->fresh());
    }
    $service->finish($item->fresh());

    $this->actingAs(User::role('admin')->first());
    $service->refill($item->fresh());

    $this->actingAs($this->worker);
    foreach (range(1, 4) as $i) {
        $service->serveClient($item->fresh());
    }
    $service->finish($item->fresh());

    $usage = (new TrackingReports(today(), today()))->usage();

    expect($usage['totals'])->toMatchArray(['clients' => 10, 'finished' => 2, 'per_unit' => 5.0])
        ->and($item->fresh()->refill_count)->toBe(1);
});

it('keeps workers to client scans at their own branch', function () {
    $this->actingAs($this->worker);

    // Asking for another mode still records a client.
    Livewire::test(Station::class)->set('action', 'disposed')->call('scanned', $this->items->first()->code);
    expect(StockScan::query()->where('action', ScanAction::DISPOSED)->count())->toBe(0)
        ->and(StockScan::query()->where('action', ScanAction::SERVED_CLIENT)->count())->toBe(1);

    // An item at another branch is refused.
    $this->actingAs(User::role('admin')->first());
    [, $ntindaItems] = app(StockTrackingService::class)->receiveBoxedDelivery($this->ntinda->id, [$this->product->id => 1]);
    $this->actingAs($this->worker);
    Livewire::test(Station::class)->call('scanned', $ntindaItems->first()->code)->assertSet('flash.type', 'error');
});

it('sends workers to the scan station from every other page', function () {
    $this->actingAs($this->worker);

    $this->get(route('dashboard'))->assertRedirect(route('tracking.station'));
    $this->get(route('tracking.reports'))->assertRedirect(route('tracking.station'));
    $this->get(route('products.index'))->assertRedirect(route('tracking.station'));
    $this->get(route('tracking.station'))->assertOk()->assertSee('Hair chair 1')->assertDontSee(__('Stock tracking'));
});

it('reports clients by branch, product, worker and per finished item', function () {
    $service = app(StockTrackingService::class);
    $this->actingAs($this->worker);
    $first = $this->items->first()->fresh();
    foreach (range(1, 4) as $i) {
        $service->serveClient($first->fresh());
    }

    $this->actingAs(User::role('admin')->first());
    $service->record($first->fresh(), ScanAction::DISPOSED);

    $usage = (new TrackingReports(today(), today()))->usage();

    expect($usage['totals'])->toMatchArray(['clients' => 4, 'units' => 1, 'finished' => 1, 'per_unit' => 4.0])
        ->and($usage['byLocation']->firstWhere('location', 'Kansanga Branch')['clients'])->toBe(4)
        ->and($usage['byProduct']->first())->toMatchArray(['product' => 'Shampoo 5L', 'clients' => 4, 'per_unit' => 4.0, 'cost_per_client' => 15000.0])
        ->and($usage['byWorker']->first())->toMatchArray(['worker' => 'Aisha', 'station' => 'Hair chair 1', 'clients' => 4]);

    // The box the item came in shows its usage too.
    $history = new ProductBoxHistory((string) $this->product->id, today(), today());
    $d = $history->details($history->boxes()->items())[$this->box->id];
    expect($d['clients_all'])->toBe(4)
        ->and($d['units']->firstWhere('code', $first->code)['clients'])->toBe(4)
        ->and($d['events']->firstWhere('action', ScanAction::SERVED_CLIENT)['clients'])->toBe(4);

    $this->get(route('tracking.reports', ['tab' => 'usage']))->assertOk()->assertSee(['By worker & station', 'Hair chair 1']);
    $this->get(route('tracking.reports.export', ['tab' => 'usage', 'format' => 'xlsx']))->assertOk();
});

it('adds a worker with a branch and station from Team & access', function () {
    Livewire::test(Team::class)
        ->call('create')
        ->set('userRole', 'worker')
        ->set('name', 'Brenda')->set('email', 'brenda@sparkle.test')->set('password', 'secret123')
        ->call('save')->assertHasErrors(['branchId', 'station'])
        ->set('branchId', $this->kansanga->id)->set('station', 'Nail bar')
        ->call('save')->assertHasNoErrors();

    $brenda = User::query()->where('email', 'brenda@sparkle.test')->first();
    expect($brenda->hasRole('worker'))->toBeTrue()
        ->and($brenda->station)->toBe('Nail bar')
        ->and($brenda->warehouses->pluck('id')->all())->toBe([$this->kansanga->id]);
});

it('shows the analytics of a single item: fills, customers per worker and its journey', function () {
    $this->product->update(['is_refillable' => true]);
    $service = app(StockTrackingService::class);
    $item = $this->items->first();

    $this->actingAs($this->worker);
    foreach (range(1, 6) as $i) {
        $service->serveClient($item->fresh());
    }
    $service->finish($item->fresh());

    $this->actingAs(User::role('admin')->first());
    $service->refill($item->fresh());
    $service->serveClient($item->fresh(), 2);

    $analytics = new App\Services\ItemAnalytics($item->fresh());
    $summary = $analytics->summary();

    expect($analytics->fills()->map(fn ($f) => [$f['customers'], $f['ended']])->all())->toBe([[6, ScanAction::RETURNED_FOR_REFILL], [2, null]])
        ->and($summary)->toMatchArray(['customers' => 8, 'refills' => 1, 'fills' => 2, 'per_fill' => 6.0, 'moves' => 1])
        ->and($summary['current']['customers'])->toBe(2)
        ->and($analytics->byWorker()->first())->toMatchArray(['worker' => 'Aisha', 'station' => 'Hair chair 1', 'customers' => 6])
        ->and($analytics->journey()->pluck('location')->all())->toBe(['Head Office', 'Kansanga Branch']);

    $this->get(route('tracking.lookup', ['code' => $item->code]))
        ->assertOk()
        ->assertSee(['Item analytics', 'Customers per fill', 'Customers by worker', 'Hair chair 1', 'Sent for refill']);
});
