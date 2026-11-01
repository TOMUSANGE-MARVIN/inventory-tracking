<?php

declare(strict_types=1);

use App\Enums\ScanAction;
use App\Livewire\Analytics\ProductAnalytics;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockScan;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductBoxHistory;
use App\Services\StockTrackingService;
use Livewire\Livewire;

beforeEach(function () {
    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->kansanga = Warehouse::factory()->create(['name' => 'Kansanga Branch']);
    $this->ntinda = Warehouse::factory()->create(['name' => 'Ntinda Branch']);

    $category = Category::factory()->create();
    $this->shampoo = Product::factory()->create(['name' => 'Shampoo 5L', 'category_id' => $category->id]);
    $this->polish = Product::factory()->create(['name' => 'Gel Polish Red', 'category_id' => $category->id]);
    ProductWarehouse::query()->delete();

    $this->actingAs(User::factory()->create(['name' => 'Grace'])->assignRole('admin'));
    $service = app(StockTrackingService::class);

    // Mixed box: 3 shampoo + 2 polish → Kansanga, one shampoo goes missing.
    [$this->mixed, $items] = $service->receiveBoxedDelivery($this->hq->id, [$this->shampoo->id => 3, $this->polish->id => 2]);
    $transfer = $service->dispatch($this->hq->id, $this->kansanga->id, [], boxIds: [$this->mixed->id]);
    $service->receive($transfer, $items->skip(1)->pluck('id')->all(), scannedBoxIds: [$this->mixed->id]);

    // Second box of shampoo → Ntinda.
    [$this->toNtinda, $items2] = $service->receiveBoxedDelivery($this->hq->id, [$this->shampoo->id => 2]);
    $service->dispatch($this->hq->id, $this->ntinda->id, [], boxIds: [$this->toNtinda->id]);
});

it('lists every box that held the product with its grouped records', function () {
    $history = new ProductBoxHistory((string) $this->shampoo->id, today()->subDay(), today());
    $boxes = $history->boxes();
    $details = $history->details($boxes->items());

    expect($boxes->total())->toBe(2);

    $mixed = $details[$this->mixed->id];
    expect($mixed['mine_count'])->toBe(3)
        ->and($mixed['total_count'])->toBe(5)
        ->and($mixed['others']->all())->toBe(['2 × Gel Polish Red'])
        ->and($mixed['events']->pluck('action')->all())->toContain(ScanAction::RECEIVED_STOCK, ScanAction::DISPATCHED, ScanAction::RECEIVED_TRANSFER, ScanAction::MARKED_MISSING);

    $dispatch = $mixed['events']->firstWhere('action', ScanAction::DISPATCHED);
    expect($dispatch)->toMatchArray(['units' => 5, 'mine' => 3, 'location' => 'Head Office', 'route' => 'Kansanga Branch', 'by' => 'Grace']);

    expect($history->summary())->toMatchArray(['boxes' => 2, 'arrived' => 5, 'dispatched' => 5, 'delivered' => 2, 'missing' => 1]);
});

it('filters records by date', function () {
    StockScan::query()->where('box_id', $this->toNtinda->id)->update(['created_at' => now()->subMonths(2)]);

    $history = new ProductBoxHistory((string) $this->shampoo->id, today()->subDays(7), today());

    expect($history->boxes()->total())->toBe(1)
        ->and($history->summary()['arrived'])->toBe(3);
});

it('only shows a branch admin the boxes that passed through their branch', function () {
    $admin = User::factory()->create()->assignRole('branch-admin');
    $admin->warehouses()->sync([$this->kansanga->id]);
    $this->actingAs($admin);

    $history = new ProductBoxHistory((string) $this->shampoo->id, today()->subDay(), today());

    expect(collect($history->boxes()->items())->pluck('id')->all())->toBe([$this->mixed->id]);
});

it('renders the page for a picked product', function () {
    Livewire::withQueryParams(['product' => $this->shampoo->id])
        ->test(ProductAnalytics::class)
        ->assertOk()
        ->assertSee([$this->mixed->code, $this->toNtinda->code, 'Box records', 'Missing on arrival', '2 × Gel Polish Red'])
        ->call('range', 'last_month')
        ->assertDontSee($this->mixed->code);
});

it('sends a few units out of a received box and shows it in the box history', function () {
    $service = app(StockTrackingService::class);
    [$box, $items] = $service->receiveBoxedDelivery($this->hq->id, [$this->shampoo->id => 10]);

    // Only three units leave; the box stays at Head Office, opened, with the other seven.
    $transfer = $service->dispatch($this->hq->id, $this->ntinda->id, $items->take(3)->pluck('id')->all());
    $service->receive($transfer, $items->take(3)->pluck('id')->all());

    $box->refresh();
    expect($box->status)->toBe(App\Enums\BoxStatus::OPENED)
        ->and($box->warehouse_id)->toBe($this->hq->id)
        ->and($box->items()->count())->toBe(7)
        ->and($items->take(3)->every(fn ($i) => $i->fresh()->warehouse_id === $this->ntinda->id && $i->fresh()->box_id === null))->toBeTrue();

    $history = new ProductBoxHistory((string) $this->shampoo->id, today(), today());
    $d = $history->details([$box])[$box->id];
    expect($d['events']->firstWhere('action', ScanAction::DISPATCHED))->toMatchArray(['units' => 3, 'route' => 'Ntinda Branch', 'note' => 'Taken out of the box'])
        ->and($d['units']->where('inside', true)->count())->toBe(7);
});

it('shows items that did not come in a box, with their records', function () {
    $service = app(StockTrackingService::class);
    $this->polish->update(['is_refillable' => true]);
    $loose = $service->receiveArrival($this->polish, $this->hq->id, 2);
    $service->receive($service->dispatch($this->hq->id, $this->kansanga->id, $loose->pluck('id')->all()), $loose->pluck('id')->all());
    $service->serveClient($loose->first()->fresh(), 3);

    $history = new ProductBoxHistory((string) $this->polish->id, today(), today());
    expect($history->looseUnits()->total())->toBe(2);

    $d = $history->looseDetails($history->looseUnits()->items())[$loose->first()->id];
    expect($d['clients_all'])->toBe(3)
        ->and($d['events']->pluck('action')->all())->toContain(ScanAction::RECEIVED_STOCK, ScanAction::DISPATCHED, ScanAction::RECEIVED_TRANSFER, ScanAction::SERVED_CLIENT);

    // Polish also came in the mixed box, so boxes show first; the loose tab lists the two loose units.
    Livewire::withQueryParams(['product' => $this->polish->id])->test(ProductAnalytics::class)
        ->assertSee('Box records')
        ->set('section', 'loose')
        ->assertSee([$loose->first()->code, $loose->last()->code, 'Item records', '3 clients served']);
});

it('opens on loose items for a product that never came in a box', function () {
    $drum = Product::factory()->create(['name' => 'Conditioner 5L', 'category_id' => $this->shampoo->category_id]);
    $items = app(StockTrackingService::class)->receiveArrival($drum, $this->hq->id, 2);

    Livewire::withQueryParams(['product' => $drum->id])->test(ProductAnalytics::class)
        ->assertSee([$items->first()->code, 'Item records'])
        ->assertDontSee('Box records');
});
