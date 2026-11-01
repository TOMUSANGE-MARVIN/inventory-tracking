<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ScanAction;
use App\Models\StockScan;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DEMO ONLY: two workers per branch (one per station) and a history of client
 * scans for every item opened at a branch, from the day it was opened until
 * it was finished (or today). Safe to re-run: earlier demo client scans are replaced.
 */
class SparkleUsageSeeder extends Seeder
{
    private const STATIONS = [
        ['Hair chair 1', ['Aisha', 'Brenda', 'Carol', 'Diana', 'Esther', 'Faith', 'Grace']],
        ['Nail bar', ['Hadijah', 'Irene', 'Joan', 'Kevin', 'Lydia', 'Mary', 'Nora']],
    ];

    public function run(): void
    {
        mt_srand(2027);
        $hq = Warehouse::query()->orderBy('id')->first();
        $branches = Warehouse::query()->whereKeyNot($hq->id)->orderBy('id')->get();

        // DEMO ONLY: e.g. kansanga.hair-chair-1@sparkle.test, same demo password as the admin.
        $workers = $branches->values()->mapWithKeys(fn (Warehouse $branch, int $i) => [$branch->id => collect(self::STATIONS)->map(function ($s) use ($branch, $i) {
            [$station, $names] = $s;
            $slug = Str::slug(Str::before($branch->name, ' Branch'));
            $user = User::query()->updateOrCreate(
                ['email' => "{$slug}." . Str::slug($station) . '@sparkle.test'],
                ['name' => $names[$i % count($names)] . ' (' . Str::before($branch->name, ' Branch') . ')', 'password' => Hash::make('password'), 'status' => 1, 'is_all_warehouses' => false, 'station' => $station],
            );
            $user->syncRoles(['worker']);
            $user->warehouses()->sync([$branch->id]);

            return $user;
        })]);

        StockScan::query()->where('action', ScanAction::SERVED_CLIENT)->where('note', 'demo')->delete();

        $opened = StockScan::query()->where('action', ScanAction::IN_USE)->whereIn('warehouse_id', $branches->pluck('id'))->get();
        $finished = StockScan::query()->whereIn('action', [ScanAction::DISPOSED, ScanAction::RETURNED_FOR_REFILL])
            ->pluck('created_at', 'stock_item_id');

        $rows = [];
        foreach ($opened as $open) {
            $staff = $workers->get($open->warehouse_id);
            if (! $staff) {
                continue;
            }

            $start = Carbon::parse($open->created_at);
            $end = isset($finished[$open->stock_item_id]) ? Carbon::parse($finished[$open->stock_item_id]) : now();
            $worker = $staff->random();

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                foreach (range(1, mt_rand(1, 4)) as $n) {
                    $when = $day->copy()->setTime(mt_rand(9, 18), mt_rand(0, 59));
                    if ($when->lt($start) || $when->gt($end) || $when->isFuture()) {
                        continue;
                    }
                    $rows[] = [
                        'product_id' => $open->product_id, 'stock_item_id' => $open->stock_item_id, 'box_id' => null,
                        'warehouse_id' => $open->warehouse_id, 'transfer_id' => null, 'action' => ScanAction::SERVED_CLIENT->value,
                        'quantity' => 1, 'code' => $open->code, 'user_id' => $worker->id, 'station' => $worker->station,
                        'note' => 'demo', 'created_at' => $when, 'updated_at' => $when,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StockScan::query()->insert($chunk);
        }

        $this->command?->info(count($rows) . ' demo client scans for ' . $opened->count() . ' opened items.');
    }
}
