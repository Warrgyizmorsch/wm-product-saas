<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM tables that already carry tenant_id. quotation_items and
     * customer_payments are excluded — they have no tenant_id column today
     * (a pre-existing gap outside this migration's scope) so there is
     * nothing to derive a default company/branch from for them yet.
     */
    private array $tables = [
        'leads',
        'customers',
        'lead_followups',
        'quotations',
        'lead_histories',
        'lead_documents',
        'crm_accounts',
        'crm_contacts',
        'crm_deals',
        'lead_statuses',
        'deal_statuses',
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
