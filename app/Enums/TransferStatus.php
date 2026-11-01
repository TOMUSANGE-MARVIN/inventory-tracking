<?php

declare(strict_types=1);

namespace App\Enums;

enum TransferStatus: int
{
    case IN_TRANSIT = 1;
    case RECEIVED = 2;
    case DISCREPANCY = 3;
    case CANCELLED = 4;

    public function label(): string
    {
        return match ($this) {
            self::IN_TRANSIT => __('In transit'),
            self::RECEIVED => __('Received'),
            self::DISCREPANCY => __('Received with discrepancy'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::IN_TRANSIT => 'bg-amber-100 text-amber-800',
            self::RECEIVED => 'bg-green-100 text-green-800',
            self::DISCREPANCY => 'bg-red-100 text-red-800',
            self::CANCELLED => 'bg-gray-200 text-gray-700',
        };
    }
}
