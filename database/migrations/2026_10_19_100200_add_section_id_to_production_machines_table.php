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
        Schema::table('production_machines', function (Blueprint $table) {
            if (!Schema::hasColumn('production_machines', 'section_id')) {
                $table->foreignId('section_id')
                    ->nullable()
                    ->after('work_center_id')
                    ->constrained('production_work_center_sections')
                    ->nullOnDelete();

                $table->index(['tenant_id', 'section_id'], 'pm_tenant_section_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            if (Schema::hasColumn('production_machines', 'section_id')) {
                $table->dropIndex('pm_tenant_section_idx');
                $table->dropConstrainedForeignId('section_id');
            }
        });
    }
};
