<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects tables that already carry tenant_id. All Projects tables
     * have tenant_id today, so none are excluded.
     */
    private array $tables = [
        'projects',
        'project_activity_logs',
        'project_members',
        'project_milestones',
        'project_task_lists',
        'project_tasks',
        'project_sub_tasks',
        'project_task_dependencies',
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
