<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    public const FOUND = 'found';

    public const NOT_FOUND = 'not_found';

    public const UNEXPECTED = 'unexpected';

    protected $fillable = ['stock_take_id', 'stock_item_id', 'result', 'system_status', 'system_warehouse_id', 'scanned_at'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }

    public function systemWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'system_warehouse_id');
    }
}
