<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A count of everything physically at one location, compared with the system. */
class StockTake extends Model
{
    public const OPEN = 'open';

    public const FINISHED = 'finished';

    public const CANCELLED = 'cancelled';

    protected $fillable = ['reference', 'warehouse_id', 'started_by', 'finished_by', 'status', 'expected', 'found', 'not_found', 'unexpected', 'note', 'finished_at'];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }
}
