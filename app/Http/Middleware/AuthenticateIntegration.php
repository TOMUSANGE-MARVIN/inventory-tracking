<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IntegrationLog;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Lets in the existing system by its shared bearer token, and logs every call
 * so that a difference between the two systems can be traced to its source.
 */
class AuthenticateIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $token = (string) config('integration.token');

        $response = match (true) {
            $token === '' => response()->json(['message' => 'The integration API is not configured.'], 503),
            ! hash_equals($token, (string) $request->bearerToken()) => response()->json(['message' => 'Invalid or missing token.'], 401),
            default => $next($request),
        };

        $this->log($request, $response, (int) ((hrtime(true) - $started) / 1_000_000));

        return $response;
    }

    private function log(Request $request, Response $response, int $durationMs): void
    {
        try {
            $error = null;
            if ($response->getStatusCode() >= 400 && $response instanceof JsonResponse) {
                $body = $response->getData(true);
                $error = Str::limit(json_encode(['message' => $body['message'] ?? null, 'errors' => $body['errors'] ?? null]) ?: '', 2000);
            }

            $data = $request->input('data');

            IntegrationLog::query()->create([
                'method' => $request->method(),
                'path' => Str::limit($request->path(), 250, ''),
                'status' => $response->getStatusCode(),
                'records' => is_array($data) ? count($data) : null,
                'error' => $error,
                'duration_ms' => $durationMs,
                'ip' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break the call itself.
            report($e);
        }
    }
}
