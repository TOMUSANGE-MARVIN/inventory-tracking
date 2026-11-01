<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\Barcode;
use Illuminate\Database\Seeder;

/**
 * Starting catalogue for Sparkle Salons: the categories a Ugandan salon stocks,
 * and a few products to begin with. Products start with no stock; stock is
 * added through "Receive new stock" so every unit gets its own label.
 * Safe to re-run: existing categories and products (matched by name) are kept.
 */
class SparkleCatalogueSeeder extends Seeder
{
    private const CATEGORIES = [
        'HAIRCARE' => 'Hair Care',
        'RELAXERS' => 'Relaxers & Treatments',
        'BRAIDS' => 'Braids & Extensions',
        'STYLING' => 'Hair Styling',
        'NAILS' => 'Nails',
        'SKINCARE' => 'Skin Care & Facials',
        'MAKEUP' => 'Makeup',
        'BARBERING' => 'Barbering',
        'CONSUMABLES' => 'Salon Consumables',
    ];

    /** [category code, name, unit, selling price, cost, re-order level, refillable] – prices in UGX */
    private const PRODUCTS = [
        ['HAIRCARE', 'Professional Shampoo 5L', 'jerrycan', 95000, 75000, 2, true],
        ['RELAXERS', 'Hair Relaxer 1.8kg', 'tub', 70000, 55000, 3, false],
        ['BRAIDS', 'Pre-stretched Braiding Hair', 'pack', 12000, 8000, 20, false],
        ['NAILS', 'Gel Nail Polish 15ml', 'bottle', 25000, 15000, 10, false],
        ['NAILS', 'Acetone Nail Polish Remover 1L', 'bottle', 20000, 14000, 3, true],
    ];

    public function run(ProductService $products): void
    {
        $categories = collect(self::CATEGORIES)->map(fn (string $name, string $code) => Category::query()->firstOrCreate(['code' => $code], ['name' => $name]));

        foreach (self::PRODUCTS as [$category, $name, $unit, $price, $cost, $alert, $refillable]) {
            if (Product::query()->where('name', $name)->exists()) {
                continue;
            }

            // No supplier barcode known yet: use a Sparkle catalogue code (SPK-0001…). It can be
            // replaced with the maker's barcode later by scanning it on the product page.
            $code = Barcode::nextCatalogueCode();

            $products->create([
                'name' => $name,
                'code' => $code,
                'barcode_symbology' => Barcode::detect($code),
                'category_id' => $categories[$category]->id,
                'unit' => $unit,
                'price' => $price,
                'cost' => $cost,
                'is_refillable' => $refillable,
            ], $alert);
        }

        $this->command?->info(count(self::CATEGORIES) . ' categories, ' . Product::query()->count() . ' products.');
    }
}
