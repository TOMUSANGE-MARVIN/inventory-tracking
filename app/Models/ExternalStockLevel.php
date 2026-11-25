<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The official stock level of a product at a location, as last reported by the existing system. */
class ExternalStockLevel extends Model
{
    protected $fillable = ['warehouse_id', 'product_id', 'quantity', 'grams', 'reported_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
