<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;

class Barcode
{
    /** Pick the symbology for a scanned/typed code: retail codes by length and check digit, otherwise Code 128. */
    public static function detect(?string $code): string
    {
        $code = trim((string) $code);

        return match (true) {
            strlen($code) === 13 && self::validGtin($code) => 'EAN13',
            strlen($code) === 8 && self::validGtin($code) => 'EAN8',
            strlen($code) === 12 && self::validGtin($code) => 'UPCA',
            default => 'C128',
        };
    }

    /** Next free catalogue code for products that come without a barcode (SPK-0001, SPK-0002, …). */
    public static function nextCatalogueCode(): string
    {
        $last = Product::withTrashed()->where('code', 'like', 'SPK-%')->pluck('code')
            ->map(fn ($c) => (int) substr($c, 4))->max() ?? 0;

        return 'SPK-' . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    private static function validGtin(string $code): bool
    {
        if (! ctype_digit($code)) {
            return false;
        }

        $digits = array_map('intval', str_split($code));
        $check = array_pop($digits);
        $sum = 0;
        foreach (array_reverse($digits) as $i => $d) {
            $sum += $d * ($i % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === $check;
    }
}
