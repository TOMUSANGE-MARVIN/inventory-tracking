<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoxStatus;
use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockBox;
use App\Models\StockCorrection;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\Transfer;
use App\Models\TransferDetails;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Item-level stock tracking. Every unit gets its own label when it arrives;
 * scanning that label moves it between locations, so the system always knows
 * which location each unit is at. Per-location quantities in product_warehouse
 * are kept in step so the rest of the app keeps working.
 */
class StockTrackingService
{
    /** Resolve a scanned product barcode/QR (the manufacturer or catalogue code). */
    public function findProduct(string $code): ?Product
    {
        return Product::query()->where('code', trim($code))->first();
    }

    /** Resolve a scanned item label. */
    public function findItem(string $code): ?StockItem
    {
        return StockItem::query()->with('product', 'warehouse')->where('code', Str::upper(trim($code)))->first();
    }

    /** Resolve a scanned box QR. */
    public function findBox(string $code): ?StockBox
    {
        return StockBox::query()->with('warehouse')->where('code', Str::upper(trim($code)))->first();
    }

    /**
     * A sealed box arrives: label the box, label every unit inside, close it again.
     *
     * @param array<string, int> $lines units per product id
     *
     * @return array{0: StockBox, 1: Collection<int, StockItem>}
     */
    public function receiveBoxedDelivery(int $warehouseId, array $lines, ?string $batch = null, ?string $note = null): array
    {
        $lines = array_filter($lines, fn ($qty) => $qty > 0);

        if ($lines === []) {
            throw new RuntimeException(__('Add what was inside the box.'));
        }

        return DB::transaction(function () use ($warehouseId, $lines, $batch, $note): array {
            $box = StockBox::query()->create([
                'warehouse_id' => $warehouseId,
                'status' => BoxStatus::PACKED,
                'batch' => $batch,
                'note' => $note,
            ]);
            $box->update(['code' => 'BX-' . str_pad((string) $box->id, 6, '0', STR_PAD_LEFT)]);

            $items = collect();
            foreach ($lines as $productId => $qty) {
                $items = $items->merge($this->receiveArrival(Product::query()->findOrFail($productId), $warehouseId, $qty, $batch, $note, $box->id));
            }

            return [$box, $items];
        });
    }

