<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BoxStatus;
use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockBox;
use App\Models\StockCorrection;
use App\Models\StockItem;
use App\Models\StockScan;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stock-take: count everything physically at one location and compare it with
 * what the system expects to be there (sealed, in use, or empty awaiting refill).
 */
class StockTakeService
{
    /** Statuses of items that should physically be at a location. */
    public const ON_SITE = [ItemStatus::IN_STOCK, ItemStatus::IN_USE, ItemStatus::FINISHED_REFILL];

    public function expected(int $warehouseId): Builder
    {
        return StockItem::query()->where('warehouse_id', $warehouseId)->whereIn('status', self::ON_SITE);
    }

    public function start(int $warehouseId): StockTake
    {
        if (StockTake::query()->where('warehouse_id', $warehouseId)->where('status', StockTake::OPEN)->exists()) {
            throw new RuntimeException(__('A stock-take is already in progress at this location.'));
        }

        $take = StockTake::query()->create([
            'warehouse_id' => $warehouseId,
            'started_by' => auth()->id(),
            'status' => StockTake::OPEN,
            'expected' => $this->expected($warehouseId)->count(),
        ]);
        $take->update(['reference' => 'ST-' . str_pad((string) $take->id, 6, '0', STR_PAD_LEFT)]);

        return $take;
    }

    /**
     * Count a scanned unit or box. A box QR counts every unit still inside it.
     *
     * @return array{type: string, message: string}
     */
    public function scan(StockTake $take, string $code, StockTrackingService $tracking): array
    {
        if ($take->status !== StockTake::OPEN) {
            throw new RuntimeException(__('This stock-take is finished.'));
        }

        if ($box = $tracking->findBox($code)) {
            $units = StockItem::query()->where('box_id', $box->id)->get();
            if ($units->isEmpty()) {
                return ['type' => 'warning', 'message' => __('Box :code is empty. Scan the units instead.', ['code' => $box->code])];
            }
            $added = $units->sum(fn (StockItem $item) => $this->record($take, $item) === 'new' ? 1 : 0);

            return ['type' => 'success', 'message' => __('Box :code: :n units counted (:new new).', ['code' => $box->code, 'n' => $units->count(), 'new' => $added])];
        }

        $item = $tracking->findItem($code);
        if (! $item) {
            return ['type' => 'error', 'message' => __('No item found for code :code.', ['code' => $code])];
        }

        $result = $this->record($take, $item);
        $line = $take->lines()->where('stock_item_id', $item->id)->first();

        return match (true) {
            $result === 'again' => ['type' => 'warning', 'message' => __(':code was already counted.', ['code' => $item->code])],
            $line->result === StockTakeItem::UNEXPECTED => ['type' => 'warning', 'message' => __(':code – :product is not expected here: the system has it as :status (:where).', [
                'code' => $item->code, 'product' => $item->product?->name, 'status' => mb_strtolower($item->status->label()), 'where' => $item->whereabouts() ?: '—',
            ])],
            default => ['type' => 'success', 'message' => __(':code – :product counted.', ['code' => $item->code, 'product' => $item->product?->name])],
        };
    }

    /** @return 'new'|'again' */
    private function record(StockTake $take, StockItem $item): string
    {
        if ($take->lines()->where('stock_item_id', $item->id)->exists()) {
            return 'again';
        }

        $expected = $item->warehouse_id === $take->warehouse_id && in_array($item->status, self::ON_SITE, true);

        $take->lines()->create([
            'stock_item_id' => $item->id,
            'result' => $expected ? StockTakeItem::FOUND : StockTakeItem::UNEXPECTED,
            'system_status' => $item->status->value,
            'system_warehouse_id' => $item->warehouse_id,
            'scanned_at' => now(),
        ]);

        return 'new';
    }

    /** Units expected at the location that have not been scanned yet. */
    public function notYetCounted(StockTake $take): Builder
    {
        return $this->expected($take->warehouse_id)
            ->whereNotIn('id', $take->lines()->select('stock_item_id'));
    }

