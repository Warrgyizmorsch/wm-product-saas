<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('lead_statuses')) {
            $now = now();

            // Only fall back to company/branch #1 when those rows actually exist —
            // on a fresh database (new install, test run) they don't, and the
            // foreign keys would reject the value.
            $defaultCompanyId = Schema::hasTable('companies') && DB::table('companies')->where('id', 1)->exists() ? 1 : null;
            $defaultBranchId = Schema::hasTable('branches') && DB::table('branches')->where('id', 1)->exists() ? 1 : null;

            // 1. Update any existing 'Converted' record where company_id or branch_id is NULL
            $defaults = array_filter([
                'company_id' => $defaultCompanyId,
                'branch_id'  => $defaultBranchId,
            ]);

            if ($defaults !== []) {
                DB::table('lead_statuses')
                    ->where('name', 'Converted')
                    ->where(function ($q) {
                        $q->whereNull('company_id')->orWhereNull('branch_id');
                    })
                    ->update($defaults);
            }

            // 2. Get distinct combinations of (tenant_id, company_id, branch_id) from lead_statuses.
            // An empty table means nothing has been seeded yet, so there is nothing to add to.
            $combinations = DB::table('lead_statuses')
                ->select('tenant_id', 'company_id', 'branch_id')
                ->distinct()
                ->get();

            foreach ($combinations as $combo) {
                if (! $combo->tenant_id) {
                    continue;
                }

                $tenantId  = $combo->tenant_id;
                $companyId = $combo->company_id ?: $defaultCompanyId;
                $branchId  = $combo->branch_id ?: $defaultBranchId;

                $exists = DB::table('lead_statuses')
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('name', 'Converted')
                    ->exists();

                if (!$exists) {
                    DB::table('lead_statuses')->insert([
                        'tenant_id'    => $tenantId,
                        'company_id'   => $companyId,
                        'branch_id'    => $branchId,
                        'name'         => 'Converted',
                        'sort_order'   => 3,
                        'color'        => 'bg-info',
                        'is_protected' => true,
                        'is_active'    => true,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            // Adjust sort order for Won and Lost so Converted stays at position #3
            DB::table('lead_statuses')->where('name', 'Won')->where('sort_order', '<', 4)->update(['sort_order' => 4]);
            DB::table('lead_statuses')->where('name', 'Lost')->where('sort_order', '<', 5)->update(['sort_order' => 5]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_statuses')) {
            DB::table('lead_statuses')->where('name', 'Converted')->delete();
        }
    }
};
