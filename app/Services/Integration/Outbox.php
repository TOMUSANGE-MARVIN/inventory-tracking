<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\ScanAction;
use App\Models\IntegrationEvent;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\Transfer;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

/**
 * Writes what happened in the module to the updates feed the existing system
 * reads. Called inside the same database transaction as the change itself,
 * so an update is published if and only if the change was saved.
 *
 * Payloads use the existing system's IDs (null for records that did not come
 * from it, e.g. demo data).
 */
class Outbox
{
    public const TRANSFER_DISPATCHED = 'transfer.dispatched';
    public const TRANSFER_RECEIVED = 'transfer.received';
    public const TRANSFER_CANCELLED = 'transfer.cancelled';
    public const ITEM_DISPOSED = 'item.disposed';
    public const ITEM_NEEDS_REFILL = 'item.needs_refill';
    public const ITEM_REFILLED = 'item.refilled';

    /**
     * @param Collection<int, StockItem> $items
     * @param array<int>                 $emptyIds items travelling as empty containers (for refill), not stock
     */
    public function transferDispatched(Transfer $transfer, Collection $items, array $emptyIds): void
    {
        $products = $this->productIds($items->pluck('product_id'));
        $isEmpty = fn (StockItem $i) => in_array($i->id, $emptyIds, true);

        $this->record(self::TRANSFER_DISPATCHED, $this->transferHeader($transfer) + [
            'dispatched_at' => $transfer->dispatched_at?->toIso8601String(),
            'dispatched_by' => auth()->user()?->external_id,
            'lines' => $this->lines($items->reject($isEmpty), $products),
            'empty_containers' => $this->lines($items->filter($isEmpty), $products),
        ]);
    }

    /**
     * @param Collection<int, StockItem> $received
     * @param Collection<int, StockItem> $missing
     * @param array<int>                 $emptyIds items that travelled as empty containers
     */
    public function transferReceived(Transfer $transfer, Collection $received, Collection $missing, array $emptyIds): void
    {
        $products = $this->productIds($received->pluck('product_id')->merge($missing->pluck('product_id')));
        $isEmpty = fn (StockItem $i) => in_array($i->id, $emptyIds, true);

        $this->record(self::TRANSFER_RECEIVED, $this->transferHeader($transfer) + [
            'received_at' => $transfer->received_at?->toIso8601String(),
            'received_by' => auth()->user()?->external_id,
            'complete' => $missing->isEmpty(),
            'lines' => $this->lines($received->reject($isEmpty), $products),
            'missing' => $this->lines($missing->reject($isEmpty), $products),
            'empty_containers' => $this->lines($received->filter($isEmpty), $products),
            'missing_empty_containers' => $this->lines($missing->filter($isEmpty), $products),
        ]);
    }

    public function transferCancelled(Transfer $transfer, string $reason): void
    {
        $this->record(self::TRANSFER_CANCELLED, $this->transferHeader($transfer) + [
            'cancelled_by' => auth()->user()?->external_id,
            'reason' => $reason,
        ]);
    }

    /** An item in use was used to completion: thrown away, or emptied to wait for a refill. */
    public function itemFinished(StockItem $item, ScanAction $action): void
    {
        $this->record($action === ScanAction::DISPOSED ? self::ITEM_DISPOSED : self::ITEM_NEEDS_REFILL, $this->itemFields($item) + [
            'customers_served' => $this->customersSinceLastFill($item),
            'grams_per_unit' => $item->product?->grams_per_unit === null ? null : (float) $item->product->grams_per_unit,
            'scanned_by' => auth()->user()?->external_id,
        ]);
    }

    public function itemRefilled(StockItem $item): void
    {
        $this->record(self::ITEM_REFILLED, $this->itemFields($item) + [
            'refill_count' => $item->refill_count,
            'scanned_by' => auth()->user()?->external_id,
        ]);
    }

    private function record(string $type, array $payload): void
    {
        IntegrationEvent::query()->create([
            'type' => $type,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    private function transferHeader(Transfer $transfer): array
    {
        $branches = Warehouse::query()
            ->whereIn('id', [$transfer->from_warehouse_id, $transfer->to_warehouse_id])
            ->pluck('external_id', 'id');

        return [
            'transfer_id' => $transfer->id,
            'reference' => $transfer->reference,
            'requisition_id' => $transfer->transferRequest?->external_id,
            'from_branch_id' => $branches[$transfer->from_warehouse_id] ?? null,
            'to_branch_id' => $branches[$transfer->to_warehouse_id] ?? null,
        ];
    }

    private function itemFields(StockItem $item): array
    {
        return [
            'item_code' => $item->code,
            'product_id' => $item->product?->external_id,
            'branch_id' => Warehouse::query()->whereKey($item->warehouse_id)->value('external_id'),
        ];
    }

    /** Customers served since the item was last filled (on arrival, or at its latest refill). */
    private function customersSinceLastFill(StockItem $item): int
    {
        $lastRefill = StockScan::query()
            ->where('stock_item_id', $item->id)
            ->where('action', ScanAction::REFILLED)
            ->max('id');

        return (int) StockScan::query()
            ->where('stock_item_id', $item->id)
            ->where('action', ScanAction::SERVED_CLIENT)
            ->when($lastRefill, fn ($q) => $q->where('id', '>', $lastRefill))
            ->sum('quantity');
    }

    /** @return array<string, string|null> local product id => the existing system's id */
    private function productIds(Collection $ids): array
    {
        return Product::withTrashed()->whereIn('id', $ids->unique()->values())->pluck('external_id', 'id')->all();
    }

    /** One line per product: how many units and which item codes. */
    private function lines(Collection $items, array $productIds): array
    {
        return $items->groupBy('product_id')->map(fn (Collection $group, $productId) => [
            'product_id' => $productIds[$productId] ?? null,
            'quantity' => $group->count(),
            'item_codes' => $group->pluck('code')->sort()->values()->all(),
        ])->values()->all();
    }
}
