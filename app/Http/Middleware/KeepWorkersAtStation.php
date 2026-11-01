<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AccessRoles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Workers only scan items: any other back-office page sends them to the scan
 * station instead of showing a 403.
 */
class KeepWorkersAtStation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AccessRoles::isWorkerOnly($request->user())) {
            return $request->expectsJson()
                ? response()->json(['message' => __('Workers can only use the scan station.')], 403)
                : redirect()->route('tracking.station');
        }

        return $next($request);
    }
}
