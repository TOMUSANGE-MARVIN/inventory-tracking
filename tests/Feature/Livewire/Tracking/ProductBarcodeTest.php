<?php

declare(strict_types=1);

use App\Livewire\Products\Create;
use App\Livewire\Products\Edit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create()->assignRole('admin'));
    $this->category = Category::factory()->create();
});

function newProduct(array $fields = [])
{
    return Livewire::test(Create::class)->call('openModal')
        ->set('form.name', $fields['name'] ?? 'Argan Oil 100ml')
        ->set('form.code', $fields['code'] ?? '6001067004516')
        ->set('form.category_id', test()->category->id)
        ->set('form.unit', 'bottle')
        ->set('form.price', $fields['price'] ?? 45000)
        ->set('form.cost', 30000)
        ->set('form.stock_alert', 3)
        ->call('create');
}

it('creates a product from the basic fields', function () {
    newProduct()->assertHasNoErrors()->assertSet('createModal', false);

    $product = Product::query()->where('code', '6001067004516')->sole();
    expect($product->name)->toBe('Argan Oil 100ml')
        ->and((float) $product->price)->toBe(45000.0)
        ->and((float) $product->cost)->toBe(30000.0)
        ->and($product->barcode_symbology)->toBe('EAN13')
        ->and($product->slug)->not->toBeEmpty()
        // Zero stock everywhere: stock only comes in through Receive new stock.
        ->and(ProductWarehouse::query()->where('product_id', $product->id)->count())->toBe(Warehouse::query()->count())
        ->and((int) ProductWarehouse::query()->where('product_id', $product->id)->sum('qty'))->toBe(0)
        ->and((int) ProductWarehouse::query()->where('product_id', $product->id)->max('stock_alert'))->toBe(3);
});

it('accepts large UGX prices', function () {
    newProduct(['price' => 4200000])->assertHasNoErrors();

    expect((float) Product::query()->where('code', '6001067004516')->value('price'))->toBe(4200000.0);
});

it('requires the basics and a unique barcode', function () {
    Livewire::test(Create::class)->call('openModal')->call('create')
        ->assertHasErrors(['form.name', 'form.code', 'form.category_id', 'form.price']);

    newProduct();
    newProduct(['name' => 'Copy'])->assertHasErrors('form.code');
});

it('detects the barcode type when saving', function (string $code, string $type) {
    newProduct(['code' => $code]);

    expect(Product::query()->where('code', $code)->value('barcode_symbology'))->toBe($type);
})->with([
    ['6001067004516', 'EAN13'],
    ['96385074', 'EAN8'],
    ['036000291452', 'UPCA'],
    ['6001067004512', 'C128'], // bad check digit: not a real EAN, keep it as Code 128
    ['SPK-0042', 'C128'],
]);

it('trims what a USB scanner types', function () {
    Livewire::test(Create::class)->set('form.code', "  6001067004516\n")->assertSet('form.code', '6001067004516');
});

it('generates the next free catalogue code for products without a barcode', function () {
    Livewire::test(Create::class)->call('generateCode')->assertSet('form.code', fn ($code) => str_starts_with($code, 'SPK-'));
});

it('edits a product and its price everywhere', function () {
    newProduct();
    $product = Product::query()->where('code', '6001067004516')->sole();

    Livewire::test(Edit::class, ['id' => $product->id])
        ->assertSet('form.name', 'Argan Oil 100ml')->assertSet('form.stock_alert', 3)
        ->set('form.name', 'Argan Oil 200ml')->set('form.price', 60000)
        ->call('update')->assertHasNoErrors();

    expect($product->fresh()->name)->toBe('Argan Oil 200ml')
        ->and((float) ProductWarehouse::query()->where('product_id', $product->id)->min('price'))->toBe(60000.0);
});
