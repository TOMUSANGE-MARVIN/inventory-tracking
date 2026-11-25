<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A requisition or transfer raised in the existing system; dispatch scans are matched to it. */
class TransferRequest extends Model
{
    protected $fillable = ['external_id', 'reference', 'from_warehouse_id', 'to_warehouse_id', 'status', 'requested_at'];

    protected function casts(): array
    {
        return ['requested_at' => 'date'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransferRequestLine::class);
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }
}
