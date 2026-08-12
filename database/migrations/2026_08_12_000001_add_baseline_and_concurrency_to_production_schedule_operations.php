<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->dateTime('baseline_start')->nullable()->after('planned_finish');
            $table->dateTime('baseline_finish')->nullable()->after('baseline_start');

            $table->boolean('manual_override')->default(false)->after('locked');
            $table->dateTime('last_adjusted_at')->nullable()->after('manual_override');
            $table->foreignId('last_adjusted_by')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('last_adjusted_at');

            $table->unsignedInteger('version')->default(1)->after('last_adjusted_by');

            $table->index(['tenant_id', 'work_center_id', 'planned_start', 'planned_finish'], 'pso_dispatch_board_wc_idx');
            $table->index(['tenant_id', 'machine_id', 'planned_start', 'planned_finish'], 'pso_dispatch_board_m_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->dropIndex('pso_dispatch_board_wc_idx');
            $table->dropIndex('pso_dispatch_board_m_idx');

            $table->dropForeign(['last_adjusted_by']);
            $table->dropColumn([
                'baseline_start',
                'baseline_finish',
                'manual_override',
                'last_adjusted_at',
                'last_adjusted_by',
                'version',
            ]);
        });
    }
};
