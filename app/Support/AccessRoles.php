<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Two levels of access:
 *  - admin        : super admin at Head Office, sees and manages everything (Gate::before grants all).
 *  - branch-admin : runs one branch; stock tracking only, limited to their branch by App\Support\Branches.
 *  - worker       : works at a station in one branch; can only scan items at the scan station.
 */
class AccessRoles
{
    public const BRANCH_ADMIN_PERMISSIONS = [
        'transfer_access',   // dashboard, transfers, history, item lookup
        'transfer_create',   // dispatch (e.g. empty containers back to Head Office)
        'adjustment_create', // receive new stock, scan station
        'print_barcodes',    // reprint labels
        'show_notifications',
        'station_scan',
    ];

    public const WORKER_PERMISSIONS = [
        'station_scan', // scan station only: client served, opened, finished
    ];

    /** A worker (and nothing more): signs in to the scan station only. */
    public static function isWorkerOnly(?\App\Models\User $user): bool
    {
        return $user !== null && $user->hasRole('worker') && ! $user->hasAnyRole(['admin', 'branch-admin']);
    }

    /** Idempotent: safe to run from migrations and seeders. */
    public static function ensure(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::BRANCH_ADMIN_PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('branch-admin', 'web')->syncPermissions(self::BRANCH_ADMIN_PERMISSIONS);
        Role::findOrCreate('worker', 'web')->syncPermissions(self::WORKER_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
