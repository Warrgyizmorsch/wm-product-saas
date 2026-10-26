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
        if (Schema::hasTable('production_work_centers')) {
            Schema::table('production_work_centers', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_centers', 'overhead_cost')) {
                    $table->decimal('overhead_cost', 14, 4)->default(0.0000)->after('description');
                }

                if (!Schema::hasColumn('production_work_centers', 'calculation_countdown_minutes')) {
                    $table->unsignedInteger('calculation_countdown_minutes')
                        ->default(10)
                        ->comment('Required user-configured countdown duration per Work Center')
                        ->after('overhead_cost');
                }

                if (!Schema::hasColumn('production_work_centers', 'calculation_countdown_end_at')) {
                    $table->timestamp('calculation_countdown_end_at')
                        ->nullable()
                        ->comment('Target expiry timestamp when countdown is active')
                        ->after('calculation_countdown_minutes');
                }

                if (!Schema::hasColumn('production_work_centers', 'pending_calculation_section_ids')) {
                    $table->json('pending_calculation_section_ids')
                        ->nullable()
                        ->comment('Array of section IDs queued for recalculation')
                        ->after('calculation_countdown_end_at');
                }

                if (!Schema::hasColumn('production_work_centers', 'is_calculation_running')) {
                    $table->boolean('is_calculation_running')
                        ->default(false)
                        ->comment('Concurrency mutex lock during calculation execution')
                        ->after('pending_calculation_section_ids');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_work_centers')) {
            Schema::table('production_work_centers', function (Blueprint $table) {
                $columns = [
                    'calculation_countdown_minutes',
                    'calculation_countdown_end_at',
                    'pending_calculation_section_ids',
                    'is_calculation_running',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('production_work_centers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
