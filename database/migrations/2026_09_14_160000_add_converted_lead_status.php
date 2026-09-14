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

            // 1. Update any existing 'Converted' record where company_id or branch_id is NULL
            DB::table('lead_statuses')
                ->where('name', 'Converted')
                ->where(function ($q) {
                    $q->whereNull('company_id')->orWhereNull('branch_id');
                })
                ->update([
                    'company_id' => 1,
                    'branch_id'  => 1,
                ]);

            // 2. Get distinct combinations of (tenant_id, company_id, branch_id) from lead_statuses
            $combinations = DB::table('lead_statuses')
                ->select('tenant_id', 'company_id', 'branch_id')
                ->distinct()
                ->get();

            if ($combinations->isEmpty()) {
                $combinations = collect([(object)['tenant_id' => 1, 'company_id' => 1, 'branch_id' => 1]]);
            }

            foreach ($combinations as $combo) {
                $tenantId  = $combo->tenant_id ?: 1;
                $companyId = $combo->company_id ?: 1;
                $branchId  = $combo->branch_id ?: 1;

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
