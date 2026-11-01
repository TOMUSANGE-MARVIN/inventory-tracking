<?php

declare(strict_types=1);

use App\Support\AccessRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workers sign in only to scan items at their station (e.g. "Hair chair 2").
 * Each scan keeps the station it was made at, so usage reports survive a
 * worker moving stations later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->string('station', 60)->nullable()->after('phone');
        });

        Schema::table('stock_scans', function (Blueprint $blueprint): void {
            $blueprint->string('station', 60)->nullable()->after('user_id');
        });

        AccessRoles::ensure();
    }

    public function down(): void
    {
        Schema::table('stock_scans', fn (Blueprint $b) => $b->dropColumn('station'));
        Schema::table('users', fn (Blueprint $b) => $b->dropColumn('station'));
    }
};
