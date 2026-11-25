<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The updates feed: everything that happened in the module, oldest first.
 * The existing system keeps the id of the last update it applied and asks for
 * the ones after it, so nothing is missed and nothing is applied twice.
 */
class UpdatesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $max = (int) config('integration.max_updates_page', 500);

        $validated = $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', "max:{$max}"],
        ]);

        $after = (int) ($validated['after'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 100);

        $events = IntegrationEvent::query()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $page = $events->take($limit);

        return response()->json([
            'data' => $page->map(fn (IntegrationEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'occurred_at' => $e->occurred_at->toIso8601String(),
                'data' => $e->payload,
            ])->values(),
            'next_after' => $page->last()?->id ?? $after,
            'has_more' => $events->count() > $limit,
        ]);
    }
}
