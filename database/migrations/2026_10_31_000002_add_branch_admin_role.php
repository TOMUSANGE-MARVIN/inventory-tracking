<?php

declare(strict_types=1);

use App\Support\AccessRoles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        AccessRoles::ensure();
    }

    public function down(): void
    {
        Role::query()->where('name', 'branch-admin')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
