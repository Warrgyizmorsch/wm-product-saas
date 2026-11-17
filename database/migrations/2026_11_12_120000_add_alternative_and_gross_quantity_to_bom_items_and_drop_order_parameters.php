<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BM-IMPL-04 #1: master list columns — alternative_quantity (alternative rows + materials on alternative operations)
 * and gross_quantity (Σ primary qty × (1 + scrap %)). Backfilled from the existing allocations.
 * BM-IMPL-09 #1: drop production_orders.parameters (formula parameters — feature removed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (! Schema::hasColumn('production_bom_items', 'alternative_quantity')) {
                $table->decimal('alternative_quantity', 18, 6)->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('production_bom_items', 'gross_quantity')) {
                $table->decimal('gross_quantity', 18, 6)->nullable()->after('alternative_quantity');
            }
        });

        // Backfill from allocations (effective alternative = alternative row OR material on an alternative operation)
        $rows = DB::table('production_routing_operation_materials as m')
            ->leftJoin('production_routing_operations as o', 'o.id', '=', 'm.routing_operation_id')
            ->whereNotNull('m.bom_id')
            ->get(['m.bom_id', 'm.material_id', 'm.quantity', 'm.material_scrap_percentage', 'm.is_alternative', 'o.is_alternative as op_is_alternative']);

        $sums = [];
        foreach ($rows as $r) {
            $key = $r->bom_id . '_' . $r->material_id;
            $sums[$key] ??= ['qty' => 0.0, 'alt' => 0.0, 'gross' => 0.0];
            $qty = (float) $r->quantity;
            if ($r->is_alternative || $r->op_is_alternative) {
                $sums[$key]['alt'] += $qty;
            } else {
                $sums[$key]['qty'] += $qty;
                $sums[$key]['gross'] += $qty * (1 + ((float) $r->material_scrap_percentage) / 100);
            }
        }

        foreach (DB::table('production_bom_items')->get(['id', 'bom_id', 'material_id']) as $item) {
            $s = $sums[$item->bom_id . '_' . $item->material_id] ?? null;
            if ($s === null) {
                continue;
            }
            DB::table('production_bom_items')->where('id', $item->id)->update([
                'quantity'             => round($s['qty'], 6),
                'alternative_quantity' => round($s['alt'], 6),
                'gross_quantity'       => round($s['gross'], 6),
            ]);
        }

        if (Schema::hasColumn('production_orders', 'parameters')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->dropColumn('parameters');
            });
        }
    }

    public function down(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            foreach (['gross_quantity', 'alternative_quantity'] as $col) {
                if (Schema::hasColumn('production_bom_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (! Schema::hasColumn('production_orders', 'parameters')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->json('parameters')->nullable();
            });
        }
    }
};
