<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ExternalStockLevel;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Service;
use App\Models\TransferRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Integration\ExternalIds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Records the existing system sends to the module. Every endpoint takes a batch
 * {"data": [...]} and creates or updates each record by the existing system's
 * own ID, so sending the same batch twice changes nothing. A batch is saved
 * whole or not at all.
 */
class InboundController extends Controller
{
    /** IDs from the existing system: numbers or short strings. */
    private const ID = ['required', 'regex:/^[A-Za-z0-9._:-]{1,64}$/'];

    private const OPTIONAL_ID = ['nullable', 'regex:/^[A-Za-z0-9._:-]{1,64}$/'];

    public function ping(): JsonResponse
    {
        return response()->json(['ok' => true, 'api_version' => 'v1', 'time' => now()->toIso8601String()]);
    }

    public function branches(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'id' => self::ID,
            'name' => ['required', 'string', 'max:192'],
            'phone' => ['nullable', 'string', 'max:192'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_head_office' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (collect($records)->where('is_head_office', true)->count() > 1) {
            throw ValidationException::withMessages(['data' => 'Only one branch can be the Head Office.']);
        }

        return $this->save($records, function (array $r): bool {
            $branch = Warehouse::query()->firstOrNew(['external_id' => (string) $r['id']]);
            $branch->fill([
                'name' => $r['name'],
                'phone' => $r['phone'] ?? $branch->phone,
                'address' => $r['address'] ?? $branch->address,
                'status' => $r['active'] ?? $branch->status ?? true,
            ]);

            if (array_key_exists('is_head_office', $r)) {
                $branch->is_head_office = (bool) $r['is_head_office'];
            }

            $created = ! $branch->exists;
            $branch->save();

            if ($branch->is_head_office) {
                Warehouse::query()->whereKeyNot($branch->id)->update(['is_head_office' => false]);
            }

            return $created;
        });
    }

    public function users(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'id' => self::ID,
            'name' => ['required', 'string', 'max:192'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:64'],
            'branch_ids' => ['sometimes', 'array'],
            'branch_ids.*' => self::ID,
            'active' => ['sometimes', 'boolean'],
        ]);

        $branches = ExternalIds::resolve(Warehouse::class, collect($records)->pluck('branch_ids')->flatten(), 'branch_id', '/branches');
        $roleMap = (array) config('integration.roles');

