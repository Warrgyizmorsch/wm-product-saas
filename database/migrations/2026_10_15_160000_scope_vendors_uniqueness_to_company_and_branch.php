<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return !empty($indexes);
        } catch (\Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        if (!Schema::hasTable('vendors')) {
            return;
        }

        // 1. Backfill any vendors missing company_id or branch_id with the tenant's primary company/branch
        $tenants = DB::table('tenants')->pluck('id');
        foreach ($tenants as $tId) {
            $company = DB::table('companies')->where('tenant_id', $tId)->where('is_default', true)->first()
                ?? DB::table('companies')->where('tenant_id', $tId)->orderBy('id')->first();

            if ($company) {
                $branch = DB::table('branches')->where('company_id', $company->id)->where('is_default', true)->first()
                    ?? DB::table('branches')->where('company_id', $company->id)->orderBy('id')->first();

                DB::table('vendors')->where('tenant_id', $tId)->whereNull('company_id')->update(['company_id' => $company->id]);
                if ($branch) {
                    DB::table('vendors')->where('tenant_id', $tId)->whereNull('branch_id')->update(['branch_id' => $branch->id]);
                }
            }
        }

        // 2. Drop the old tenant-only unique constraint
        $indexesToDrop = ['vendors_tenant_id_name_unique', 'vendors_name_unique'];
        foreach ($indexesToDrop as $indexName) {
            if ($this->hasIndex('vendors', $indexName)) {
                Schema::table('vendors', function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }
        }

        // 3. Add composite unique constraint: tenant_id, company_id, branch_id, name
        if (!$this->hasIndex('vendors', 'vendors_tenant_co_branch_name_unique')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unique(['tenant_id', 'company_id', 'branch_id', 'name'], 'vendors_tenant_co_branch_name_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('vendors')) {
            return;
        }

        if ($this->hasIndex('vendors', 'vendors_tenant_co_branch_name_unique')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropUnique('vendors_tenant_co_branch_name_unique');
            });
        }

        if (!$this->hasIndex('vendors', 'vendors_tenant_id_name_unique')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unique(['tenant_id', 'name'], 'vendors_tenant_id_name_unique');
            });
        }
    }
};
