<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration with the client's existing inventory system.
 *
 * The existing system owns products, branches, users, services, purchase
 * orders, requisitions/transfers and the official stock levels; it sends them
 * to this module's API. Each record keeps the existing system's ID in
 * `external_id`, so both sides refer to the same thing by the same ID.
 *
 * This module owns barcodes and scans. What happens to items (dispatched,
 * received, missing, finished, refilled) is written to `integration_events`,
 * which the existing system reads in order through the updates feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $blueprint): void {
            $blueprint->string('external_id', 64)->nullable()->unique();
            $blueprint->boolean('is_head_office')->default(false);
        });

        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->string('external_id', 64)->nullable()->unique();
            $blueprint->string('external_role', 64)->nullable();
        });

        Schema::table('products', function (Blueprint $blueprint): void {
            $blueprint->string('external_id', 64)->nullable()->unique();
            $blueprint->decimal('grams_per_unit', 12, 2)->nullable();
        });

        // Services and the grams of each product a service uses (set by their admin).
        Schema::create('services', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('external_id', 64)->unique();
            $blueprint->string('name');
            $blueprint->boolean('status')->default(true);
            $blueprint->timestamps();
        });

        Schema::create('service_products', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('service_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $blueprint->decimal('grams', 12, 2)->default(0);
            $blueprint->timestamps();
            $blueprint->unique(['service_id', 'product_id']);
        });

        // The official stock levels, as last reported by the existing system.
        Schema::create('external_stock_levels', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $blueprint->decimal('quantity', 15, 2)->default(0);
            $blueprint->decimal('grams', 15, 2)->nullable();
            $blueprint->timestamp('reported_at')->nullable();
            $blueprint->timestamps();
            $blueprint->unique(['warehouse_id', 'product_id']);
        });

        // Purchase orders raised in the existing system; items are scanned in against them.
        Schema::create('purchase_orders', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('external_id', 64)->unique();
            $blueprint->string('reference')->nullable();
            $blueprint->string('supplier_name')->nullable();
            $blueprint->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $blueprint->string('status', 32)->nullable();
            $blueprint->date('ordered_at')->nullable();
            $blueprint->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $blueprint->decimal('quantity', 15, 2);
            $blueprint->timestamps();
        });

        // Requisitions / transfers raised in the existing system; dispatch scans are matched to them.
        Schema::create('transfer_requests', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('external_id', 64)->unique();
            $blueprint->string('reference')->nullable();
            $blueprint->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $blueprint->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $blueprint->string('status', 32)->nullable();
            $blueprint->date('requested_at')->nullable();
            $blueprint->timestamps();
        });

        Schema::create('transfer_request_lines', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('transfer_request_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $blueprint->decimal('quantity', 15, 2);
            $blueprint->timestamps();
        });

        Schema::table('transfers', function (Blueprint $blueprint): void {
            $blueprint->foreignId('transfer_request_id')->nullable()->constrained()->nullOnDelete();
        });

        // Outbox: what happened in the module, read in order by the existing system.
        Schema::create('integration_events', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('type', 48)->index();
            $blueprint->json('payload');
            $blueprint->timestamp('occurred_at');
            $blueprint->timestamps();
        });

        // Every API call, so a mismatch between the systems can be traced to its source.
        Schema::create('integration_logs', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('method', 8);
            $blueprint->string('path');
            $blueprint->unsignedSmallInteger('status');
            $blueprint->unsignedInteger('records')->nullable();
            $blueprint->text('error')->nullable();
            $blueprint->unsignedInteger('duration_ms')->default(0);
            $blueprint->string('ip', 45)->nullable();
            $blueprint->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_events');
        Schema::table('transfers', fn (Blueprint $b) => $b->dropConstrainedForeignId('transfer_request_id'));
        Schema::dropIfExists('transfer_request_lines');
        Schema::dropIfExists('transfer_requests');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('external_stock_levels');
        Schema::dropIfExists('service_products');
        Schema::dropIfExists('services');
        Schema::table('products', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['external_id']);
            $blueprint->dropColumn(['external_id', 'grams_per_unit']);
        });
        Schema::table('users', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['external_id']);
            $blueprint->dropColumn(['external_id', 'external_role']);
        });
        Schema::table('warehouses', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['external_id']);
            $blueprint->dropColumn(['external_id', 'is_head_office']);
        });
    }
};
