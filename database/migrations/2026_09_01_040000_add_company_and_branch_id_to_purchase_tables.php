<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purchase tables that already carry tenant_id. All Purchase domain
     * tables currently have a tenant_id column, so none are skipped here
     * (unlike the CRM migration, which had to exclude quotation_items and
     * customer_payments).
     */
    private array $tables = [
        'purchase_requisitions',
        'purchase_requisition_items',
        'purchase_rfqs',
        'purchase_rfq_items',
        'purchase_rfq_vendors',
        'purchase_rfq_vendor_rates',
        'purchase_orders',
        'purchase_order_items',
        'goods_receipt_notes',
        'goods_receipt_note_items',
        'purchase_returns',
        'purchase_return_items',
        'purchase_advance_payments',
        'vendor_bills',
        'vendor_bill_items',
        'vendor_payments',
        'vendor_payment_allocations',
        'landed_cost_vouchers',
        'landed_cost_receipts',
        'landed_cost_expenses',
        'landed_cost_items',
        'approval_reminders',
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
