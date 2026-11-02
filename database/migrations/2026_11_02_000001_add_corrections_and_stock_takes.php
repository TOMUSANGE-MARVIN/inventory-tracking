<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections (undoing a mistaken delivery entry, cancelling a dispatch) are
 * logged with who, when and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_corrections', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('type', 32); // undo_delivery, cancel_transfer
            $blueprint->uuid('user_id')->nullable();
            $blueprint->unsignedBigInteger('warehouse_id')->nullable();
            $blueprint->string('reference')->nullable(); // box code or transfer reference
            $blueprint->string('reason', 500);
            $blueprint->json('details')->nullable();
            $blueprint->timestamps();

            $blueprint->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_corrections');
    }
};
