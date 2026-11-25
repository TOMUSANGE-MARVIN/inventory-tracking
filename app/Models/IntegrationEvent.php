<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing that happened in the module, for the existing system to read from the updates feed. */
class IntegrationEvent extends Model
{
    protected $fillable = ['type', 'payload', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
