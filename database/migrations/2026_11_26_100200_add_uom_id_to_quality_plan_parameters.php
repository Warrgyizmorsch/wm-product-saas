<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quality Plan parameter Unit is picked from the UOM master (uoms) instead of free text.
 * uom_id is the link; unit_of_measure keeps the UOM code as the saved label, so every screen that already shows the
 * unit keeps working. Existing free-text units are matched to a UOM by code or name (same tenant) where possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('production_quality_plan_parameters', 'uom_id')) {
            Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
                $table->foreignId('uom_id')->nullable()->after('max_value')->constrained('uoms')->nullOnDelete();
            });
        }

        DB::table('production_quality_plan_parameters')
            ->whereNull('uom_id')
            ->whereNotNull('unit_of_measure')
            ->where('unit_of_measure', '!=', '')
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'unit_of_measure'])
            ->each(function ($param) {
                $text = mb_strtolower(trim((string) $param->unit_of_measure));
                $uom = DB::table('uoms')
                    ->where('tenant_id', $param->tenant_id)
                    ->where(function ($q) use ($text) {
                        $q->whereRaw('LOWER(code) = ?', [$text])->orWhereRaw('LOWER(name) = ?', [$text]);
                    })
                    ->first(['id', 'code']);

                if ($uom) {
                    DB::table('production_quality_plan_parameters')->where('id', $param->id)->update([
                        'uom_id'          => $uom->id,
                        'unit_of_measure' => $uom->code,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('production_quality_plan_parameters', 'uom_id')) {
            Schema::table('production_quality_plan_parameters', function (Blueprint $table) {
                $table->dropConstrainedForeignId('uom_id');
            });
        }
    }
};