    /**
     * Close the count. Items not found are listed; with $markMissing they are
     * also marked missing (taken off the stock count) and the change is logged.
     */
    public function finish(StockTake $take, bool $markMissing, ?string $note = null): StockTake
    {
        return DB::transaction(function () use ($take, $markMissing, $note): StockTake {
            $take = StockTake::query()->lockForUpdate()->findOrFail($take->id);

            if ($take->status !== StockTake::OPEN) {
                throw new RuntimeException(__('This stock-take is already finished.'));
            }

            $notFound = $this->notYetCounted($take)->get();

            foreach ($notFound as $item) {
                $take->lines()->create([
                    'stock_item_id' => $item->id,
                    'result' => StockTakeItem::NOT_FOUND,
                    'system_status' => $item->status->value,
                    'system_warehouse_id' => $item->warehouse_id,
                ]);
            }

            // Everything found gets a "counted" record in its history.
            $found = StockItem::query()->whereIn('id', $take->lines()->where('result', StockTakeItem::FOUND)->select('stock_item_id'))->get();
            foreach ($found as $item) {
                $this->log($item, $take->warehouse_id, ScanAction::COUNTED, $take->reference);
            }

            if ($markMissing && $notFound->isNotEmpty()) {
                foreach ($notFound as $item) {
                    $this->markMissing($item, $take);
                }

                StockCorrection::query()->create([
                    'type' => StockCorrection::STOCK_TAKE,
                    'user_id' => auth()->id(),
                    'warehouse_id' => $take->warehouse_id,
                    'reference' => $take->reference,
                    'reason' => $note ?: __('Not found at stock-take'),
                    'details' => ['missing' => $notFound->pluck('code')->all()],
                ]);
            }

            $counts = $take->lines()->selectRaw('result, COUNT(*) as n')->groupBy('result')->pluck('n', 'result');

            $take->update([
                'status' => StockTake::FINISHED,
                'finished_by' => auth()->id(),
                'finished_at' => now(),
                'note' => $note,
                'found' => (int) ($counts[StockTakeItem::FOUND] ?? 0),
                'not_found' => (int) ($counts[StockTakeItem::NOT_FOUND] ?? 0),
                'unexpected' => (int) ($counts[StockTakeItem::UNEXPECTED] ?? 0),
            ]);

            return $take;
        });
    }

    public function cancel(StockTake $take): void
    {
        if ($take->status !== StockTake::OPEN) {
            throw new RuntimeException(__('This stock-take is already finished.'));
        }

        $take->lines()->delete();
        $take->update(['status' => StockTake::CANCELLED, 'finished_by' => auth()->id(), 'finished_at' => now()]);
    }

    private function markMissing(StockItem $item, StockTake $take): void
    {
        // Sealed and in-use items count as stock; empties awaiting refill do not.
        if (in_array($item->status, ItemStatus::countsAsStock(), true)) {
            ProductWarehouse::query()->where('product_id', $item->product_id)->where('warehouse_id', $take->warehouse_id)->decrement('qty');
            Product::query()->whereKey($item->product_id)->decrement('quantity');
        }

        $this->log($item, $take->warehouse_id, ScanAction::MARKED_MISSING, __('Not found at stock-take :ref', ['ref' => $take->reference]));

        $box = $item->box_id ? StockBox::query()->find($item->box_id) : null;
        $item->update(['status' => ItemStatus::MISSING, 'warehouse_id' => null, 'box_id' => null]);

        if ($box && $box->status === BoxStatus::PACKED) {
            $box->update(['status' => BoxStatus::OPENED]);
        }
        if ($box && ! $box->items()->exists()) {
            $box->update(['status' => BoxStatus::EMPTY]);
        }
    }

    private function log(StockItem $item, int $warehouseId, ScanAction $action, ?string $note): void
    {
        StockScan::query()->create([
            'product_id' => $item->product_id,
            'stock_item_id' => $item->id,
            'box_id' => $item->box_id,
            'warehouse_id' => $warehouseId,
            'action' => $action,
            'quantity' => 1,
            'code' => $item->code,
            'user_id' => auth()->id(),
            'station' => auth()->user()?->station,
            'note' => $note,
        ]);
    }
}
