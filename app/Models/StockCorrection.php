<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A logged correction: who undid or cancelled what, when, and why. */
class StockCorrection extends Model
{
    public const UNDO_DELIVERY = 'undo_delivery';

    public const CANCEL_TRANSFER = 'cancel_transfer';

    protected $fillable = ['type', 'user_id', 'warehouse_id', 'reference', 'reason', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function label(): string
    {
        return match ($this->type) {
            self::UNDO_DELIVERY => __('Delivery entry undone'),
            self::CANCEL_TRANSFER => __('Dispatch cancelled'),
            default => $this->type,
        };
    }
}
