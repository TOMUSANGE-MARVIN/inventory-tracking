<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upstream stores many amounts as DECIMAL(8,2) (max 999,999.99). Everyday UGX
 * amounts exceed that, so widen every money column to DECIMAL(15,2), keeping
 * each column's nullability and default.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'cash_registers' => ['cash_in_hand', 'sent', 'recieved'],
        'expenses' => ['amount'],
        'financial_snapshots' => ['break_even_point_units', 'average_order_value'],
        'movements' => ['price'],
        'product_warehouse' => ['price', 'cost', 'old_price'],
        'purchase_details' => ['price', 'unit_price'],
        'purchase_payments' => ['amount'],
        'purchase_return_details' => ['price', 'unit_price'],
        'purchases' => ['tax_amount', 'discount_amount', 'shipping_amount', 'total_amount', 'paid_amount', 'due_amount'],
        'quotation_details' => ['price', 'unit_price'],
        'sale_details' => ['price', 'unit_price'],
        'sale_payments' => ['amount'],
        'sale_return_details' => ['price', 'unit_price'],
        'sale_return_payments' => ['amount'],
        'shippings' => ['cost'],
        'transfers' => ['total_cost', 'total_amount'],
    ];

    public function up(): void
    {
        $this->resize('DECIMAL(15,2)');
    }

    public function down(): void
    {
        // Narrowing back could truncate real amounts; leave the wider columns in place.
    }

    private function resize(string $type): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $info = DB::selectOne(
                    'SELECT IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$table, $column],
                );

                if (! $info) {
                    continue;
                }

                $sql = sprintf('ALTER TABLE `%s` MODIFY `%s` %s %s', $table, $column, $type, $info->IS_NULLABLE === 'YES' ? 'NULL' : 'NOT NULL');
                if ($info->COLUMN_DEFAULT !== null) {
                    $sql .= ' DEFAULT ' . DB::getPdo()->quote((string) $info->COLUMN_DEFAULT);
                }

                DB::statement($sql);
            }
        }
    }
};
