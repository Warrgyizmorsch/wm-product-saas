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
        if (Schema::hasTable('production_work_center_employees')) {
            Schema::table('production_work_center_employees', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_center_employees', 'section_id')) {
                    $table->unsignedBigInteger('section_id')->nullable()->after('work_center_id');
                    $table->foreign('section_id', 'pwce_section_fk')
                        ->references('id')
                        ->on('production_work_center_sections')
                        ->nullOnDelete();
                    $table->index(['tenant_id', 'section_id'], 'pwce_tenant_sec_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_work_center_employees')) {
            Schema::table('production_work_center_employees', function (Blueprint $table) {
                if (Schema::hasColumn('production_work_center_employees', 'section_id')) {
                    $table->dropForeign('pwce_section_fk');
                    $table->dropIndex('pwce_tenant_sec_idx');
                    $table->dropColumn('section_id');
                }
            });
        }
    }
};
