<?php

declare(strict_types=1);

use App\Enums\ItemStatus;
use App\Livewire\Pages\Auth\Login;
use App\Livewire\Team\Index as Team;
use App\Livewire\Tracking\Arrivals;
use App\Livewire\Tracking\Dispatch;
use App\Livewire\Tracking\Lookup;
use App\Livewire\Tracking\Overview;
use App\Livewire\Tracking\Receive;
use App\Livewire\Tracking\Station;
use App\Livewire\Tracking\Transfers;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTrackingService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->hq = Warehouse::factory()->create(['name' => 'Head Office']);
    $this->kansanga = Warehouse::factory()->create(['name' => 'Kansanga Branch']);
    $this->ntinda = Warehouse::factory()->create(['name' => 'Ntinda Branch']);

    $this->super = User::factory()->create()->assignRole('admin');
    $this->kansangaAdmin = User::factory()->create()->assignRole('branch-admin');
    $this->kansangaAdmin->warehouses()->sync([$this->kansanga->id]);

    $product = Product::factory()->create(['code' => '6001067004516', 'category_id' => Category::factory()->create()->id]);
    ProductWarehouse::query()->where('product_id', $product->id)->delete();

    // Head Office receives 4 units and sends 2 to each branch.
    $this->actingAs($this->super);
    $service = app(StockTrackingService::class);
    $items = $service->receiveArrival($product, $this->hq->id, 4);
    $this->toKansanga = $service->dispatch($this->hq->id, $this->kansanga->id, $items->take(2)->pluck('id')->all());
    $this->toNtinda = $service->dispatch($this->hq->id, $this->ntinda->id, $items->skip(2)->pluck('id')->all());
    $service->receive($this->toKansanga, $this->toKansanga->items()->pluck('stock_items.id')->all());
    $service->receive($this->toNtinda, $this->toNtinda->items()->pluck('stock_items.id')->all());

    $this->kansangaItem = StockItem::query()->where('warehouse_id', $this->kansanga->id)->first();
    $this->ntindaItem = StockItem::query()->where('warehouse_id', $this->ntinda->id)->first();
});

it('lets a branch admin into tracking but not into admin areas', function () {
    $this->actingAs($this->kansangaAdmin);

    $this->get(route('dashboard'))->assertOk()->assertSee('Stock at Kansanga Branch');
    $this->get(route('tracking.transfers'))->assertOk();
    $this->get(route('team.index'))->assertForbidden();
    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('pos.index'))->assertForbidden();
});

it('shows a branch admin only their branch on the dashboard', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(Overview::class)
        ->assertViewHas('warehouses', fn ($w) => $w->pluck('id')->all() === [$this->kansanga->id])
        ->assertViewHas('tiles', fn ($tiles) => $tiles[0]['value'] === 2);

    $this->actingAs($this->super);
    Livewire::test(Overview::class)->assertViewHas('warehouses', fn ($w) => $w->count() === Warehouse::query()->count());
});

it('lists only transfers that touch the branch', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(Transfers::class)
        ->assertViewHas('transfers', fn ($t) => $t->pluck('id')->all() === [$this->toKansanga->id]);
});

it('blocks opening another branch\'s transfer', function () {
    $this->actingAs($this->kansangaAdmin);

    $this->get(route('tracking.receive', $this->toNtinda))->assertForbidden();
    $this->get(route('tracking.receive', $this->toKansanga))->assertOk();
});

it('only lets the receiving branch confirm a delivery', function () {
    $this->actingAs($this->super);
    $service = app(StockTrackingService::class);
    $extra = $service->receiveArrival(Product::query()->first(), $this->hq->id, 1);
    $toNtinda = $service->dispatch($this->hq->id, $this->ntinda->id, $extra->pluck('id')->all());
    $ntindaAdmin = User::factory()->create()->assignRole('branch-admin');
    $ntindaAdmin->warehouses()->sync([$this->ntinda->id]);

    // Head Office staff can view it but only Ntinda scans it in.
    $this->actingAs($this->kansangaAdmin);
    $this->get(route('tracking.receive', $toNtinda))->assertForbidden();

    $this->actingAs($ntindaAdmin);
    Livewire::test(Receive::class, ['transfer' => $toNtinda])->assertViewHas('open', true);
});

it('refuses station scans for items at another location', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(Station::class)->set('action', 'in_use')->call('scanned', $this->ntindaItem->code)->assertSet('flash.type', 'error');
    expect($this->ntindaItem->fresh()->status)->toBe(ItemStatus::IN_STOCK);

    Livewire::test(Station::class)->set('action', 'in_use')->call('scanned', $this->kansangaItem->code)->assertSet('flash.type', 'success');
});

it('hides other branches\' items from lookup', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(Lookup::class)->call('scanned', $this->ntindaItem->code)->assertSet('code', '')->assertSet('flash.type', 'error');
    Livewire::test(Lookup::class)->call('scanned', $this->kansangaItem->code)->assertSet('code', $this->kansangaItem->code);
});

it('only dispatches and receives stock at the branch\'s own location', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(Dispatch::class)->assertSet('fromWarehouseId', $this->kansanga->id)->set('fromWarehouseId', $this->hq->id)->assertForbidden();
    Livewire::test(Arrivals::class)->assertSet('warehouseId', $this->kansanga->id)->set('warehouseId', $this->hq->id)
        ->call('scanned', '6001067004516')->call('save')->assertForbidden();
});

it('lets the super admin add a branch admin tied to one branch', function () {
    $this->actingAs($this->super);

    Livewire::test(Team::class)
        ->call('create')
        ->set('name', 'Grace')->set('email', 'grace@sparkle.test')->set('userRole', 'branch-admin')
        ->set('password', 'secret-pass')
        ->call('save')->assertHasErrors('branchId')
        ->set('branchId', $this->ntinda->id)
        ->call('save')->assertHasNoErrors();

    $grace = User::query()->where('email', 'grace@sparkle.test')->sole();
    expect($grace->hasRole('branch-admin'))->toBeTrue()
        ->and($grace->warehouses()->pluck('warehouses.id')->all())->toBe([$this->ntinda->id]);
});

it('does not let the super admin remove their own access', function () {
    $this->actingAs($this->super);

    Livewire::test(Team::class)->call('edit', $this->super->id)->set('userRole', 'branch-admin')->set('branchId', $this->kansanga->id)
        ->call('save')->assertHasErrors('userRole');

    expect($this->super->fresh()->hasRole('admin'))->toBeTrue();
});

it('keeps branch admins out of the team page actions', function () {
    $this->actingAs($this->kansangaAdmin);

    Livewire::test(Team::class)->assertForbidden();
});

it('stops deactivated people from signing in', function () {
    $user = User::factory()->create(['email' => 'off@sparkle.test', 'password' => Hash::make('secret-pass'), 'status' => 0]);
    $user->assignRole('branch-admin');
    auth()->logout();

    Livewire::test(Login::class)->set('form.email', 'off@sparkle.test')->set('form.password', 'secret-pass')
        ->call('login')->assertHasErrors('form.email');

    $this->assertGuest();
});
