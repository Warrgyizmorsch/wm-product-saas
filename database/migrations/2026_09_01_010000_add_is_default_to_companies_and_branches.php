<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'is_default')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('status');
            });
        }

        if (! Schema::hasColumn('branches', 'is_default')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('status');
            });
        }

        // Every tenant needs at least one Company so BelongsToCompany's global
        // scope always has something to resolve against. Create one for any
        // tenant that has none, and flag a default company for every tenant
        // that doesn't already have one flagged.
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $hasDefault = DB::table('companies')->where('tenant_id', $tenantId)->where('is_default', true)->exists();

            if ($hasDefault) {
                continue;
            }

            $existingCompanyId = DB::table('companies')->where('tenant_id', $tenantId)->orderBy('id')->value('id');

            if ($existingCompanyId !== null) {
                DB::table('companies')->where('id', $existingCompanyId)->update(['is_default' => true]);

                continue;
            }

            $tenantName = DB::table('tenants')->where('id', $tenantId)->value('name') ?? 'Default Company';

            DB::table('companies')->insert([
                'tenant_id' => $tenantId,
                'company_name' => $tenantName,
                'currency' => 'INR',
                'timezone' => 'Asia/Kolkata',
                'status' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Same for Branch, one level down: every Company needs at least one
        // default Branch for BelongsToBranch's global scope to resolve against.
        $companies = DB::table('companies')->select('id', 'company_name')->get();

        foreach ($companies as $companyRow) {
            $hasDefault = DB::table('branches')->where('company_id', $companyRow->id)->where('is_default', true)->exists();

            if ($hasDefault) {
                continue;
            }

            $existingBranchId = DB::table('branches')->where('company_id', $companyRow->id)->orderBy('id')->value('id');

            if ($existingBranchId !== null) {
                DB::table('branches')->where('id', $existingBranchId)->update(['is_default' => true]);

                continue;
            }

            DB::table('branches')->insert([
                'company_id' => $companyRow->id,
                'name' => 'Main Branch',
                'code' => 'MAIN-'.$companyRow->id,
                'status' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('branches', 'is_default')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }

        if (Schema::hasColumn('companies', 'is_default')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
