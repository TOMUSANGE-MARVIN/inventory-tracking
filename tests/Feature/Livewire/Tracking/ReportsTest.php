<?php

declare(strict_types=1);

use App\Livewire\Tracking\Reports;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTrackingService;
use App\Services\TrackingReports;
use Livewire\Livewire;

beforeEach(function () {
    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->kansanga = Warehouse::factory()->create(['name' => 'Kansanga Branch']);
    $this->ntinda = Warehouse::factory()->create(['name' => 'Ntinda Branch']);

    $this->super = User::factory()->create(['name' => 'Grace'])->assignRole('admin');
    $this->kansangaAdmin = User::factory()->create(['name' => 'Kansanga Admin'])->assignRole('branch-admin');
    $this->kansangaAdmin->warehouses()->sync([$this->kansanga->id]);

    $this->product = Product::factory()->create(['code' => '6001067004516', 'category_id' => Category::factory()->create()->id, 'cost' => 10000, 'price' => 15000]);
    ProductWarehouse::query()->where('product_id', $this->product->id)->delete();

    // A box of 4 arrives; 3 go to Kansanga (1 goes missing), 1 stays at Head Office.
    $this->actingAs($this->super);
    $service = app(StockTrackingService::class);
    [$box, $items] = $service->receiveBoxedDelivery($this->hq->id, [$this->product->id => 4]);
    $toKansanga = $service->dispatch($this->hq->id, $this->kansanga->id, $items->take(3)->pluck('id')->all());
    $service->receive($toKansanga, $items->take(2)->pluck('id')->all());
});

function reportsFor(?int $warehouse = null): TrackingReports
{
    return new TrackingReports(today()->subDays(7), today(), $warehouse);
}

it('reports losses with value, loss rate and who handled the transfer', function () {
    $losses = reportsFor()->losses();

    expect($losses['totals']['units'])->toBe(1)
        ->and($losses['totals']['cost'])->toEqual(10000)
        ->and($losses['totals']['sent'])->toBe(3)
        ->and($losses['totals']['rate'])->toEqual(33.3)
        ->and($losses['byLocation']->firstWhere('location', 'Kansanga Branch')['units'])->toBe(1)
        ->and($losses['rows']->first()['dispatched_by'])->toBe('Grace');
});

it('reports transfers with delivery status and the people involved', function () {
    $transfers = reportsFor()->transfers();

    expect($transfers['totals']['count'])->toBe(1)
        ->and($transfers['totals']['short'])->toBe(1)
        ->and($transfers['totals']['full_rate'])->toEqual(0)
        ->and($transfers['rows']->first()['missing'])->toBe(1)
        ->and($transfers['staff']->first())->toMatchArray(['name' => 'Grace', 'dispatched' => 1, 'received' => 1, 'short' => 1]);
});

it('counts in-use items as stock until they are used up', function () {
    $service = app(StockTrackingService::class);
    $item = StockItem::query()->where('warehouse_id', $this->kansanga->id)->first();

    $service->record($item, App\Enums\ScanAction::IN_USE);
    expect(reportsFor()->stockValue()['byLocation']->firstWhere('location', 'Kansanga Branch'))->toMatchArray(['units' => 2, 'in_use' => 1])
        ->and(ProductWarehouse::query()->where('warehouse_id', $this->kansanga->id)->value('qty'))->toBe(2);

    $service->finish($item->fresh());
    expect(reportsFor()->stockValue()['byLocation']->firstWhere('location', 'Kansanga Branch'))->toMatchArray(['units' => 1, 'in_use' => 0])
        ->and(ProductWarehouse::query()->where('warehouse_id', $this->kansanga->id)->value('qty'))->toBe(1);
});

it('values the stock on hand at each location', function () {
    $value = reportsFor()->stockValue();

    expect($value['byLocation']->firstWhere('location', 'Head Office'))->toMatchArray(['units' => 1, 'cost' => 10000.0, 'price' => 15000.0])
        ->and($value['byLocation']->firstWhere('location', 'Kansanga Branch'))->toMatchArray(['units' => 2, 'cost' => 20000.0])
        ->and($value['totals']['units'])->toBe(3);
});

