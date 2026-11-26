<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Control rework — data clean-up of the existing (dummy) plans (docs/production_quality_rules.md, QM-02 / QM-05):
 *  - "Work Center Specific" plans are deleted (soft delete — QR-02, the user: "delete them … dummy data only")
 *  - statuses mapped to draft | pending_approval | approved | historical (QR-07)
 *  - unknown types (in_process, final, product_category …) → product when a product is set, otherwise process
 *  - every remaining plan becomes version "1" of its own family with a plan number QP-0001… (QR-08)
 *  - duplicate name + version per tenant renamed "<name> (#id)" so the new unique rule holds (QR-11)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_quality_plans')) {
            return;
        }

        $now = now();

        DB::table('production_quality_plans')
            ->where('type', 'work_center')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => $now]);

        $statusMap = [
            'submitted' => 'pending_approval',
            'active'    => 'approved',
            'archived'  => 'historical',
        ];
        foreach ($statusMap as $from => $to) {
            DB::table('production_quality_plans')->where('status', $from)->update(['status' => $to]);
        }
        DB::table('production_quality_plans')
            ->whereNotIn('status', ['draft', 'pending_approval', 'approved', 'historical'])
            ->update(['status' => 'draft']);

        DB::table('production_quality_plans')
            ->whereNotIn('type', ['product', 'section', 'process'])
            ->whereNotNull('product_id')
            ->update(['type' => 'product']);
        DB::table('production_quality_plans')
            ->whereNotIn('type', ['product', 'section', 'process'])
            ->update(['type' => 'process']);

        $counters = [];
        $seen = [];
        DB::table('production_quality_plans')->orderBy('id')->get()->each(function ($plan) use (&$counters, &$seen) {
            $tenant = (int) $plan->tenant_id;
            $counters[$tenant] = ($counters[$tenant] ?? 0) + 1;

            $name = (string) $plan->name;
            if ($plan->deleted_at === null) {
                $key = $tenant . '|' . mb_strtolower($name);
                if (isset($seen[$key])) {
                    $name = $name . ' (#' . $plan->id . ')';
                }
                $seen[$key] = true;
            }

            DB::table('production_quality_plans')->where('id', $plan->id)->update([
                'name'         => $name,
                'version'      => '1',
                'plan_number'  => $plan->plan_number ?: 'QP-' . str_pad((string) $counters[$tenant], 4, '0', STR_PAD_LEFT),
                'root_plan_id' => $plan->root_plan_id ?: $plan->id,
                'historical_at' => $plan->status === 'historical' ? ($plan->historical_at ?? now()) : $plan->historical_at,
            ]);
        });

        if (Schema::hasTable('production_quality_plan_parameters')) {
            DB::table('production_quality_plan_parameters')->update(['sort_order' => DB::raw('id')]);
        }
    }

    public function down(): void
    {
        // data clean-up only — nothing to restore
    }
};
