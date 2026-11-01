<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Branch stock now counts items until they are used to completion: sealed and
 * in-use items both count. Recount the stock levels of every product tracked
 * with item barcodes from its items.
 */
return new class extends Migration
{
    public function up(): void
    {
        $counts = DB::table('stock_items')
            ->whereIn('status', ['in_stock', 'in_use'])
            ->whereNotNull('warehouse_id')
            ->selectRaw('product_id, warehouse_id, COUNT(*) as n')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        $tracked = DB::table('stock_items')->distinct()->pluck('product_id');

        foreach ($tracked as $productId) {
            DB::table('product_warehouse')->where('product_id', $productId)->update(['qty' => 0]);
        }

        foreach ($counts as $row) {
            DB::table('product_warehouse')
                ->where('product_id', $row->product_id)
                ->where('warehouse_id', $row->warehouse_id)
                ->update(['qty' => $row->n]);
        }

        foreach ($tracked as $productId) {
            DB::table('products')->where('id', $productId)
                ->update(['quantity' => (int) $counts->where('product_id', $productId)->sum('n')]);
        }
    }

    public function down(): void
    {
        // Stock levels are recounted from items; nothing to restore.
    }
};
