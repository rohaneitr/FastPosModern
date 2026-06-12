<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add missing business_id indexes to all tenant-scoped tables.
 *
 * EVIDENCE: Runtime audit confirmed 34 tables with a business_id column
 * but no corresponding index, causing full sequential scans on every
 * multi-tenant WHERE clause.
 *
 * RISK: LOW — adding an index is a non-destructive, fully reversible operation.
 * ROLLBACK: down() method drops all indexes cleanly.
 */
return new class extends Migration
{
    // Only tables confirmed to have business_id AND no existing index
    private array $tables = [
        'barcodes',
        'brands',
        'cash_registers',
        'categories',
        'commercial_quotations',
        'customer_groups',
        'customer_wallets',
        'employees',
        'exam_results',
        'exam_schedules',
        'expense_categories',
        'expenses',
        'invoice_layouts',
        'journal_entries',
        'locations',
        'loyalty_point_ledgers',
        'payrolls',
        'prescriptions',
        'printers',
        'production_orders',
        'restaurant_sessions',
        'restaurant_tables',
        'saas_payment_ledgers',
        'saas_subscriptions',
        'selling_price_groups',
        'stock_ledgers',
        'student_batches',
        'student_enrollments',
        'student_invoices',
        'subscriptions',
        'supplier_ledgers',
        'tax_rates',
        'units',
        'warranties',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Guard: skip if table doesn't exist (module not installed)
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Guard: skip if index already exists (idempotent migration)
            $indexExists = DB::select(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexdef LIKE '%business_id%'",
                [$table]
            );

            if (!empty($indexExists)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('business_id', null);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropIndex(["{$table}_business_id_index"]);
                });
            } catch (\Throwable) {
                // Index may not exist — safe to continue
            }
        }
    }
};
