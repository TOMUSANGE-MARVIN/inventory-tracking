<?php

declare(strict_types=1);

namespace App\Enums;

enum ScanAction: string
{
    case RECEIVED_STOCK = 'received_stock';
    case DISPATCHED = 'dispatched';
    case RECEIVED_TRANSFER = 'received_transfer';
    case IN_USE = 'in_use';
    case SERVED_CLIENT = 'served_client';
    case DISPOSED = 'disposed';
    case RETURNED_FOR_REFILL = 'returned_for_refill';
    case MARKED_MISSING = 'marked_missing';
    case REFILLED = 'refilled';
    case DISPATCH_CANCELLED = 'dispatch_cancelled';
    case COUNTED = 'counted';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVED_STOCK => __('Arrived – new stock'),
            self::DISPATCHED => __('Dispatched'),
            self::RECEIVED_TRANSFER => __('Received from transfer'),
            self::IN_USE => __('Put into use'),
            self::SERVED_CLIENT => __('Client served'),
            self::DISPOSED => __('Finished – disposed'),
            self::RETURNED_FOR_REFILL => __('Empty – needs refill'),
            self::MARKED_MISSING => __('Missing on arrival'),
            self::REFILLED => __('Refilled'),
            self::DISPATCH_CANCELLED => __('Dispatch cancelled'),
            self::COUNTED => __('Counted at stock-take'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEIVED_STOCK, self::RECEIVED_TRANSFER => 'bg-green-100 text-green-800',
            self::DISPATCHED => 'bg-amber-100 text-amber-800',
            self::IN_USE => 'bg-blue-100 text-blue-800',
            self::SERVED_CLIENT => 'bg-teal-100 text-teal-800',
            self::DISPOSED => 'bg-gray-200 text-gray-800',
            self::RETURNED_FOR_REFILL => 'bg-purple-100 text-purple-800',
            self::MARKED_MISSING => 'bg-red-100 text-red-800',
            self::REFILLED => 'bg-cyan-100 text-cyan-800',
            self::DISPATCH_CANCELLED => 'bg-gray-200 text-gray-700',
            self::COUNTED => 'bg-indigo-100 text-indigo-800',
        };
    }

    /** Actions recorded on an item from the scan station (DISPOSED / RETURNED_FOR_REFILL come from "Item finished"). */
    public static function stationActions(): array
    {
        return [self::SERVED_CLIENT, self::IN_USE, self::DISPOSED, self::RETURNED_FOR_REFILL];
    }
}
