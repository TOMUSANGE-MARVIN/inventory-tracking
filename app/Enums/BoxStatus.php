<?php

declare(strict_types=1);

namespace App\Enums;

enum BoxStatus: string
{
    case PACKED = 'packed';
    case IN_TRANSIT = 'in_transit';
    case OPENED = 'opened';
    case EMPTY = 'empty';
    case MISSING = 'missing';

    public function label(): string
    {
        return match ($this) {
            self::PACKED => __('Packed'),
            self::IN_TRANSIT => __('In transit'),
            self::OPENED => __('Opened'),
            self::EMPTY => __('Empty'),
            self::MISSING => __('Missing'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PACKED => 'bg-green-100 text-green-800',
            self::IN_TRANSIT => 'bg-amber-100 text-amber-800',
            self::OPENED => 'bg-blue-100 text-blue-800',
            self::EMPTY => 'bg-gray-200 text-gray-800',
            self::MISSING => 'bg-red-100 text-red-800',
        };
    }
}
