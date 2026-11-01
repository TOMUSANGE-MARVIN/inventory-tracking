<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

/**
 * Which locations the signed-in user may see. Super admins (role "admin") see
 * every location; branch admins only the branches assigned to them.
 */
class Branches
{
    public static function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    /** @return array<int>|null warehouse ids, or null for "all locations" */
    public static function ids(): ?array
    {
        $user = Auth::user();

        if (! $user || self::isSuperAdmin()) {
            return null;
        }

        return $user->warehouses()->pluck('warehouses.id')->map(fn ($id) => (int) $id)->all();
    }

    public static function allows(?int $warehouseId): bool
    {
        $ids = self::ids();

        return $ids === null || ($warehouseId !== null && in_array($warehouseId, $ids, true));
    }

    public static function authorize(?int $warehouseId): void
    {
        abort_unless(self::allows($warehouseId), 403, __('This location is not one of your branches.'));
    }

    /** Limit a query to the user's locations on the given column. */
    public static function scope(Builder|QueryBuilder|Relation $query, string $column = 'warehouse_id'): Builder|QueryBuilder|Relation
    {
        $ids = self::ids();

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    /**
     * Who may scan in and confirm a delivery: a branch's deliveries are received
     * by that branch's own staff, so the branch itself confirms what arrived.
     * Super admins receive at locations without an active branch admin
     * (Head Office, or a branch that has no admin yet).
     */
    public static function canReceiveAt(?int $warehouseId): bool
    {
        $user = Auth::user();

        if (! $user || $warehouseId === null) {
            return false;
        }

        if (! self::isSuperAdmin()) {
            return in_array($warehouseId, self::ids() ?? [], true);
        }

        return ! \App\Models\User::role('branch-admin')
            ->where('status', 1)
            ->whereHas('warehouses', fn ($q) => $q->where('warehouses.id', $warehouseId))
            ->exists();
    }

    /** Transfers that start or end at one of the user's locations. */
    public static function scopeTransfers(Builder $query): Builder
    {
        $ids = self::ids();

        return $ids === null ? $query : $query->where(fn ($q) => $q
            ->whereIn('from_warehouse_id', $ids)
            ->orWhereIn('to_warehouse_id', $ids));
    }

    /** Locations the user may pick from (e.g. "receive at", "dispatch from"). */
    public static function warehouses()
    {
        return self::scope(Warehouse::query()->orderBy('id'), 'id')->get(['id', 'name']);
    }
}
