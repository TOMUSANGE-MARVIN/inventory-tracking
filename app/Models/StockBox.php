<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BoxStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockBox extends Model
{
    protected $fillable = [
        'code',
        'warehouse_id',
        'status',
        'transfer_id',
        'batch',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => BoxStatus::class,
        ];
    }

    /** Units currently inside the box. */
    public function items(): HasMany
    {
        return $this->hasMany(StockItem::class, 'box_id');
    }

    public function scans(): HasMany
    {
        return $this->hasMany(StockScan::class, 'box_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /** "12 × Professional Shampoo 5L, 6 × Gel Nail Polish – Red" */
    public function summary(): string
    {
        return $this->items->loadMissing('product:id,name')->groupBy('product_id')
            ->map(fn ($units) => $units->count() . ' × ' . $units->first()->product?->name)
            ->join(', ');
    }

    public function whereabouts(): string
    {
        return $this->status === BoxStatus::IN_TRANSIT
            ? __('On the way to :name', ['name' => $this->transfer?->toWarehouse?->name])
            : (string) $this->warehouse?->name;
    }
}
