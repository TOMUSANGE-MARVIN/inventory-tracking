<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Models\StockItem;
use App\Models\StockScan;
use Illuminate\Support\Collection;

/**
 * The life of one labelled item, worked out from its scans: each fill (opened →
 * customers → finished), customers per worker and station, the locations it has
 * been at, and its history with customer scans grouped per day.
 */
class ItemAnalytics
{
    /** @var Collection<int, StockScan> oldest first */
    private Collection $scans;

    public function __construct(private StockItem $item)
    {
        $this->scans = $item->scans()
            ->with('warehouse:id,name', 'user:id,name', 'transfer:id,reference,from_warehouse_id,to_warehouse_id', 'transfer.toWarehouse:id,name', 'box:id,code')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function summary(): array
    {
        $served = $this->scans->where('action', ScanAction::SERVED_CLIENT);
        $arrived = $this->scans->firstWhere('action', ScanAction::RECEIVED_STOCK)?->created_at ?? $this->item->created_at;
        $fills = $this->fills();
        $finished = $fills->whereNotNull('finished_at');
        $current = $fills->last();

        return [
            'customers' => (int) $served->sum('quantity'),
            'refills' => (int) $this->item->refill_count,
            'fills' => $fills->count(),
            'per_fill' => $finished->isEmpty() ? null : round($finished->avg('customers'), 1),
            'days_since_arrival' => (int) $arrived->copy()->startOfDay()->diffInDays(today(), true),
            'arrived_at' => $arrived,
            'moves' => $this->scans->where('action', ScanAction::DISPATCHED)->count(),
            'last_used_at' => $served->last()?->created_at,
            // The fill in use right now, if any.
            'current' => $current && $current['finished_at'] === null && $this->item->status === ItemStatus::IN_USE ? $current : null,
        ];
    }

    /**
     * One row per fill: opened → customers → finished (refillable items have
     * several; others have one).
     *
     * @return Collection<int, array>
     */
    public function fills(): Collection
    {
        $fills = collect();
        $open = null;

        foreach ($this->scans as $scan) {
            if ($scan->action === ScanAction::IN_USE) {
                $open = ['opened_at' => $scan->created_at, 'location' => $scan->warehouse?->name, 'customers' => 0, 'finished_at' => null, 'ended' => null];
            } elseif ($scan->action === ScanAction::SERVED_CLIENT && $open) {
                $open['customers'] += (int) $scan->quantity;
            } elseif (in_array($scan->action, [ScanAction::DISPOSED, ScanAction::RETURNED_FOR_REFILL], true) && $open) {
                $open['finished_at'] = $scan->created_at;
                $open['ended'] = $scan->action;
                $fills->push($open);
                $open = null;
            }
        }

        if ($open) {
            $fills->push($open);
        }

        return $fills->values()->map(fn ($f, $i) => $f + [
            'number' => $i + 1,
            'days' => (int) $f['opened_at']->copy()->startOfDay()->diffInDays(($f['finished_at'] ?? now())->copy()->startOfDay(), true) + 1,
        ]);
    }

    /** Customers served by each worker at each station. */
    public function byWorker(): Collection
    {
        return $this->scans->where('action', ScanAction::SERVED_CLIENT)
            ->groupBy(fn (StockScan $s) => $s->user_id . '|' . $s->station . '|' . $s->warehouse_id)
            ->map(fn (Collection $g) => [
                'worker' => $g->first()->user?->name ?? '—',
                'station' => $g->first()->station,
                'location' => $g->first()->warehouse?->name,
                'customers' => (int) $g->sum('quantity'),
                'last_at' => $g->max('created_at'),
            ])
            ->sortByDesc('customers')
            ->values();
    }

    /** Places the item has been, in order, with when it got there. */
    public function journey(): Collection
    {
        return $this->scans
            ->filter(fn (StockScan $s) => in_array($s->action, [ScanAction::RECEIVED_STOCK, ScanAction::RECEIVED_TRANSFER], true))
            ->map(fn (StockScan $s) => ['location' => $s->warehouse?->name, 'at' => $s->created_at])
            ->values();
    }

    /** Customers per day, for the usage chart. */
    public function customersPerDay(): Collection
    {
        return $this->scans->where('action', ScanAction::SERVED_CLIENT)
            ->groupBy(fn (StockScan $s) => $s->created_at->toDateString())
            ->map(fn (Collection $g) => (int) $g->sum('quantity'));
    }

    /** History, newest first, with customer scans on the same day at the same place merged into one row. */
    public function history(): Collection
    {
        return $this->scans
            ->groupBy(fn (StockScan $s) => $s->action === ScanAction::SERVED_CLIENT
                ? 'served|' . $s->created_at->toDateString() . '|' . $s->warehouse_id
                : 'scan|' . $s->id)
            ->map(function (Collection $g) {
                $first = $g->first();

                return [
                    'id' => $first->id,
                    'at' => $g->max('created_at'),
                    'action' => $first->action,
                    'location' => $first->warehouse?->name,
                    'to' => $first->action === ScanAction::DISPATCHED ? $first->transfer?->toWarehouse?->name : null,
                    'transfer' => $first->transfer,
                    'box' => $first->box?->code,
                    'by' => $g->map(fn ($s) => $s->user?->name . ($s->station ? " ({$s->station})" : ''))->filter()->unique()->join(', '),
                    'customers' => $first->action === ScanAction::SERVED_CLIENT ? (int) $g->sum('quantity') : null,
                    'note' => $g->pluck('note')->filter()->unique()->join('; '),
                ];
            })
            ->sortByDesc('at')
            ->values();
    }
}
