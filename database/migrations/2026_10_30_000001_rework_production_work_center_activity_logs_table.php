<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add new explicit columns
        Schema::table('production_work_center_activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('production_work_center_activity_logs', 'calculation_type')) {
                $table->string('calculation_type', 30)->default('work_center')->after('user_id');
            }
            if (!Schema::hasColumn('production_work_center_activity_logs', 'snapshot_id')) {
                $table->unsignedBigInteger('snapshot_id')->nullable()->after('calculation_type');
            }
            if (!Schema::hasColumn('production_work_center_activity_logs', 'batch_id')) {
                $table->string('batch_id', 64)->nullable()->after('snapshot_id');
            }
        });

        // 2. Backfill existing data from polymorphic fields
        if (Schema::hasColumn('production_work_center_activity_logs', 'entity_id')) {
            $existingLogs = DB::table('production_work_center_activity_logs')->get();
            foreach ($existingLogs as $log) {
                $props = !empty($log->properties) ? json_decode($log->properties, true) : [];
                $batchId = $props['batch_id'] ?? null;
                $calcType = (
                    (isset($log->event_type) && $log->event_type === 'section_calculation_completed') ||
                    (isset($log->entity_type) && str_contains($log->entity_type, 'Section'))
                ) ? 'section' : 'work_center';

                DB::table('production_work_center_activity_logs')
                    ->where('id', $log->id)
                    ->update([
                        'calculation_type' => $calcType,
                        'snapshot_id'      => $log->entity_id ?? null,
                        'batch_id'         => $batchId,
                    ]);
            }
        }

        // 3. Drop legacy columns and indices
        Schema::table('production_work_center_activity_logs', function (Blueprint $table) {
            try {
                $table->dropIndex('pwcal_event_idx');
            } catch (\Throwable $e) {
                // Ignore if index doesn't exist
            }

            $legacyColumns = [];
            foreach (['event_type', 'entity_type', 'entity_id', 'properties'] as $col) {
                if (Schema::hasColumn('production_work_center_activity_logs', $col)) {
                    $legacyColumns[] = $col;
                }
            }

            if (!empty($legacyColumns)) {
                $table->dropColumn($legacyColumns);
            }

            // Add dedicated indices for rapid querying
            $table->index(['tenant_id', 'work_center_id', 'calculation_type'], 'pwcal_wc_type_idx');
            $table->index(['snapshot_id'], 'pwcal_snapshot_idx');
            $table->index(['batch_id'], 'pwcal_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_work_center_activity_logs', function (Blueprint $table) {
            try {
                $table->dropIndex('pwcal_wc_type_idx');
                $table->dropIndex('pwcal_snapshot_idx');
                $table->dropIndex('pwcal_batch_idx');
            } catch (\Throwable $e) {
                // Ignore
            }

            if (!Schema::hasColumn('production_work_center_activity_logs', 'event_type')) {
                $table->string('event_type', 60)->default('calculation_completed');
            }
            if (!Schema::hasColumn('production_work_center_activity_logs', 'entity_type')) {
                $table->string('entity_type', 100)->default('WorkCenterCalculation');
            }
            if (!Schema::hasColumn('production_work_center_activity_logs', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable();
            }
            if (!Schema::hasColumn('production_work_center_activity_logs', 'properties')) {
                $table->json('properties')->nullable();
            }

            $columnsToDrop = [];
            foreach (['calculation_type', 'snapshot_id', 'batch_id'] as $col) {
                if (Schema::hasColumn('production_work_center_activity_logs', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