it('lists movements for the period', function () {
    // 4 arrivals + 3 dispatches + 2 received + 1 missing
    expect(reportsFor()->movementsQuery()->count())->toBe(10);
});

it('limits a branch admin to their own branch, even when asking for another', function () {
    $this->actingAs($this->kansangaAdmin);

    expect(reportsFor()->locations()->pluck('id')->all())->toBe([$this->kansanga->id])
        ->and(reportsFor()->stockValue()['totals']['units'])->toBe(2);

    // Asking for Head Office from the screen falls back to their branch only.
    Livewire::test(Reports::class)->set('tab', 'value')->set('warehouse', (string) $this->hq->id)
        ->assertViewHas('data', fn ($d) => $d['totals']['units'] === 2);

    $this->get(route('tracking.reports.export', ['tab' => 'value', 'format' => 'xlsx', 'warehouse' => $this->hq->id]))->assertOk();
});

it('renders every tab', function (string $tab, string $text) {
    $this->get(route('tracking.reports', ['tab' => $tab]))->assertOk()->assertSee($text);
})->with([
    ['losses', 'Every missing unit'],
    ['transfers', 'Average delivery time'],
    ['value', 'Value at cost'],
    ['movements', 'Arrived – new stock'],
]);

it('downloads Excel and PDF', function (string $format, string $type) {
    $response = $this->get(route('tracking.reports.export', ['tab' => 'losses', 'format' => $format]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain($type);
})->with([
    ['xlsx', 'spreadsheetml'],
    ['pdf', 'application/pdf'],
]);

it('compares customers per item across branches', function () {
    $service = app(StockTrackingService::class);
    $ntindaAdmin = User::factory()->create()->assignRole('branch-admin');
    $ntindaAdmin->warehouses()->sync([$this->ntinda->id]);

    // Two more units go to Ntinda.
    $more = $service->receiveArrival($this->product, $this->hq->id, 2);
    $service->receive($service->dispatch($this->hq->id, $this->ntinda->id, $more->pluck('id')->all()), $more->pluck('id')->all());

    // Kansanga: one bottle serves 10 customers. Ntinda: two bottles serve 4 and 6 (5 each).
    $useUp = function ($item, int $customers) use ($service) {
        $service->serveClient($item->fresh(), $customers);
        $service->finish($item->fresh());
    };
    $useUp(StockItem::query()->where('warehouse_id', $this->kansanga->id)->first(), 10);
    $useUp($more[0], 4);
    $useUp($more[1], 6);

    $compare = reportsFor()->branchComparison();
    $row = $compare['rows']->first();

    expect($compare['locations']->pluck('name')->all())->toBe(['Kansanga Branch', 'Ntinda Branch'])
        ->and($row['cells'][$this->kansanga->id])->toMatchArray(['per_fill' => 10.0, 'fills' => 1, 'vs_average' => 33])
        ->and($row['cells'][$this->ntinda->id])->toMatchArray(['per_fill' => 5.0, 'fills' => 2, 'vs_average' => -33])
        ->and($row['average'])->toEqual(7.5)
        ->and($row['best'])->toBe($this->kansanga->id)
        ->and($row['worst'])->toBe($this->ntinda->id)
        // Cost 10,000: 2,000 per customer at Ntinda vs 1,000 at Kansanga.
        ->and($row['cost_gap'])->toBe(1000);

    $this->get(route('tracking.reports', ['tab' => 'compare']))->assertOk()->assertSee(['Customers per item by branch', 'Kansanga', 'Ntinda', '-33%']);
    $this->get(route('tracking.reports.export', ['tab' => 'compare', 'format' => 'xlsx']))->assertOk();

    // A branch admin only sees their own branch.
    $this->actingAs($ntindaAdmin);
    expect(reportsFor()->branchComparison()['locations']->pluck('name')->all())->toBe(['Ntinda Branch']);
});
