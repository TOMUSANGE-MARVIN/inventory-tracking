<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One call to the integration API. */
class IntegrationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['method', 'path', 'status', 'records', 'error', 'duration_ms', 'ip'];
}
