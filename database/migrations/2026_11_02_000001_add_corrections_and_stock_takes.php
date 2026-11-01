<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections (undoing a mistaken delivery entry, cancelling a dispatch) are
 * logged with who, when and why. Stock-takes compare what is physically at a
 * location with what the system expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_corrections', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('type', 32); // undo_delivery, cancel_transfer, stock_take
            $blueprint->uuid('user_id')->nullable();
            $blueprint->unsignedBigInteger('warehouse_id')->nullable();
            $blueprint->string('reference')->nullable(); // box code, transfer reference, stock-take number
            $blueprint->string('reason', 500);
            $blueprint->json('details')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['type', 'created_at']);
        });

        Schema::create('stock_takes', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('reference')->nullable()->unique(); // ST-000001
            $blueprint->unsignedBigInteger('warehouse_id');
            $blueprint->uuid('started_by')->nullable();
            $blueprint->uuid('finished_by')->nullable();
            $blueprint->string('status', 16)->default('open'); // open, finished, cancelled
            $blueprint->unsignedInteger('expected')->default(0);
            $blueprint->unsignedInteger('found')->default(0);
            $blueprint->unsignedInteger('not_found')->default(0);
            $blueprint->unsignedInteger('unexpected')->default(0);
            $blueprint->string('note', 500)->nullable();
            $blueprint->timestamp('finished_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('warehouse_id')->references('id')->on('warehouses');
            $blueprint->index(['warehouse_id', 'status']);
        });

        Schema::create('stock_take_items', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $blueprint->string('result', 16); // found, not_found, unexpected
            $blueprint->string('system_status', 16)->nullable(); // what the system said when counted
            $blueprint->unsignedBigInteger('system_warehouse_id')->nullable();
            $blueprint->timestamp('scanned_at')->nullable();
            $blueprint->timestamps();

            $blueprint->unique(['stock_take_id', 'stock_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
        Schema::dropIfExists('stock_corrections');
    }
};
