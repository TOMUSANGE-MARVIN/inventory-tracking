<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BoxStatus;
use App\Enums\ItemStatus;
use App\Enums\ScanAction;
use App\Enums\TransferStatus;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\StockBox;
use App\Models\StockItem;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTrackingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * DEMO ONLY: simulates three weeks of salon activity (deliveries, dispatches,
 * branch receipts, usage, the odd missing unit) through the real tracking
 * service with backdated clocks, so the dashboard has something to show.
 *
 * php artisan db:seed --class=SparkleActivitySeeder
 */
class SparkleActivitySeeder extends Seeder
{
    public function run(StockTrackingService $tracking): void
    {
        mt_srand(2026);
        Auth::login(User::role('admin')->firstOrFail());

        $hq = $tracking->headOffice();
        $branches = Warehouse::query()->whereKeyNot($hq->id)->orderBy('id')->get();
        $products = Product::query()->get();

        foreach (range(20, 0) as $daysAgo) {
            $day = today()->subDays($daysAgo);

            // A delivery reaches Head Office every few days.
            if ($daysAgo % 4 === 0) {
                foreach (range(1, mt_rand(1, 3)) as $box) {
                    $this->at($day, 8, fn () => $tracking->receiveBoxedDelivery($hq->id, [$products->random()->id => mt_rand(6, 12)], 'B' . $day->format('md'), 'Supplier delivery'));
                }
            }

            // Yesterday's transfers are scanned in at the branch (now and then one unit goes missing).
            Transfer::query()->where('status', TransferStatus::IN_TRANSIT)->where('dispatched_at', '<', $day)->get()
                ->each(fn (Transfer $t) => $this->at($day, 9, function () use ($tracking, $t) {
                    $ids = $t->items()->pluck('stock_items.id')->all();
                    if (count($ids) > 3 && mt_rand(1, 6) === 1) {
                        array_pop($ids);
                    }
                    $tracking->receive($t, $ids);
                }));

            // Head Office sends one or two branches a top-up.
            foreach ($branches->random(mt_rand(1, 2)) as $branch) {
                // A sealed box or two, plus a few loose units.
                $boxIds = StockBox::query()->where('warehouse_id', $hq->id)->where('status', BoxStatus::PACKED)
                    ->inRandomOrder()->limit(mt_rand(0, 2))->pluck('id')->all();
                $ids = StockItem::query()->where('warehouse_id', $hq->id)->where('status', ItemStatus::IN_STOCK)->whereNull('box_id')
                    ->inRandomOrder()->limit(mt_rand(0, 4))->pluck('id')->all();
                if ($ids || $boxIds) {
                    $this->at($day, 11, fn () => $tracking->dispatch($hq->id, $branch->id, $ids, null, $boxIds));
                }
            }

            // Branches open stock for clients and finish some open containers.
            foreach ($branches as $branch) {
                StockItem::query()->where('warehouse_id', $branch->id)->where('status', ItemStatus::IN_STOCK)
                    ->inRandomOrder()->limit(mt_rand(0, 3))->get()
                    ->each(fn ($item) => $this->at($day, 14, fn () => $tracking->record($item, ScanAction::IN_USE)));

                StockItem::query()->where('warehouse_id', $branch->id)->where('status', ItemStatus::IN_USE)
                    ->where('updated_at', '<', $day)->inRandomOrder()->limit(mt_rand(0, 2))->get()
                    ->each(fn ($item) => $this->at($day, 17, fn () => $tracking->finish($item)));
            }
        }

        // Re-order levels so low-stock alerts have something to say: Head Office keeps a buffer, branches a couple.
        ProductWarehouse::query()->where('warehouse_id', $hq->id)->update(['stock_alert' => 20]);
        ProductWarehouse::query()->where('warehouse_id', '!=', $hq->id)->update(['stock_alert' => 1]);

        Auth::logout();

        // Workers at each branch, and their client scans on the items opened above.
        $this->call(SparkleUsageSeeder::class);
    }

    private function at(Carbon $day, int $hour, callable $fn): void
    {
        $when = $day->copy()->setTime($hour, mt_rand(0, 59));
        // Today's events must not land later than the current time.
        $when = $when->isFuture() ? now()->subMinutes(mt_rand(5, 120)) : $when;
        Carbon::setTestNow($when);
        $fn();
        Carbon::setTestNow();
    }
}
