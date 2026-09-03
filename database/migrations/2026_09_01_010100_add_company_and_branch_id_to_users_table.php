<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
        }

        // Backfill every existing user to their tenant's default company/branch.
        $defaultCompanies = DB::table('companies')->where('is_default', true)->pluck('id', 'tenant_id');

        foreach ($defaultCompanies as $tenantId => $companyId) {
            DB::table('users')->where('tenant_id', $tenantId)->whereNull('company_id')->update(['company_id' => $companyId]);

            $defaultBranchId = DB::table('branches')->where('company_id', $companyId)->where('is_default', true)->value('id');

            if ($defaultBranchId !== null) {
                DB::table('users')->where('tenant_id', $tenantId)->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropConstrainedForeignId('branch_id');
            }

            if (Schema::hasColumn('users', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
