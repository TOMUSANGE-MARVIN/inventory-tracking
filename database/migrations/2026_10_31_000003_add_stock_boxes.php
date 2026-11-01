<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boxes (QR label BX-…) hold labelled units (barcode SP-…). Scanning a box moves
 * every unit still inside it; units that never came in a box have no box_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_boxes', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('code')->nullable()->unique();
            $blueprint->unsignedBigInteger('warehouse_id')->nullable(); // null while in transit
            $blueprint->string('status', 16); // packed, in_transit, opened, empty
            $blueprint->uuid('transfer_id')->nullable();
            $blueprint->string('batch')->nullable();
            $blueprint->string('note')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $blueprint->index(['warehouse_id', 'status']);
        });

        Schema::table('stock_items', function (Blueprint $blueprint): void {
            // The box the unit is currently inside; cleared when it is taken out.
            $blueprint->foreignId('box_id')->nullable()->after('product_id')->constrained('stock_boxes')->nullOnDelete();
        });

        Schema::table('stock_scans', function (Blueprint $blueprint): void {
            $blueprint->foreignId('box_id')->nullable()->after('stock_item_id')->constrained('stock_boxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_scans', fn (Blueprint $b) => $b->dropConstrainedForeignId('box_id'));
        Schema::table('stock_items', fn (Blueprint $b) => $b->dropConstrainedForeignId('box_id'));
        Schema::dropIfExists('stock_boxes');
    }
};
