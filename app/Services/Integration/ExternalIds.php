<?php

declare(strict_types=1);

namespace App\Services\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Turns the existing system's IDs into this module's IDs. An ID that has not
 * been sent yet is an error naming the endpoint to send it to first, so the
 * two databases never point at records the other side does not have.
 */
class ExternalIds
{
    /**
     * @param class-string<Model> $model
     * @param iterable<mixed>     $externalIds
     *
     * @return array<string, int|string> external id => local id
     */
    public static function resolve(string $model, iterable $externalIds, string $field, string $endpoint): array
    {
        $wanted = collect($externalIds)
            ->reject(fn ($id) => $id === null || $id === '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($wanted->isEmpty()) {
            return [];
        }

        $query = $model::query();
        if (method_exists($model, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        $found = $query->whereIn('external_id', $wanted)->pluck('id', 'external_id')
            ->mapWithKeys(fn ($id, $ext) => [(string) $ext => $id])
            ->all();

        $unknown = $wanted->reject(fn ($id) => array_key_exists($id, $found))->values();

        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => sprintf('Unknown %s id(s): %s. Send them to %s first.', str_replace('_id', '', $field), $unknown->take(20)->implode(', '), $endpoint),
            ]);
        }

        return $found;
    }
}
