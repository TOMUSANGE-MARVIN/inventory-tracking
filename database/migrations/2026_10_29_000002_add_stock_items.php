<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per physical unit (bottle, tub, drum) with its own label code.
        Schema::create('stock_items', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('code')->nullable()->unique();
            $blueprint->uuid('product_id');
            $blueprint->unsignedBigInteger('warehouse_id')->nullable(); // current location; null while in transit or missing
            $blueprint->string('status', 24);
            $blueprint->uuid('transfer_id')->nullable(); // transfer it is travelling on
            $blueprint->string('batch')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $blueprint->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $blueprint->index(['product_id', 'warehouse_id', 'status']);
        });

        Schema::create('transfer_items', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->uuid('transfer_id');
            $blueprint->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $blueprint->boolean('from_stock')->default(true); // false for empty containers going back for refill
            $blueprint->timestamp('received_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('transfer_id')->references('id')->on('transfers')->cascadeOnDelete();
            $blueprint->unique(['transfer_id', 'stock_item_id']);
        });

        Schema::table('stock_scans', function (Blueprint $blueprint): void {
            $blueprint->foreignId('stock_item_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_scans', function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('stock_item_id');
        });

        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('stock_items');
    }
};
