<?php

declare(strict_types=1);

use App\Enums\ItemStatus;
use App\Models\Category;
use App\Models\ExternalStockLevel;
use App\Models\IntegrationEvent;
use App\Models\IntegrationLog;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\PurchaseOrder;
use App\Models\Service;
use App\Models\StockItem;
use App\Models\TransferRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Integration\Outbox;
use App\Services\StockTrackingService;
use App\Support\AccessRoles;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config(['integration.token' => 'test-token']);
    $this->withToken('test-token');
});

/** Send a batch to an integration endpoint. */
function sync(string $endpoint, array $records)
{
    return test()->putJson("/api/integration/v1/{$endpoint}", ['data' => $records]);
}

function seedBranches(): void
{
    sync('branches', [
        ['id' => 1, 'name' => 'Head Office', 'is_head_office' => true],
        ['id' => 2, 'name' => 'Kansanga'],
    ])->assertOk();
}

function seedProducts(): void
{
    sync('products', [
        ['id' => 45, 'name' => 'Shampoo 1L', 'category' => 'Hair care', 'unit' => 'bottle', 'grams_per_unit' => 1000],
        ['id' => 46, 'name' => 'Conditioner 500ml', 'category' => 'Hair care', 'is_refillable' => true],
    ])->assertOk();
}

// --- Access -----------------------------------------------------------------

it('rejects calls without the right token and logs them', function () {
    $this->withToken('wrong')->getJson('/api/integration/v1/ping')->assertUnauthorized();
    $this->withHeaders(['Authorization' => ''])->getJson('/api/integration/v1/ping')->assertUnauthorized();

    expect(IntegrationLog::query()->where('status', 401)->count())->toBe(2);
});

it('is switched off until a token is configured', function () {
    config(['integration.token' => '']);

    $this->getJson('/api/integration/v1/ping')->assertStatus(503);
});

it('answers ping and logs every call with its record count', function () {
    $this->getJson('/api/integration/v1/ping')->assertOk()->assertJson(['ok' => true, 'api_version' => 'v1']);
    seedBranches();

    $log = IntegrationLog::query()->latest('id')->first();
    expect($log->path)->toBe('api/integration/v1/branches')
        ->and($log->status)->toBe(200)
        ->and($log->records)->toBe(2);
});

// --- Records from the existing system --------------------------------------

it('creates and then updates branches by their id, keeping one head office', function () {
    seedBranches();

    sync('branches', [
        ['id' => 2, 'name' => 'Kansanga Branch', 'is_head_office' => true],
    ])->assertOk()->assertJson(['received' => 1, 'created' => 0, 'updated' => 1]);

    expect(Warehouse::query()->whereNotNull('external_id')->count())->toBe(2)
        ->and(Warehouse::query()->where('external_id', '2')->value('name'))->toBe('Kansanga Branch')
        ->and(Warehouse::query()->where('is_head_office', true)->pluck('external_id')->all())->toBe(['2'])
        ->and(app(StockTrackingService::class)->headOffice()->external_id)->toBe('2');
});

it('is safe to send the same batch twice', function () {
    seedBranches();
    seedProducts();
    sync('products', [['id' => 45, 'name' => 'Shampoo 1L', 'grams_per_unit' => 1000]])
        ->assertJson(['created' => 0, 'updated' => 1]);

    expect(Product::query()->whereNotNull('external_id')->count())->toBe(2);
});

it('refuses a batch with the same record twice', function () {
    sync('branches', [
        ['id' => 1, 'name' => 'A'],
        ['id' => '1', 'name' => 'B'],
    ])->assertUnprocessable();

    expect(Warehouse::query()->whereNotNull('external_id')->count())->toBe(0);
});

it('stores products with grams, category and refillable flag', function () {
    seedProducts();

    $shampoo = Product::query()->where('external_id', '45')->first();
    $conditioner = Product::query()->where('external_id', '46')->first();

    expect((float) $shampoo->grams_per_unit)->toBe(1000.0)
        ->and($shampoo->unit)->toBe('bottle')
        ->and($shampoo->category->name)->toBe('Hair care')
        ->and($conditioner->is_refillable)->toBeTrue()
        ->and(Category::query()->where('name', 'Hair care')->count())->toBe(1);

    // Fields not sent are left as they were.
    sync('products', [['id' => 46, 'name' => 'Conditioner 500ml']])->assertOk();
    expect($conditioner->fresh()->is_refillable)->toBeTrue();
});

