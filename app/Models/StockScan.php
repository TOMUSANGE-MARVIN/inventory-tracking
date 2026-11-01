<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScanAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockScan extends Model
{
    protected $fillable = [
        'product_id',
        'stock_item_id',
        'box_id',
        'warehouse_id',
        'transfer_id',
        'action',
        'quantity',
        'code',
        'user_id',
        'station',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'action' => ScanAction::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
