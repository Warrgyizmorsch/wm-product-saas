<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('material_requirement_items', 'quantity_released')) {
            Schema::table('material_requirement_items', function (Blueprint $table) {
                $table->decimal('quantity_released', 12, 4)->default(0)->after('quantity_reserved');
            });
        }

        // Backfill existing records that are marked as Released
        DB::table('material_requirement_items')
            ->whereIn('status', ['Released', 'Picked', 'Packed', 'Dispatched', 'Delivered', 'Shipped'])
            ->update([
                'quantity_released' => DB::raw('COALESCE(NULLIF(quantity_ordered, 0), quantity)')
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_requirement_items', 'quantity_released')) {
            Schema::table('material_requirement_items', function (Blueprint $table) {
                $table->dropColumn('quantity_released');
            });
        }
    }
};