        return $this->save($records, function (array $r) use ($branches, $roleMap): bool {
            $id = (string) $r['id'];
            $email = isset($r['email']) ? Str::lower($r['email']) : null;

            if ($email && User::query()->where('email', $email)->where(fn ($q) => $q->whereNull('external_id')->orWhere('external_id', '!=', $id))->exists()) {
                throw ValidationException::withMessages(['data' => "User {$id}: the email {$email} already belongs to another user."]);
            }

            $user = User::query()->firstOrNew(['external_id' => $id]);
            $created = ! $user->exists;

            $user->fill([
                'name' => $r['name'],
                // Our users table needs a unique email; users without one get a placeholder.
                'email' => $email ?? $user->email ?? "user-{$id}@external.invalid",
                'phone' => $r['phone'] ?? $user->phone,
                'status' => $r['active'] ?? $user->status ?? true,
                'external_role' => $r['role'] ?? $user->external_role,
            ]);

            if ($created) {
                // Signing in to the module is settled separately; nobody can sign in with this.
                $user->password = Hash::make(Str::random(40));
            }

            $user->save();

            if (array_key_exists('branch_ids', $r)) {
                $user->warehouses()->sync(collect($r['branch_ids'])->map(fn ($b) => $branches[(string) $b])->unique()->all());
            }

            $mapped = $roleMap[$user->external_role] ?? null;
            if ($mapped && Role::query()->where('name', $mapped)->exists()) {
                $user->syncRoles([$mapped]);
            }

            return $created;
        });
    }

    public function products(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'id' => self::ID,
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:192'],
            'unit' => ['nullable', 'string', 'max:32'],
            'grams_per_unit' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_refillable' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);

        return $this->save($records, function (array $r): bool {
            $id = (string) $r['id'];
            $product = Product::withTrashed()->firstOrNew(['external_id' => $id]);
            $created = ! $product->exists;

            $product->fill([
                'name' => $r['name'],
                'unit' => $r['unit'] ?? $product->unit,
                'grams_per_unit' => array_key_exists('grams_per_unit', $r) ? $r['grams_per_unit'] : $product->grams_per_unit,
                'cost' => $r['cost'] ?? $product->cost ?? 0,
                'price' => $r['price'] ?? $product->price ?? 0,
                'status' => $r['active'] ?? $product->status ?? true,
            ]);

            if (array_key_exists('is_refillable', $r)) {
                $product->is_refillable = (bool) $r['is_refillable'];
            }

            if (isset($r['category']) || $created) {
                $product->category_id = $this->category($r['category'] ?? null)->id;
            }

            if ($created) {
                $product->slug = Str::slug($r['name']) . '-' . Str::slug($id);
            }

            $product->save();

            return $created;
        });
    }

    public function services(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'id' => self::ID,
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'products' => ['present', 'array'],
            'products.*.product_id' => self::ID,
            'products.*.grams' => ['required', 'numeric', 'min:0'],
        ]);

        $products = ExternalIds::resolve(Product::class, collect($records)->pluck('products')->flatten(1)->pluck('product_id'), 'product_id', '/products');

        return $this->save($records, function (array $r) use ($products): bool {
            $service = Service::query()->firstOrNew(['external_id' => (string) $r['id']]);
            $created = ! $service->exists;
            $service->fill(['name' => $r['name'], 'status' => $r['active'] ?? $service->status ?? true])->save();

            $service->products()->sync(collect($r['products'])->mapWithKeys(fn ($p) => [
                $products[(string) $p['product_id']] => ['grams' => $p['grams']],
            ])->all());

            return $created;
        });
    }

    public function stockLevels(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'branch_id' => self::ID,
            'product_id' => self::ID,
            'quantity' => ['required', 'numeric'],
            'grams' => ['nullable', 'numeric'],
            'as_of' => ['nullable', 'date'],
        ], key: fn (array $r) => $r['branch_id'] . '|' . $r['product_id']);

        $branches = ExternalIds::resolve(Warehouse::class, array_column($records, 'branch_id'), 'branch_id', '/branches');
        $products = ExternalIds::resolve(Product::class, array_column($records, 'product_id'), 'product_id', '/products');

        return $this->save($records, function (array $r) use ($branches, $products): bool {
            $level = ExternalStockLevel::query()->updateOrCreate(
                ['warehouse_id' => $branches[(string) $r['branch_id']], 'product_id' => $products[(string) $r['product_id']]],
                ['quantity' => $r['quantity'], 'grams' => $r['grams'] ?? null, 'reported_at' => $r['as_of'] ?? now()],
            );

            return $level->wasRecentlyCreated;
        });
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'id' => self::ID,
            'reference' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'branch_id' => self::OPTIONAL_ID,
            'status' => ['nullable', 'string', 'max:32'],
            'ordered_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => self::ID,
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $branches = ExternalIds::resolve(Warehouse::class, array_column($records, 'branch_id'), 'branch_id', '/branches');
        $products = ExternalIds::resolve(Product::class, collect($records)->pluck('lines')->flatten(1)->pluck('product_id'), 'product_id', '/products');

        return $this->save($records, function (array $r) use ($branches, $products): bool {
            $order = PurchaseOrder::query()->firstOrNew(['external_id' => (string) $r['id']]);
            $created = ! $order->exists;
            $order->fill([
                'reference' => $r['reference'] ?? $order->reference,
                'supplier_name' => $r['supplier_name'] ?? $order->supplier_name,
                'warehouse_id' => isset($r['branch_id']) ? $branches[(string) $r['branch_id']] : $order->warehouse_id,
                'status' => $r['status'] ?? $order->status,
                'ordered_at' => $r['ordered_at'] ?? $order->ordered_at,
            ])->save();

            $this->replaceLines($order, $r['lines'], $products);

            return $created;
        });
    }

    public function requisitions(Request $request): JsonResponse
    {
        $records = $this->batch($request, [
            'id' => self::ID,
            'reference' => ['nullable', 'string', 'max:255'],
            'from_branch_id' => self::OPTIONAL_ID,
            'to_branch_id' => self::ID,
            'status' => ['nullable', 'string', 'max:32'],
            'requested_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => self::ID,
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $branches = ExternalIds::resolve(Warehouse::class, collect($records)->flatMap(fn ($r) => [$r['from_branch_id'] ?? null, $r['to_branch_id']]), 'branch_id', '/branches');
        $products = ExternalIds::resolve(Product::class, collect($records)->pluck('lines')->flatten(1)->pluck('product_id'), 'product_id', '/products');

        return $this->save($records, function (array $r) use ($branches, $products): bool {
            if ((string) ($r['from_branch_id'] ?? '') === (string) $r['to_branch_id']) {
                throw ValidationException::withMessages(['data' => "Requisition {$r['id']}: from and to branch must be different."]);
            }

            $requisition = TransferRequest::query()->firstOrNew(['external_id' => (string) $r['id']]);
            $created = ! $requisition->exists;
            $requisition->fill([
                'reference' => $r['reference'] ?? $requisition->reference,
                'from_warehouse_id' => isset($r['from_branch_id']) ? $branches[(string) $r['from_branch_id']] : $requisition->from_warehouse_id,
                'to_warehouse_id' => $branches[(string) $r['to_branch_id']],
                'status' => $r['status'] ?? $requisition->status,
                'requested_at' => $r['requested_at'] ?? $requisition->requested_at,
            ])->save();

            $this->replaceLines($requisition, $r['lines'], $products);

            return $created;
        });
    }

    /**
     * Validate a batch: {"data": [ ...records ]}, each record unique by its key.
     *
     * @param array<string, mixed>          $rules rules for one record
     * @param (callable(array): string)|null $key   what makes a record unique (default: its id)
     *
     * @return array<int, array<string, mixed>>
     */
    private function batch(Request $request, array $rules, ?callable $key = null): array
    {
        $max = (int) config('integration.max_batch', 500);

        $validated = $request->validate(
            ['data' => ['required', 'array', 'list', 'min:1', "max:{$max}"]]
            + collect($rules)->mapWithKeys(fn ($rule, $field) => ["data.*.{$field}" => $rule])->all(),
        );

        $records = $validated['data'];
        $keys = array_map($key ?? fn (array $r) => (string) $r['id'], $records);

        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['data' => 'The same record appears more than once in this batch.']);
        }

        return $records;
    }

    /** Save every record in one transaction; $saveOne returns true when it created the record. */
    private function save(array $records, callable $saveOne): JsonResponse
    {
        $created = DB::transaction(fn () => collect($records)->filter(fn (array $r) => $saveOne($r))->count());

        return response()->json([
            'received' => count($records),
            'created' => $created,
            'updated' => count($records) - $created,
        ]);
    }

    /** Lines are always sent in full: replace what was there. */
    private function replaceLines(PurchaseOrder|TransferRequest $document, array $lines, array $products): void
    {
        $document->lines()->delete();
        $document->lines()->createMany(array_map(fn ($line) => [
            'product_id' => $products[(string) $line['product_id']],
            'quantity' => $line['quantity'],
        ], $lines));
    }

    private function category(?string $name): Category
    {
        $name = trim((string) $name) ?: 'Uncategorised';

        return Category::query()->firstOrCreate(
            ['name' => $name],
            ['code' => Str::upper(Str::limit(Str::slug($name), 40, '')) . '-' . Str::upper(Str::random(4))],
        );
    }
}