it('stores users with their branches, and maps roles only when configured', function () {
    AccessRoles::ensure();
    config(['integration.roles' => ['Branch Manager' => 'branch-admin']]);
    seedBranches();

    sync('users', [
        ['id' => 'u-7', 'name' => 'Sarah', 'email' => 'Sarah@Example.com', 'role' => 'Branch Manager', 'branch_ids' => [2]],
        ['id' => 'u-8', 'name' => 'Joan', 'role' => 'Receptionist'],
    ])->assertOk()->assertJson(['created' => 2]);

    $sarah = User::query()->where('external_id', 'u-7')->first();
    $joan = User::query()->where('external_id', 'u-8')->first();

    expect($sarah->email)->toBe('sarah@example.com')
        ->and($sarah->hasRole('branch-admin'))->toBeTrue()
        ->and($sarah->warehouses()->pluck('external_id')->all())->toBe(['2'])
        ->and($joan->email)->toBe('user-u-8@external.invalid')
        ->and($joan->external_role)->toBe('Receptionist')
        ->and($joan->roles()->count())->toBe(0);
});

it('refuses a user whose email belongs to someone else', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    sync('users', [['id' => 9, 'name' => 'X', 'email' => 'taken@example.com']])
        ->assertUnprocessable();
});

it('names the endpoint to call when a referenced record is unknown, and saves nothing', function () {
    seedProducts();

    sync('services', [
        ['id' => 1, 'name' => 'Wash', 'products' => [['product_id' => 45, 'grams' => 20]]],
        ['id' => 2, 'name' => 'Treatment', 'products' => [['product_id' => 999, 'grams' => 15]]],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.product_id.0', 'Unknown product id(s): 999. Send them to /products first.');

    expect(Service::query()->count())->toBe(0);
});

it('stores services with the grams of each product they use', function () {
    seedProducts();

    sync('services', [
        ['id' => 'S1', 'name' => 'Wash & Treatment', 'products' => [
            ['product_id' => 45, 'grams' => 20],
            ['product_id' => 46, 'grams' => 15],
        ]],
    ])->assertOk();

    // Sending it again with fewer products replaces the list.
    sync('services', [
        ['id' => 'S1', 'name' => 'Wash & Treatment', 'products' => [['product_id' => 45, 'grams' => 25]]],
    ])->assertOk();

    $service = Service::query()->where('external_id', 'S1')->first();
    expect($service->products)->toHaveCount(1)
        ->and((float) $service->products->first()->pivot->grams)->toBe(25.0);
});

it('stores stock levels, purchase orders and requisitions', function () {
    seedBranches();
    seedProducts();

    sync('stock-levels', [
        ['branch_id' => 2, 'product_id' => 45, 'quantity' => 12, 'grams' => 12000],
    ])->assertOk();
    sync('stock-levels', [
        ['branch_id' => 2, 'product_id' => 45, 'quantity' => 11, 'grams' => 11000],
    ])->assertJson(['created' => 0, 'updated' => 1]);

    sync('purchase-orders', [
        ['id' => 'PO-100', 'reference' => 'PO-100', 'supplier_name' => 'Beauty Supplies Ltd', 'branch_id' => 1, 'status' => 'approved',
            'lines' => [['product_id' => 45, 'quantity' => 24], ['product_id' => 46, 'quantity' => 6]]],
    ])->assertOk();

    sync('requisitions', [
        ['id' => 'RQ-5', 'from_branch_id' => 1, 'to_branch_id' => 2, 'status' => 'approved',
            'lines' => [['product_id' => 45, 'quantity' => 4]]],
    ])->assertOk();

    sync('requisitions', [
        ['id' => 'RQ-6', 'from_branch_id' => 2, 'to_branch_id' => 2, 'lines' => [['product_id' => 45, 'quantity' => 1]]],
    ])->assertUnprocessable();

    expect((float) ExternalStockLevel::query()->sole()->quantity)->toBe(11.0)
        ->and(PurchaseOrder::query()->sole()->lines)->toHaveCount(2)
        ->and(TransferRequest::query()->sole()->toWarehouse->external_id)->toBe('2');
});

// --- Updates feed -------------------------------------------------------------

it('publishes dispatches and receipts with the existing system ids, including missing items', function () {
    seedBranches();
    seedProducts();
    Role::findOrCreate('admin');
    $this->actingAs(User::factory()->create(['external_id' => 'u-1'])->assignRole('admin'));

    $hq = Warehouse::query()->where('external_id', '1')->first();
    $branch = Warehouse::query()->where('external_id', '2')->first();
    $shampoo = Product::query()->where('external_id', '45')->first();
    ProductWarehouse::query()->delete();

    $tracking = app(StockTrackingService::class);
    $items = $tracking->receiveArrival($shampoo, $hq->id, 3);
    $transfer = $tracking->dispatch($hq->id, $branch->id, $items->pluck('id')->all());
    $tracking->receive($transfer->fresh(), $items->take(2)->pluck('id')->all());

    $feed = $this->getJson('/api/integration/v1/updates')->assertOk()->json();

    expect(collect($feed['data'])->pluck('type')->all())->toBe([Outbox::TRANSFER_DISPATCHED, Outbox::TRANSFER_RECEIVED]);

    [$dispatched, $received] = collect($feed['data'])->pluck('data')->all();
    $codes = $items->pluck('code')->sort()->values()->all();

    expect($dispatched)->toMatchArray([
        'transfer_id' => $transfer->id,
        'from_branch_id' => '1',
        'to_branch_id' => '2',
        'dispatched_by' => 'u-1',
        'lines' => [['product_id' => '45', 'quantity' => 3, 'item_codes' => $codes]],
        'empty_containers' => [],
    ])->and($received['complete'])->toBeFalse()
        ->and($received['lines'][0]['quantity'])->toBe(2)
        ->and($received['missing'][0])->toBe(['product_id' => '45', 'quantity' => 1, 'item_codes' => [$items->last()->code]]);
});

it('publishes finished, refilled and cancelled items', function () {
    seedBranches();
    seedProducts();
    $this->actingAs(User::factory()->create());

    $hq = Warehouse::query()->where('external_id', '1')->first();
    $branch = Warehouse::query()->where('external_id', '2')->first();
    $conditioner = Product::query()->where('external_id', '46')->first();
    $tracking = app(StockTrackingService::class);

    [$a, $b] = $tracking->receiveArrival($conditioner, $hq->id, 2)->all();
    $tracking->serveClient($a, 3);
    $tracking->finish($a->fresh());
    $tracking->refill($a->fresh());
    $tracking->serveClient($a->fresh(), 2);
    $tracking->finish($a->fresh());

    $transfer = $tracking->dispatch($hq->id, $branch->id, [$b->id]);
    $tracking->cancelTransfer($transfer, 'Wrong branch');

    $events = IntegrationEvent::query()->orderBy('id')->get();

    expect($events->pluck('type')->all())->toBe([
        Outbox::ITEM_NEEDS_REFILL, Outbox::ITEM_REFILLED, Outbox::ITEM_NEEDS_REFILL,
        Outbox::TRANSFER_DISPATCHED, Outbox::TRANSFER_CANCELLED,
    ])
        ->and($events[0]->payload)->toMatchArray(['item_code' => $a->code, 'product_id' => '46', 'branch_id' => '1', 'customers_served' => 3])
        ->and($events[1]->payload['refill_count'])->toBe(1)
        // Customers are counted from the latest refill, not over the item's whole life.
        ->and($events[2]->payload['customers_served'])->toBe(2)
        ->and($events[4]->payload['reason'])->toBe('Wrong branch')
        ->and(StockItem::query()->find($b->id)->status)->toBe(ItemStatus::IN_STOCK);
});

it('pages through updates with after and limit', function () {
    foreach (range(1, 5) as $i) {
        IntegrationEvent::query()->create(['type' => 'item.refilled', 'payload' => ['n' => $i], 'occurred_at' => now()]);
    }
    $ids = IntegrationEvent::query()->orderBy('id')->pluck('id');

    $first = $this->getJson('/api/integration/v1/updates?limit=2')->assertOk();
    $first->assertJson(['next_after' => $ids[1], 'has_more' => true]);
    expect(collect($first->json('data'))->pluck('id')->all())->toBe([$ids[0], $ids[1]]);

    $last = $this->getJson('/api/integration/v1/updates?after=' . $ids[3] . '&limit=2')->assertOk();
    $last->assertJson(['next_after' => $ids[4], 'has_more' => false]);

    // Nothing new: the cursor stays where it was.
    $this->getJson('/api/integration/v1/updates?after=' . $ids[4])
        ->assertJson(['data' => [], 'next_after' => $ids[4], 'has_more' => false]);
});
