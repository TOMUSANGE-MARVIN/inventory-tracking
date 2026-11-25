<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A salon service from the existing system, with the grams of each product it uses. */
class Service extends Model
{
    protected $fillable = ['external_id', 'name', 'status'];

    protected function casts(): array
    {
        return ['status' => 'boolean'];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_products')
            ->withPivot('grams')
            ->withTimestamps();
    }
}
