<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operational and transactional production tables that carry tenant_id.
     * Masters (BOMs, Routings, Work Centers, Machines, Shifts, Calendars, etc.)
     * are excluded so they remain shared across branches.
     */
    private array $tables = [
        'production_plans',
        'production_plan_requirements',
        'production_plan_operations',
        'production_orders',
        'production_order_operations',
        'production_order_reservations',
        'production_order_issues',
        'production_order_issue_batches',
        'production_order_receipts',
        'production_order_scraps',
        'production_order_reworks',
        'production_order_requests',
        'production_order_progress_logs',
        'production_schedules',
        'production_schedule_operations',
        'production_requisition_slips',
        'production_requisition_slip_items',
        'production_quality_inspections',
        'production_quality_inspection_results',
        'production_wips',
        'production_wip_transactions',
        'production_cost_adjustments',
        'production_maintenance_work_orders',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'company_id')) {
                    $blueprint->foreignId('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
                }

                if (! Schema::hasColumn($table, 'branch_id')) {
                    $blueprint->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
                }
            });
        }

        $tenants = DB::table('tenants')->pluck('id');

        foreach ($tenants as $tenantId) {
            $companyId = DB::table('companies')->where('tenant_id', $tenantId)->where('is_default', true)->value('id')
                ?? DB::table('companies')->where('tenant_id', $tenantId)->value('id');

            if (! $companyId) {
                continue;
            }

            $branchId = DB::table('branches')->where('company_id', $companyId)->where('is_default', true)->value('id')
                ?? DB::table('branches')->where('tenant_id', $tenantId)->value('id');

            foreach ($this->tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                DB::table($table)->where('tenant_id', $tenantId)->whereNull('company_id')->update(['company_id' => $companyId]);

                if ($branchId !== null) {
                    DB::table($table)->where('tenant_id', $tenantId)->whereNull('branch_id')->update(['branch_id' => $branchId]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

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
