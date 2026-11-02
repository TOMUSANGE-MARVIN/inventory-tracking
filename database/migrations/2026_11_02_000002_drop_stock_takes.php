<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Stock-take was removed; drop its tables where an earlier migration created them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('stock_take_items');
        Schema::dropIfExists('stock_takes');
    }

    public function down(): void
    {
        // Not restored.
    }
};
