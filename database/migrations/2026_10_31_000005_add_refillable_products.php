<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refillable products (jerrycans, refill bottles): when an item is used up it
 * waits for a refill instead of being thrown away, then goes back into stock
 * under the same barcode. refill_count is how many times it has been refilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $blueprint): void {
            $blueprint->boolean('is_refillable')->default(false)->after('unit');
        });

        Schema::table('stock_items', function (Blueprint $blueprint): void {
            $blueprint->unsignedInteger('refill_count')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', fn (Blueprint $b) => $b->dropColumn('refill_count'));
        Schema::table('products', fn (Blueprint $b) => $b->dropColumn('is_refillable'));
    }
};
