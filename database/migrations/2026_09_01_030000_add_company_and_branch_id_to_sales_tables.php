<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sales tables that already carry tenant_id — either from their original
     * create migration, or backfilled by
     * 2026_07_15_000002_add_tenant_isolation_to_sales_crm_purchase_line_items.php
     * (which added tenant_id to the line-item tables: dispatch_order_items,
     * invoice_items, material_requirement_items i.e. delivery_order_items,
     * and sales_order_items). Every Sales table has tenant_id today.
     *
     * sales_order_allocations is excluded: it was dropped entirely by
     * 2026_07_09_100000_remove_replenishment_planning.php (the model class
     * still exists in code, but the backing table no longer does), so there
     * is nothing to add columns to.
     */
    private array $tables = [
        'customer_payments',
        'dispatch_orders',
        'dispatch_order_items',
        'invoices',
        'invoice_items',
        'material_requirements',
        'material_requirement_items',
        'payment_allocations',
        'sales_orders',
        'sales_order_items',
        'sales_returns',
        'sales_return_items',
        'transporters',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'company_id')) {
                    $blueprint->foreignId('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'branch_id')) {
                    $blueprint->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
                }
            });
        }

        $defaultCompanies = DB::table('companies')->where('is_default', true)->pluck('id', 'tenant_id');

        foreach ($defaultCompanies as $tenantId => $companyId) {
            $defaultBranchId = DB::table('branches')->where('company_id', $companyId)->where('is_default', true)->value('id');

            foreach ($this->tables as $table) {
                DB::table($table)->where('tenant_id', $tenantId)->whereNull('company_id')->update(['company_id' => $companyId]);

                if ($defaultBranchId !== null) {
                    DB::table($table)->where('tenant_id', $tenantId)->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'branch_id')) {
                    $blueprint->dropConstrainedForeignId('branch_id');
                }

                if (Schema::hasColumn($table, 'company_id')) {
                    $blueprint->dropConstrainedForeignId('company_id');
                }
            });
        }
    }
};
