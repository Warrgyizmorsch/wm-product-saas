<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_work_center_sections', function (Blueprint $table) {
            if (Schema::hasColumn('production_work_center_sections', 'supports_manual_production')) {
                $table->dropColumn('supports_manual_production');
            }
            if (Schema::hasColumn('production_work_center_sections', 'manual_capacity_per_hour')) {
                $table->dropColumn('manual_capacity_per_hour');
            }
        });

        Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
            if (Schema::hasColumn('production_work_center_section_calculations', 'work_center_id')) {
                if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign('pwcsc_wc_fk');
                }
                $table->dropColumn('work_center_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_sections', function (Blueprint $table) {
            if (!Schema::hasColumn('production_work_center_sections', 'supports_manual_production')) {
                $table->boolean('supports_manual_production')->default(false);
            }
            if (!Schema::hasColumn('production_work_center_sections', 'manual_capacity_per_hour')) {
                $table->decimal('manual_capacity_per_hour', 12, 4)->nullable();
            }
        });

        Schema::table('production_work_center_section_calculations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_work_center_section_calculations', 'work_center_id')) {
                $table->unsignedBigInteger('work_center_id')->nullable()->after('tenant_id');
                $table->foreign('work_center_id', 'pwcsc_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();
            }
        });
    }
};
