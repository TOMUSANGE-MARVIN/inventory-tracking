<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    protected $fillable = [
        'code',
        'product_id',
        'box_id',
        'warehouse_id',
        'status',
        'refill_count',
        'transfer_id',
        'batch',
    ];

    protected function casts(): array
    {
        return [
            'status' => ItemStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(StockBox::class, 'box_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(StockScan::class);
    }

    /** Where the item is, in words: a location, or on its way to one. */
    public function whereabouts(): string
    {
        return match ($this->status) {
            ItemStatus::IN_TRANSIT => __('On the way to :name', ['name' => $this->transfer?->toWarehouse?->name]),
            ItemStatus::MISSING => __('Missing'),
            default => (string) $this->warehouse?->name,
        };
    }
}
