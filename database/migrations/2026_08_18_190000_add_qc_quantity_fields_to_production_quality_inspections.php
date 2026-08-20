<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_quality_inspections', function (Blueprint $table) {
            if (!Schema::hasColumn('production_quality_inspections', 'inspection_number')) {
                $table->string('inspection_number', 50)->nullable()->after('quality_plan_id');
            }
            if (!Schema::hasColumn('production_quality_inspections', 'sample_size')) {
                $table->decimal('sample_size', 15, 4)->nullable()->default(0)->after('result');
            }
            if (!Schema::hasColumn('production_quality_inspections', 'inspected_quantity')) {
                $table->decimal('inspected_quantity', 15, 4)->nullable()->default(0)->after('sample_size');
            }
            if (!Schema::hasColumn('production_quality_inspections', 'passed_qty')) {
                $table->decimal('passed_qty', 15, 4)->nullable()->default(0)->after('inspected_quantity');
            }
            if (!Schema::hasColumn('production_quality_inspections', 'failed_qty')) {
                $table->decimal('failed_qty', 15, 4)->nullable()->default(0)->after('passed_qty');
            }
            if (!Schema::hasColumn('production_quality_inspections', 'remarks')) {
                $table->text('remarks')->nullable()->after('failed_qty');
            }
            if (!Schema::hasColumn('production_quality_inspections', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable()->after('remarks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_quality_inspections', function (Blueprint $table) {
            $columns = ['inspection_number', 'sample_size', 'inspected_quantity', 'passed_qty', 'failed_qty', 'remarks', 'inspected_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('production_quality_inspections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
