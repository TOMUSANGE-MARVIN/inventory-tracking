<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Create a product with zero stock at every location; stock arrives later
     * through "Receive new stock" so each unit is labelled.
     *
     * @param array{name:string, code:string, barcode_symbology:string, category_id:int, unit:string, price:float, cost:float} $attributes
     */
    public function create(array $attributes, int $stockAlert = 0): Product
    {
        return DB::transaction(function () use ($attributes, $stockAlert): Product {
            $product = Product::query()->create([
                ...$attributes,
                'slug' => Str::slug($attributes['name']) . '-' . Str::lower(Str::random(6)),
                'quantity' => 0,
                'status' => true,
                'availability' => true,
            ]);

            foreach (Warehouse::query()->pluck('id') as $warehouseId) {
                ProductWarehouse::query()->create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'qty' => 0,
                    'price' => $attributes['price'],
                    'cost' => $attributes['cost'],
                    'stock_alert' => $stockAlert,
                ]);
            }

            return $product;
        });
    }

    public function update(Product $product, array $attributes, int $stockAlert = 0): Product
    {
        return DB::transaction(function () use ($product, $attributes, $stockAlert): Product {
            if ($attributes['name'] !== $product->name) {
                $attributes['slug'] = Str::slug($attributes['name']) . '-' . Str::lower(Str::random(6));
            }

            $product->update($attributes);

            // Prices and the re-order level apply at every location.
            ProductWarehouse::query()->where('product_id', $product->id)->update([
                'price' => $attributes['price'],
                'cost' => $attributes['cost'],
                'stock_alert' => $stockAlert,
            ]);

            return $product;
        });
    }
}
