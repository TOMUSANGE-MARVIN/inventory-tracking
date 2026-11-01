<?php

declare(strict_types=1);

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\Locale;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        api: __DIR__ . '/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Guest redirect with route helper (preferred over hardcoded path)
        $middleware->redirectGuestsTo(fn (Request $request) => route('login'));

        // Local HTTPS proxy for phone testing (camera needs HTTPS); only trust it from this machine.
        $middleware->trustProxies(at: ['127.0.0.1']);

        // $middleware->append(Locale::class);

        // Livewire CSRF exception
        $middleware->validateCsrfTokens(except: [
            'livewire/*',
        ]);

        // Middleware aliases
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.installation' => CheckInstallation::class,
            'workers.station' => App\Http\Middleware\KeepWorkersAtStation::class,
        ]);

        // Uncomment to apply installation check globally to web routes
        // $middleware->web(append: [
        //     CheckInstallation::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Configure exception rendering/reporting here
        // Example: API-friendly JSON responses for API routes
    })
    ->create();
