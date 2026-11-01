<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTrackingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for the Sparkle Salons prototype: a head office, seven branches
 * and salon products. Products with an EAN code stand in for items that
 * arrive with a manufacturer barcode; SPK- codes are catalogue codes for the
 * rest. Opening stock is created as individually labelled SP- items.
 *
 * php artisan db:seed --class=SparkleDemoSeeder
 */
class SparkleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $locations = ['Head Office', 'Kansanga', 'Ntinda', 'Kololo', 'Muyenga', 'Bugolobi', 'Naalya', 'Entebbe'];

        // Upstream seeds a right-to-left layout in French with Moroccan dirhams; Sparkle is English-only, in UGX.
        $ugx = Currency::query()->updateOrCreate(['code' => 'UGX'], ['name' => 'Ugandan Shilling', 'locale' => 'en_US']);
        Setting::query()->first()?->update([
            'is_rtl' => false,
            'default_language' => 'en',
            'company_name' => 'Sparkle Salons',
            'default_currency_id' => $ugx->id,
            'default_currency_position' => 'left',
        ]);
        Language::query()->where('code', '!=', 'en')->delete();
        Language::query()->where('code', 'en')->update(['is_default' => true]);
        Cache::forget('settings');

        // Reuse the project's demo warehouses (sales data points at them) and add the rest.
        $existing = Warehouse::query()->orderBy('id')->get();

        $warehouses = collect($locations)->map(function (string $name, int $i) use ($existing): Warehouse {
            $warehouse = $existing[$i] ?? new Warehouse;
            $warehouse->fill([
                'name' => $i === 0 ? $name : $name . ' Branch',
                'email' => 'location' . $i . '@sparkle.test',
                'city' => 'Kampala',
                'country' => 'Uganda',
                'phone' => '',
            ])->save();

            return $warehouse;
        });

        // Hide the generic demo products that ship with the project, and their
        // stock rows (the dashboard's stock alerts assume every row has a product).
        $demo = Product::withTrashed()->where('code', 'like', 'PRD%');
        DB::table('product_warehouse')->whereIn('product_id', $demo->pluck('id'))->delete();
        $demo->delete();

        // Super admin: the project's demo admin, across every location.
        $admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();
        $admin->assignRole('admin');
        $warehouses->each(fn ($w) => $w->users()->syncWithoutDetaching([$admin->id]));

        // DEMO ONLY: one branch admin per branch, e.g. kansanga@sparkle.test, same demo password as the admin.
        $warehouses->skip(1)->each(function (Warehouse $branch): void {
            $user = User::query()->updateOrCreate(
                ['email' => Str::slug(Str::before($branch->name, ' Branch')) . '@sparkle.test'],
                ['name' => Str::before($branch->name, ' Branch') . ' Branch Admin', 'password' => Hash::make('password'), 'status' => 1, 'is_all_warehouses' => false],
            );
            $user->syncRoles(['branch-admin']);
            $user->warehouses()->sync([$branch->id]);
        });

        $categories = collect(['Hair care', 'Treatments', 'Nails'])->mapWithKeys(fn (string $name) => [
            $name => Category::query()->firstOrCreate(['code' => Str::upper(Str::slug($name, ''))], ['name' => $name]),
        ]);

        $products = [
            ['Hair care', 'Professional Shampoo 5L', '6001067004516', 'drum', 185000, 40],
            ['Hair care', 'Moisturising Conditioner 5L', '6001067004523', 'drum', 195000, 30],
            ['Hair care', 'Hair Relaxer 1.8kg', '6009695840117', 'tub', 98000, 50],
            ['Hair care', 'Leave-in Conditioner 500ml', '5011321458932', 'bottle', 42000, 80],
            ['Treatments', 'Keratin Treatment 1L', '7898556980227', 'bottle', 320000, 20],
            ['Treatments', 'Protein Hair Mask 1kg', 'SPK-0001', 'tub', 75000, 35],
            ['Treatments', 'Scalp Oil Treatment 250ml', 'SPK-0002', 'bottle', 38000, 60],
            ['Nails', 'Gel Nail Polish – Red', '8809557560041', 'bottle', 25000, 120],
            ['Nails', 'Gel Nail Polish – Nude', '8809557560058', 'bottle', 25000, 120],
            ['Nails', 'Acetone Remover 1L', 'SPK-0003', 'bottle', 18000, 70],
        ];

        // Refillable: drums/jerrycans and the acetone refill bottle go back for a refill when empty.
        $refillable = ['6001067004516', '6001067004523', 'SPK-0003'];
        Product::withTrashed()->whereIn('code', $refillable)->update(['is_refillable' => true]);

        $headOffice = $warehouses->first();

        $tracking = app(StockTrackingService::class);

        foreach ($products as [$category, $name, $code, $unit, $price, $qty]) {
            if (Product::withTrashed()->where('code', $code)->exists()) {
                continue;
            }

            $id = (string) Str::uuid();

            DB::table('products')->insert([
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'category_id' => $categories[$category]->id,
                'unit' => $unit,
                'is_refillable' => in_array($code, $refillable, true),
                'price' => $price,
                'cost' => (int) ($price * 0.7),
                'quantity' => 0,
                'barcode_symbology' => str_starts_with($code, 'SPK') ? 'C128' : 'EAN13',
                'status' => true,
                'slug' => Str::slug($name) . '-' . $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Opening stock at head office, one labelled item per unit, dated before the demo activity window.
            Carbon::setTestNow(now()->subDays(28)->setTime(9, 0));
            if ($unit === 'drum') {
                // Drums come unboxed, like jerrycans: a barcode each.
                $tracking->receiveArrival(Product::query()->findOrFail($id), $headOffice->id, $qty, 'OPENING', 'Opening stock');
            } else {
                // Everything else arrives in sealed boxes of up to 10.
                for ($left = $qty; $left > 0; $left -= 10) {
                    $tracking->receiveBoxedDelivery($headOffice->id, [$id => min(10, $left)], 'OPENING', 'Opening stock');
                }
            }
            Carbon::setTestNow();
        }
    }
}
