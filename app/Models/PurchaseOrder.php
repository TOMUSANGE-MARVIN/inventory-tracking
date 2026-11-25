<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A purchase order raised in the existing system; items are scanned in against it. */
class PurchaseOrder extends Model
{
    protected $fillable = ['external_id', 'reference', 'supplier_name', 'warehouse_id', 'status', 'ordered_at'];

    protected function casts(): array
    {
        return ['ordered_at' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
