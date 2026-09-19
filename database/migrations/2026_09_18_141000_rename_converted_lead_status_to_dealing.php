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

            // 1. Rename existing 'Converted' to 'Dealing' in lead_statuses table
            DB::table('lead_statuses')
                ->where('name', 'Converted')
                ->update([
                    'name'         => 'Dealing',
                    'sort_order'   => 3,
                    'color'        => 'bg-info',
                    'is_protected' => true,
                    'updated_at'   => $now,
                ]);

            // 2. Ensure every tenant/company/branch has 'Dealing' status
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
                    ->where('name', 'Dealing');

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
                        'name'         => 'Dealing',
                        'sort_order'   => 3,
                        'color'        => 'bg-info',
                        'is_protected' => true,
                        'is_active'    => true,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }

            // Ensure sort order for Won and Lost
            DB::table('lead_statuses')->where('name', 'Won')->where('sort_order', '<', 4)->update(['sort_order' => 4]);
            DB::table('lead_statuses')->where('name', 'Lost')->where('sort_order', '<', 5)->update(['sort_order' => 5]);
        }

        // 3. Update existing leads status from 'Converted' to 'Dealing'
        if (Schema::hasTable('leads')) {
            DB::table('leads')
                ->where('status', 'Converted')
                ->update(['status' => 'Dealing']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lead_statuses')) {
            DB::table('lead_statuses')
                ->where('name', 'Dealing')
                ->update(['name' => 'Converted']);
        }

        if (Schema::hasTable('leads')) {
            DB::table('leads')
                ->where('status', 'Dealing')
                ->update(['status' => 'Converted']);
        }
    }
};
