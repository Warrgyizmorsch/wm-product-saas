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

            $defaultCompanyId = DB::table('companies')->where('is_default', true)->value('id') ?? DB::table('companies')->value('id');
            $defaultBranchId  = DB::table('branches')->where('is_default', true)->value('id') ?? DB::table('branches')->value('id');

            // 1. Update any existing 'Converted' record where company_id or branch_id is NULL
            if ($defaultCompanyId !== null || $defaultBranchId !== null) {
                $updateData = [];
                if ($defaultCompanyId !== null) {
                    $updateData['company_id'] = $defaultCompanyId;
                }
                if ($defaultBranchId !== null) {
                    $updateData['branch_id'] = $defaultBranchId;
                }

                DB::table('lead_statuses')
                    ->where('name', 'Converted')
                    ->where(function ($q) {
                        $q->whereNull('company_id')->orWhereNull('branch_id');
                    })
                    ->update($updateData);
            }

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

            if ($combinations->isEmpty()) {
                $combinations = collect([(object)[
                    'tenant_id'  => 1,
                    'company_id' => $defaultCompanyId,
                    'branch_id'  => $defaultBranchId,
                ]]);
            }

            foreach ($combinations as $combo) {
                $tenantId  = $combo->tenant_id ?: 1;
                $companyId = $combo->company_id ?: $defaultCompanyId;
                $branchId  = $combo->branch_id ?: $defaultBranchId;

                $existsQuery = DB::table('lead_statuses')
                    ->where('tenant_id', $tenantId)
                    ->where('name', 'Converted');

                if ($companyId !== null) {
                    $existsQuery->where('company_id', $companyId);
                } else {
                    $existsQuery->whereNull('company_id');
                }

                if (!$existsQuery->exists()) {
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
