<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $blueprint): void {
            $blueprint->uuid('dispatched_by')->nullable()->after('status');
            $blueprint->timestamp('dispatched_at')->nullable()->after('dispatched_by');
            $blueprint->uuid('received_by')->nullable()->after('dispatched_at');
            $blueprint->timestamp('received_at')->nullable()->after('received_by');
        });

        Schema::table('transfer_details', function (Blueprint $blueprint): void {
            $blueprint->integer('received_quantity')->nullable()->after('quantity');
        });

        // One row per scan: the movement history of every product.
        Schema::create('stock_scans', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->uuid('product_id');
            $blueprint->unsignedBigInteger('warehouse_id');
            $blueprint->uuid('transfer_id')->nullable();
            $blueprint->string('action', 32);
            $blueprint->integer('quantity');
            $blueprint->string('code')->nullable();
            $blueprint->uuid('user_id')->nullable();
            $blueprint->string('note')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $blueprint->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $blueprint->index(['warehouse_id', 'created_at']);
            $blueprint->index('transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_scans');

        Schema::table('transfer_details', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('received_quantity');
        });

        Schema::table('transfers', function (Blueprint $blueprint): void {
            $blueprint->dropColumn(['dispatched_by', 'dispatched_at', 'received_by', 'received_at']);
        });
    }
};
