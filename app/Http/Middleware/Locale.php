<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Sparkle is English-only; ignore any old language cookie (e.g. "fr") from before.
        App::setLocale('en');

        return $next($request);
    }
}