    /**
     * New stock arriving at a location: create one labelled item per unit,
     * optionally inside a box.
     *
     * @return Collection<int, StockItem>
     */
    public function receiveArrival(Product $product, int $warehouseId, int $qty, ?string $batch = null, ?string $note = null, ?int $boxId = null): Collection
    {
        if ($qty < 1 || $qty > 500) {
            throw new RuntimeException(__('Enter a quantity between 1 and 500.'));
        }

        return DB::transaction(function () use ($product, $warehouseId, $qty, $batch, $note, $boxId): Collection {
            $items = collect();

            for ($i = 0; $i < $qty; $i++) {
                $item = StockItem::query()->create([
                    'product_id' => $product->id,
                    'box_id' => $boxId,
                    'warehouse_id' => $warehouseId,
                    'status' => ItemStatus::IN_STOCK,
                    'batch' => $batch,
                ]);
                $item->update(['code' => 'SP-' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT)]);

                $this->log($item, $warehouseId, ScanAction::RECEIVED_STOCK, null, $note);
                $items->push($item);
            }

            $this->addStock($product->id, $warehouseId, $qty);
            Product::query()->whereKey($product->id)->increment('quantity', $qty);

            return $items;
        });
    }

    /**
     * Check an item can be added to a dispatch from the given location.
     */
    public function assertDispatchable(StockItem $item, int $fromWarehouseId): void
    {
        if ($item->warehouse_id !== $fromWarehouseId || ! $item->status->canDispatch()) {
            throw new RuntimeException(__(':code is :status at :where, not available at this location.', [
                'code' => $item->code,
                'status' => Str::lower($item->status->label()),
                'where' => $item->whereabouts() ?: '—',
            ]));
        }
    }

    /** Check a whole box can be dispatched from the given location. */
    public function assertBoxDispatchable(StockBox $box, int $fromWarehouseId): void
    {
        if ($box->warehouse_id !== $fromWarehouseId || ! in_array($box->status, [BoxStatus::PACKED, BoxStatus::OPENED], true)) {
            throw new RuntimeException(__(':code is :status at :where, not available at this location.', [
                'code' => $box->code,
                'status' => Str::lower($box->status->label()),
                'where' => $box->whereabouts() ?: '—',
            ]));
        }

        if (! $box->items()->where('status', ItemStatus::IN_STOCK)->exists()) {
            throw new RuntimeException(__(':code has nothing in stock inside it.', ['code' => $box->code]));
        }
    }

    /**
     * @param array<int> $itemIds loose units (a unit scanned on its own is taken out of its box)
     * @param array<int> $boxIds  whole boxes; every in-stock unit inside travels with the box
     */
    public function dispatch(int $fromWarehouseId, int $toWarehouseId, array $itemIds, ?string $note = null, array $boxIds = []): Transfer
    {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new RuntimeException(__('Source and destination must be different.'));
        }

        if ($itemIds === [] && $boxIds === []) {
            throw new RuntimeException(__('Scan at least one box or item before dispatching.'));
        }

        return DB::transaction(function () use ($fromWarehouseId, $toWarehouseId, $itemIds, $note, $boxIds): Transfer {
            $boxes = StockBox::query()->whereIn('id', $boxIds)->lockForUpdate()->get();
            $boxes->each(fn (StockBox $box) => $this->assertBoxDispatchable($box, $fromWarehouseId));

            $boxedIds = StockItem::query()->whereIn('box_id', $boxes->pluck('id'))
                ->where('status', ItemStatus::IN_STOCK)->pluck('id')->all();

            $items = StockItem::query()->with('product')->whereIn('id', array_unique([...$itemIds, ...$boxedIds]))->lockForUpdate()->get();
            $items->each(fn (StockItem $item) => $this->assertDispatchable($item, $fromWarehouseId));

            // Units scanned on their own leave their box; the box keeps only what is still inside.
            // Remember which box each came from so the box's history shows them leaving.
            $takenFrom = [];
            foreach ($items->whereNotIn('id', $boxedIds)->whereNotNull('box_id') as $item) {
                $takenFrom[$item->id] = $item->box_id;
                $this->takeOutOfBox($item);
            }

            $transfer = Transfer::query()->create([
                'reference' => 'TRF-' . now()->format('ymd') . '-' . Str::upper(Str::random(4)),
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'item' => $items->pluck('product_id')->unique()->count(),
                'total_qty' => $items->count(),
                'total_tax' => 0,
                'total_cost' => $items->sum(fn ($i) => $i->product->cost),
                'total_amount' => $items->sum(fn ($i) => $i->product->price),
                'status' => TransferStatus::IN_TRANSIT,
                'note' => $note,
                'dispatched_by' => auth()->id(),
                'dispatched_at' => now(),
            ]);

            foreach ($items->groupBy('product_id') as $productId => $group) {
                TransferDetails::query()->create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'warehouse_id' => $toWarehouseId,
                    'quantity' => $group->count(),
                ]);

                $fromStock = $group->where('status', ItemStatus::IN_STOCK)->count();
                if ($fromStock > 0) {
                    $this->stockRow($productId, $fromWarehouseId)->decrement('qty', $fromStock);
                }
            }

            foreach ($items as $item) {
                $transfer->items()->attach($item->id, ['from_stock' => $item->status === ItemStatus::IN_STOCK]);
                $item->update(['status' => ItemStatus::IN_TRANSIT, 'warehouse_id' => null, 'transfer_id' => $transfer->id]);
                $this->log($item, $fromWarehouseId, ScanAction::DISPATCHED, $transfer->id, isset($takenFrom[$item->id]) ? __('Taken out of the box') : null, 1, $takenFrom[$item->id] ?? null);
            }

            foreach ($boxes as $box) {
                $box->update(['status' => BoxStatus::IN_TRANSIT, 'warehouse_id' => null, 'transfer_id' => $transfer->id]);
            }

            return $transfer;
        });
    }

    /**
     * The destination scans items in. Scanned items are placed at the destination;
     * anything on the transfer that was not scanned is marked missing.
     *
     * @param array<int>      $scannedItemIds units scanned in one by one
     * @param array<int>|null $scannedBoxIds  boxes scanned on arrival (null: treat every box as arrived)
     */
    public function receive(Transfer $transfer, array $scannedItemIds, ?string $note = null, ?array $scannedBoxIds = null): Transfer
    {
        if ($transfer->status !== TransferStatus::IN_TRANSIT) {
            throw new RuntimeException(__('This transfer has already been received.'));
        }

        return DB::transaction(function () use ($transfer, $scannedItemIds, $note, $scannedBoxIds): Transfer {
            $to = $transfer->to_warehouse_id;
            $items = $transfer->items()->lockForUpdate()->get();
            $received = $items->whereIn('id', $scannedItemIds);
            $missing = $items->whereNotIn('id', $scannedItemIds);

            // Empty refillable containers (sent e.g. to Head Office for refilling) arrive still empty.
            $empties = $transfer->items()->wherePivot('from_stock', false)->pluck('stock_items.id')->all();

            foreach ($received as $item) {
                $item->update([
                    'status' => in_array($item->id, $empties, true) ? ItemStatus::FINISHED_REFILL : ItemStatus::IN_STOCK,
                    'warehouse_id' => $to,
                    'transfer_id' => null,
                ]);
                $transfer->items()->updateExistingPivot($item->id, ['received_at' => now()]);
                $this->log($item, $to, ScanAction::RECEIVED_TRANSFER, $transfer->id);
            }

            // Note which boxes came up short before missing units are taken out of them.
            $shortBoxIds = $missing->pluck('box_id')->filter()->unique()->all();

            foreach ($missing as $item) {
                $item->update(['status' => ItemStatus::MISSING, 'warehouse_id' => null, 'transfer_id' => null]);
                $this->log($item, $to, ScanAction::MARKED_MISSING, $transfer->id);
                // A missing unit is no longer in its box.
                $item->update(['box_id' => null]);
            }

            // Boxes arrive with the units that were scanned inside them; a box that came up short counts
            // as opened, and a box that was never scanned is missing.
            foreach (StockBox::query()->where('transfer_id', $transfer->id)->get() as $box) {
                if ($scannedBoxIds !== null && ! in_array($box->id, $scannedBoxIds, true)) {
                    $box->update(['warehouse_id' => null, 'transfer_id' => null, 'status' => BoxStatus::MISSING]);

                    continue;
                }

                $short = in_array($box->id, $shortBoxIds);
                $box->update(['warehouse_id' => $to, 'transfer_id' => null, 'status' => $short ? BoxStatus::OPENED : BoxStatus::PACKED]);
                $this->refreshBox($box);
            }

            foreach ($received->whereNotIn('id', $empties)->groupBy('product_id') as $productId => $group) {
                $this->addStock($productId, $to, $group->count());
            }

            foreach ($transfer->transferDetails as $detail) {
                $detail->update(['received_quantity' => $received->where('product_id', $detail->product_id)->count()]);
            }

            $transfer->update([
                'status' => $missing->isEmpty() ? TransferStatus::RECEIVED : TransferStatus::DISCREPANCY,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'note' => trim(($transfer->note ?? '') . "\n" . ($note ?? '')) ?: null,
            ]);

            return $transfer;
        });
    }

    /** Record what happens to an item at the location it is at. */
    public function record(StockItem $item, ScanAction $action, ?string $note = null): StockItem
    {
        [$from, $to] = match ($action) {
            ScanAction::IN_USE => [ItemStatus::IN_STOCK, ItemStatus::IN_USE],
            ScanAction::DISPOSED => [ItemStatus::IN_USE, ItemStatus::DISPOSED],
            ScanAction::RETURNED_FOR_REFILL => [ItemStatus::IN_USE, ItemStatus::FINISHED_REFILL],
            default => throw new RuntimeException(__('This action cannot be recorded here.')),
        };

        if ($action === ScanAction::RETURNED_FOR_REFILL && ! $item->product?->is_refillable) {
            throw new RuntimeException(__(':product is not refillable – mark it finished to dispose of it.', ['product' => $item->product?->name]));
        }

        if ($item->status !== $from || $item->warehouse_id === null) {
            throw new RuntimeException(__(':code is :status (:where) – it must be :required first.', [
                'code' => $item->code,
                'status' => Str::lower($item->status->label()),
                'where' => $item->whereabouts() ?: '—',
                'required' => Str::lower($from->label()),
            ]));
        }

        return DB::transaction(function () use ($item, $action, $to, $note): StockItem {
            // An opened item still counts as the branch's stock until it is used to completion.
            if (in_array($action, [ScanAction::DISPOSED, ScanAction::RETURNED_FOR_REFILL], true)) {
                $this->stockRow($item->product_id, $item->warehouse_id)->decrement('qty');
                Product::query()->whereKey($item->product_id)->decrement('quantity');
            }

            $item->update(['status' => $to]);
            $this->log($item, $item->warehouse_id, $action, null, $note);

            if ($item->box_id) {
                $this->takeOutOfBox($item);
            }

            return $item;
        });
    }

    /**
     * A client was worked on with this item (one scan per client, or several
     * at once). A sealed item is opened (put into use) on its first client.
     */
    public function serveClient(StockItem $item, int $clients = 1, ?string $note = null): StockScan
    {
        if ($clients < 1 || $clients > 50) {
            throw new RuntimeException(__('Clients must be between 1 and 50.'));
        }

        if ($item->warehouse_id === null || ! in_array($item->status, [ItemStatus::IN_STOCK, ItemStatus::IN_USE], true)) {
            throw new RuntimeException(__(':code is :status (:where) – only items in stock or in use can be used on a client.', [
                'code' => $item->code,
                'status' => Str::lower($item->status->label()),
                'where' => $item->whereabouts() ?: '—',
            ]));
        }

        return DB::transaction(function () use ($item, $clients, $note): StockScan {
            if ($item->status === ItemStatus::IN_STOCK) {
                $this->record($item, ScanAction::IN_USE);
            }

            return $this->log($item, $item->warehouse_id, ScanAction::SERVED_CLIENT, null, $note, $clients);
        });
    }

    /**
     * An item in use is used up: refillable products wait for a refill (same
     * barcode), everything else is disposed of.
     */
    public function finish(StockItem $item, ?string $note = null): StockItem
    {
        return $this->record($item, $item->product?->is_refillable ? ScanAction::RETURNED_FOR_REFILL : ScanAction::DISPOSED, $note);
    }

    /** An empty refillable item has been refilled where it is: back into stock under the same barcode. */
    public function refill(StockItem $item, ?string $note = null): StockItem
    {
        if ($item->status !== ItemStatus::FINISHED_REFILL || $item->warehouse_id === null) {
            throw new RuntimeException(__(':code is :status (:where) – only items waiting for a refill can be refilled.', [
                'code' => $item->code,
                'status' => Str::lower($item->status->label()),
                'where' => $item->whereabouts() ?: '—',
            ]));
        }

        return DB::transaction(function () use ($item, $note): StockItem {
            $item->update(['status' => ItemStatus::IN_STOCK, 'refill_count' => $item->refill_count + 1]);
            $this->addStock($item->product_id, $item->warehouse_id, 1);
            Product::query()->whereKey($item->product_id)->increment('quantity');
            $this->log($item, $item->warehouse_id, ScanAction::REFILLED, null, $note);

            return $item;
        });
    }

    /** Undo a client-served scan made by the same person in the last 15 minutes (a double scan). */
    public function undoServe(StockScan $scan): void
    {
        if ($scan->action !== ScanAction::SERVED_CLIENT || $scan->user_id !== auth()->id() || $scan->created_at->lt(now()->subMinutes(15))) {
            throw new RuntimeException(__('Only your own client scans from the last 15 minutes can be undone.'));
        }

        $scan->delete();
    }

    // ---------------------------------------------------------- Corrections

    /**
     * Why a delivery entry cannot be undone, or null if it can: every unit must
     * still be exactly as it was received (only the arrival scan, in stock, at the
     * same place, still in its box).
     */
    public function undoBlocker(Collection $items, ?StockBox $box = null): ?string
    {
        if ($items->isEmpty()) {
            return __('Nothing to undo.');
        }

        $scans = StockScan::query()->whereIn('stock_item_id', $items->pluck('id'))->get()->groupBy('stock_item_id');

        foreach ($items as $item) {
            $own = $scans->get($item->id, collect());
            $arrival = $own->firstWhere('action', ScanAction::RECEIVED_STOCK);

            if ($own->count() !== 1 || ! $arrival || $item->status !== ItemStatus::IN_STOCK
                || $item->warehouse_id !== $arrival->warehouse_id || $item->box_id !== $box?->id) {
                return __(':code has already been moved, opened or taken out of its box, so this delivery can no longer be undone.', ['code' => $item->code]);
            }
        }

        if ($box && StockItem::query()->where('box_id', $box->id)->whereNotIn('id', $items->pluck('id'))->exists()) {
            return __('Box :code holds other units.', ['code' => $box->code]);
        }

        // Every unit that was ever in the box must still be in it (none taken out and sent on).
        if ($box && StockScan::query()->where('box_id', $box->id)->whereNotIn('stock_item_id', $items->pluck('id'))->exists()) {
            return __('Units have already been taken out of box :code, so this delivery can no longer be undone.', ['code' => $box->code]);
        }

        return null;
    }

    /** Units of a delivery entry: everything in a box, or the loose units given. */
    public function deliveryItems(StockBox|StockItem|Collection $target): Collection
    {
        return match (true) {
            $target instanceof StockBox => StockItem::query()->with('product:id,name')->where('box_id', $target->id)->get(),
            $target instanceof StockItem => StockItem::query()->with('product:id,name')->whereKey($target->id)->get(),
            default => StockItem::query()->with('product:id,name')->whereIn('id', $target->pluck('id'))->get(),
        };
    }

    /**
     * Remove a delivery entered by mistake (wrong product or quantity). Allowed
     * only while its units are untouched. The removal is logged with the reason.
     */
    public function undoDelivery(StockBox|StockItem|Collection $target, string $reason): StockCorrection
    {
        if (trim($reason) === '') {
            throw new RuntimeException(__('Say why this delivery is being undone.'));
        }

        return DB::transaction(function () use ($target, $reason): StockCorrection {
            $box = $target instanceof StockBox ? StockBox::query()->lockForUpdate()->findOrFail($target->id) : null;
            $items = $this->deliveryItems($box ?? $target);

            if ($items->pluck('warehouse_id')->unique()->count() > 1) {
                throw new RuntimeException(__('These units are at different locations.'));
            }

            if ($blocker = $this->undoBlocker($items, $box)) {
                throw new RuntimeException($blocker);
            }

            $warehouseId = (int) $items->first()->warehouse_id;
            $byProduct = $items->groupBy('product_id');

            foreach ($byProduct as $productId => $group) {
                $this->stockRow($productId, $warehouseId)->decrement('qty', $group->count());
                Product::query()->whereKey($productId)->decrement('quantity', $group->count());
            }

            $details = [
                'box' => $box?->code,
                'units' => $items->pluck('code')->all(),
                'products' => $byProduct->map(fn ($g) => ['name' => $g->first()->product?->name, 'qty' => $g->count()])->values()->all(),
            ];

            StockScan::query()->whereIn('stock_item_id', $items->pluck('id'))->delete();
            StockItem::query()->whereIn('id', $items->pluck('id'))->delete();
            $box?->delete();

            return StockCorrection::query()->create([
                'type' => StockCorrection::UNDO_DELIVERY,
                'user_id' => auth()->id(),
                'warehouse_id' => $warehouseId,
                'reference' => $box?->code ?? $items->first()->code,
                'reason' => trim($reason),
                'details' => $details,
            ]);
        });
    }

    /**
     * Cancel a dispatch that has not been received (wrong branch, wrong items):
     * everything goes back to where it was sent from, as it was.
     */
    public function cancelTransfer(Transfer $transfer, string $reason): Transfer
    {
        if (trim($reason) === '') {
            throw new RuntimeException(__('Say why this dispatch is being cancelled.'));
        }

        return DB::transaction(function () use ($transfer, $reason): Transfer {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== TransferStatus::IN_TRANSIT) {
                throw new RuntimeException(__('Only a dispatch that is still on its way can be cancelled.'));
            }

            $from = (int) $transfer->from_warehouse_id;
            $items = $transfer->items()->get();

            foreach ($items as $item) {
                $fromStock = (bool) $item->pivot->from_stock;
                $item->update([
                    'status' => $fromStock ? ItemStatus::IN_STOCK : ItemStatus::FINISHED_REFILL,
                    'warehouse_id' => $from,
                    'transfer_id' => null,
                ]);
                $this->log($item, $from, ScanAction::DISPATCH_CANCELLED, $transfer->id, trim($reason));
            }

            foreach ($items->filter(fn ($i) => (bool) $i->pivot->from_stock)->groupBy('product_id') as $productId => $group) {
                $this->addStock($productId, $from, $group->count());
            }

            // Boxes go back as they were: packed if nothing was ever taken out, otherwise opened.
            foreach (StockBox::query()->where('transfer_id', $transfer->id)->get() as $box) {
                $everInside = StockScan::query()->where('box_id', $box->id)->distinct()->count('stock_item_id');
                $box->update([
                    'warehouse_id' => $from,
                    'transfer_id' => null,
                    'status' => $everInside > $box->items()->count() ? BoxStatus::OPENED : BoxStatus::PACKED,
                ]);
            }

            $transfer->update([
                'status' => TransferStatus::CANCELLED,
                'note' => trim(($transfer->note ?? '') . "\n" . __('Cancelled: :reason', ['reason' => trim($reason)])),
            ]);

            StockCorrection::query()->create([
                'type' => StockCorrection::CANCEL_TRANSFER,
                'user_id' => auth()->id(),
                'warehouse_id' => $from,
                'reference' => $transfer->reference,
                'reason' => trim($reason),
                'details' => ['to' => $transfer->toWarehouse?->name, 'units' => $items->pluck('code')->all()],
            ]);

            return $transfer;
        });
    }

    public function headOffice(): ?Warehouse
    {
        return Warehouse::query()->orderBy('id')->first();
    }

    private function stockRow(string $productId, int $warehouseId): Builder
    {
        return ProductWarehouse::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);
    }

    private function addStock(string $productId, int $warehouseId, int $qty): void
    {
        // ProductWarehouse is a Pivot (no usable key on new instances), so update by query.
        $updated = $this->stockRow($productId, $warehouseId)->increment('qty', $qty);

        if ($updated === 0) {
            $product = Product::query()->find($productId, ['price', 'cost']);

            ProductWarehouse::query()->create([
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'qty' => $qty,
                'price' => $product->price ?? 0,
                'cost' => $product->cost ?? 0,
            ]);
        }
    }

    /** Take a unit out of its box and update what the box says about itself. */
    private function takeOutOfBox(StockItem $item): void
    {
        $box = $item->box;
        $item->update(['box_id' => null]);

        if ($box) {
            if ($box->status === BoxStatus::PACKED) {
                $box->update(['status' => BoxStatus::OPENED]);
            }
            $this->refreshBox($box);
        }
    }

    private function refreshBox(StockBox $box): void
    {
        if ($box->status !== BoxStatus::IN_TRANSIT && ! $box->items()->exists()) {
            $box->update(['status' => BoxStatus::EMPTY]);
        }
    }

    private function log(StockItem $item, int $warehouseId, ScanAction $action, ?string $transferId = null, ?string $note = null, int $quantity = 1, ?int $fromBoxId = null): StockScan
    {
        return StockScan::query()->create([
            'product_id' => $item->product_id,
            'stock_item_id' => $item->id,
            'box_id' => $fromBoxId ?? $item->box_id,
            'warehouse_id' => $warehouseId,
            'transfer_id' => $transferId,
            'action' => $action,
            'quantity' => $quantity,
            'code' => $item->code,
            'user_id' => auth()->id(),
            'station' => auth()->user()?->station,
            'note' => $note,
        ]);
    }
}
