<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemStatus: string
{
    case IN_STOCK = 'in_stock';
    case IN_TRANSIT = 'in_transit';
    case IN_USE = 'in_use';
    case FINISHED_REFILL = 'finished_refill';
    case DISPOSED = 'disposed';
    case MISSING = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::IN_STOCK => __('In stock'),
            self::IN_TRANSIT => __('In transit'),
            self::IN_USE => __('In use'),
            self::FINISHED_REFILL => __('Needs refill'),
            self::DISPOSED => __('Disposed'),
            self::MISSING => __('Missing'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IN_STOCK => 'bg-green-100 text-green-800',
            self::IN_TRANSIT => 'bg-amber-100 text-amber-800',
            self::IN_USE => 'bg-blue-100 text-blue-800',
            self::FINISHED_REFILL => 'bg-purple-100 text-purple-800',
            self::DISPOSED => 'bg-gray-200 text-gray-800',
            self::MISSING => 'bg-red-100 text-red-800',
        };
    }

    /**
     * Stock a branch still holds: sealed items and items in use, until they are
     * used to completion (disposed or emptied for refill).
     *
     * @return array<self>
     */
    public static function countsAsStock(): array
    {
        return [self::IN_STOCK, self::IN_USE];
    }

    /** Statuses an item can be dispatched from: full stock, or an empty container going back for refill. */
    public function canDispatch(): bool
    {
        return in_array($this, [self::IN_STOCK, self::FINISHED_REFILL], true);
    }
}
