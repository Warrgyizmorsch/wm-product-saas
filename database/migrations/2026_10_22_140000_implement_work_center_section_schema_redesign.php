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
        // 1. production_work_centers: drop legacy calculation/overhead columns, add overhead_cost
        if (Schema::hasTable('production_work_centers')) {
            Schema::table('production_work_centers', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_centers', 'overhead_cost')) {
                    $table->decimal('overhead_cost', 12, 4)->default(0.0000)->after('status');
                }

                $columnsToDrop = [
                    'capacity_per_hour',
                    'total_theoretical_capacity',
                    'efficiency_percentage',
                    'cost_per_hour',
                    'labor_cost_per_hour',
                    'total_scheduled_labor_cost',
                    'unique_operating_hours',
                    'calculation_details',
                    'overhead_rate',
                ];

                foreach ($columnsToDrop as $col) {
                    if (Schema::hasColumn('production_work_centers', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 2. production_work_center_sections: drop legacy capacity/labor columns, add assigned_shifts JSON
        if (Schema::hasTable('production_work_center_sections')) {
            Schema::table('production_work_center_sections', function (Blueprint $table) {
                if (!Schema::hasColumn('production_work_center_sections', 'assigned_shifts')) {
                    $table->json('assigned_shifts')->nullable()->after('status');
                }

                $columnsToDrop = [
                    'workers_required',
                    'capacity_per_hour',
                    'labor_cost_per_hour',
                    'total_scheduled_labor_cost',
                    'unique_operating_hours',
                    'calculation_details',
                ];

                foreach ($columnsToDrop as $col) {
                    if (Schema::hasColumn('production_work_center_sections', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 3. production_external_worker_allocations: drop section_shift_id & headcount, add number_of_external_workers
        if (Schema::hasTable('production_external_worker_allocations')) {
            if (DB::getDriverName() === 'sqlite') {
                $qtyCol = Schema::hasColumn('production_external_worker_allocations', 'headcount') ? 'headcount' : 'number_of_external_workers';
                DB::statement("CREATE TABLE pewa_backup AS SELECT id, tenant_id, section_id, shift_id, pay_grade_id, {$qtyCol} as number_of_external_workers, active, created_at, updated_at, deleted_at FROM production_external_worker_allocations");
                Schema::drop('production_external_worker_allocations');

                Schema::create('production_external_worker_allocations', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id');
                    $table->foreign('tenant_id', 'pewa_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                    $table->unsignedBigInteger('section_id');
                    $table->foreign('section_id', 'pewa_section_fk')->references('id')->on('production_work_center_sections')->cascadeOnDelete();
                    $table->unsignedBigInteger('shift_id');
                    $table->foreign('shift_id', 'pewa_shift_fk')->references('id')->on('production_shifts')->cascadeOnDelete();
                    $table->unsignedBigInteger('pay_grade_id');
                    $table->foreign('pay_grade_id', 'pewa_pay_grade_fk')->references('id')->on('production_external_worker_pay_grades')->cascadeOnDelete();
                    $table->unsignedInteger('number_of_external_workers')->default(1);
                    $table->boolean('active')->default(true);
                    $table->timestamps();
                    $table->softDeletes();
                    $table->unique(['section_id', 'shift_id', 'pay_grade_id'], 'pewa_sec_shift_pg_unique');
                    $table->index(['tenant_id', 'section_id', 'shift_id'], 'pewa_tenant_sec_shift_idx');
                    $table->index(['tenant_id', 'pay_grade_id'], 'pewa_tenant_pg_idx');
                });

                DB::statement("INSERT INTO production_external_worker_allocations (id, tenant_id, section_id, shift_id, pay_grade_id, number_of_external_workers, active, created_at, updated_at, deleted_at) SELECT id, tenant_id, section_id, shift_id, pay_grade_id, number_of_external_workers, active, created_at, updated_at, deleted_at FROM pewa_backup");
                DB::statement("DROP TABLE pewa_backup");
            } else {
                Schema::table('production_external_worker_allocations', function (Blueprint $table) {
                    if (Schema::hasColumn('production_external_worker_allocations', 'section_shift_id')) {
                        try {
                            $table->dropForeign('pewa_section_shift_fk');
                        } catch (\Throwable $e) {
                            // In case foreign key has a different name or was already dropped
                        }
                        $table->dropColumn('section_shift_id');
                    }

                    if (Schema::hasColumn('production_external_worker_allocations', 'headcount')) {
                        $table->dropColumn('headcount');
                    }

                    if (!Schema::hasColumn('production_external_worker_allocations', 'number_of_external_workers')) {
                        $table->unsignedInteger('number_of_external_workers')->default(1)->after('pay_grade_id');
                    }
                });
            }
        }

        // 4. Drop entire table production_internal_worker_allocations
        Schema::dropIfExists('production_internal_worker_allocations');

        // 5. Drop entire table production_section_shifts
        Schema::dropIfExists('production_section_shifts');

        // 6. production_work_center_employees: make work_center_id nullable
        if (Schema::hasTable('production_work_center_employees')) {
            Schema::table('production_work_center_employees', function (Blueprint $table) {
                if (Schema::hasColumn('production_work_center_employees', 'work_center_id')) {
                    $table->unsignedBigInteger('work_center_id')->nullable()->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Revert production_work_center_employees: restore work_center_id as NOT NULL
        if (Schema::hasTable('production_work_center_employees')) {
            Schema::table('production_work_center_employees', function (Blueprint $table) {
                if (Schema::hasColumn('production_work_center_employees', 'work_center_id')) {
                    $table->unsignedBigInteger('work_center_id')->nullable(false)->change();
                }
            });
        }

        // 2. Recreate production_section_shifts table
        if (!Schema::hasTable('production_section_shifts')) {
            Schema::create('production_section_shifts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pss_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('section_id');
                $table->foreign('section_id', 'pss_section_fk')->references('id')->on('production_work_center_sections')->cascadeOnDelete();
                $table->unsignedBigInteger('shift_id');
                $table->foreign('shift_id', 'pss_shift_fk')->references('id')->on('production_shifts')->cascadeOnDelete();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['section_id', 'shift_id'], 'pss_section_shift_unique');
            });
        }

        // 3. Recreate production_internal_worker_allocations table
        if (!Schema::hasTable('production_internal_worker_allocations')) {
            Schema::create('production_internal_worker_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'piwa_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('section_shift_id')->nullable();
                $table->foreign('section_shift_id', 'piwa_section_shift_fk')->references('id')->on('production_section_shifts')->cascadeOnDelete();
                $table->unsignedBigInteger('section_id');
                $table->foreign('section_id', 'piwa_section_fk')->references('id')->on('production_work_center_sections')->cascadeOnDelete();
                $table->unsignedBigInteger('shift_id');
                $table->foreign('shift_id', 'piwa_shift_fk')->references('id')->on('production_shifts')->cascadeOnDelete();
                $table->unsignedInteger('headcount')->default(1);
                $table->decimal('rate', 15, 4)->default(100.0000);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['section_id', 'shift_id'], 'piwa_sec_shift_unique');
                $table->index(['tenant_id', 'section_id', 'shift_id'], 'piwa_tenant_sec_shift_idx');
            });
        }

        // 4. Revert production_external_worker_allocations
        if (Schema::hasTable('production_external_worker_allocations')) {
            Schema::table('production_external_worker_allocations', function (Blueprint $table) {
                if (Schema::hasColumn('production_external_worker_allocations', 'number_of_external_workers')) {
                    $table->dropColumn('number_of_external_workers');
                }
                if (!Schema::hasColumn('production_external_worker_allocations', 'section_shift_id')) {
                    $table->unsignedBigInteger('section_shift_id')->nullable()->after('tenant_id');
                    $table->foreign('section_shift_id', 'pewa_section_shift_fk')->references('id')->on('production_section_shifts')->cascadeOnDelete();
                }
                if (!Schema::hasColumn('production_external_worker_allocations', 'headcount')) {
                    $table->unsignedInteger('headcount')->default(1)->after('pay_grade_id');
                }
            });
        }

        // 5. Revert production_work_center_sections
        if (Schema::hasTable('production_work_center_sections')) {
            Schema::table('production_work_center_sections', function (Blueprint $table) {
                if (Schema::hasColumn('production_work_center_sections', 'assigned_shifts')) {
                    $table->dropColumn('assigned_shifts');
                }
                if (!Schema::hasColumn('production_work_center_sections', 'workers_required')) {
                    $table->unsignedInteger('workers_required')->default(1)->after('code');
                }
                if (!Schema::hasColumn('production_work_center_sections', 'capacity_per_hour')) {
                    $table->decimal('capacity_per_hour', 15, 4)->nullable()->after('manual_capacity_per_hour');
                }
                if (!Schema::hasColumn('production_work_center_sections', 'labor_cost_per_hour')) {
                    $table->decimal('labor_cost_per_hour', 15, 4)->nullable()->after('capacity_per_hour');
                }
                if (!Schema::hasColumn('production_work_center_sections', 'total_scheduled_labor_cost')) {
                    $table->decimal('total_scheduled_labor_cost', 14, 4)->nullable()->after('labor_cost_per_hour');
                }
                if (!Schema::hasColumn('production_work_center_sections', 'unique_operating_hours')) {
                    $table->decimal('unique_operating_hours', 8, 4)->nullable()->after('total_scheduled_labor_cost');
                }
                if (!Schema::hasColumn('production_work_center_sections', 'calculation_details')) {
                    $table->json('calculation_details')->nullable()->after('unique_operating_hours');
                }
            });
        }

        // 6. Revert production_work_centers
        if (Schema::hasTable('production_work_centers')) {
            Schema::table('production_work_centers', function (Blueprint $table) {
                if (Schema::hasColumn('production_work_centers', 'overhead_cost')) {
                    $table->dropColumn('overhead_cost');
                }
                if (!Schema::hasColumn('production_work_centers', 'capacity_per_hour')) {
                    $table->decimal('capacity_per_hour', 10, 2)->nullable()->after('location');
                }
                if (!Schema::hasColumn('production_work_centers', 'total_theoretical_capacity')) {
                    $table->decimal('total_theoretical_capacity', 14, 4)->nullable()->after('capacity_per_hour');
                }
                if (!Schema::hasColumn('production_work_centers', 'efficiency_percentage')) {
                    $table->decimal('efficiency_percentage', 5, 2)->default(100.00)->after('total_theoretical_capacity');
                }
                if (!Schema::hasColumn('production_work_centers', 'cost_per_hour')) {
                    $table->decimal('cost_per_hour', 12, 4)->default(0.0000)->after('efficiency_percentage');
                }
                if (!Schema::hasColumn('production_work_centers', 'labor_cost_per_hour')) {
                    $table->decimal('labor_cost_per_hour', 12, 4)->nullable()->after('cost_per_hour');
                }
                if (!Schema::hasColumn('production_work_centers', 'total_scheduled_labor_cost')) {
                    $table->decimal('total_scheduled_labor_cost', 14, 4)->nullable()->after('labor_cost_per_hour');
                }
                if (!Schema::hasColumn('production_work_centers', 'unique_operating_hours')) {
                    $table->decimal('unique_operating_hours', 8, 4)->nullable()->after('total_scheduled_labor_cost');
                }
                if (!Schema::hasColumn('production_work_centers', 'calculation_details')) {
                    $table->json('calculation_details')->nullable()->after('unique_operating_hours');
                }
                if (!Schema::hasColumn('production_work_centers', 'overhead_rate')) {
                    $table->decimal('overhead_rate', 12, 4)->default(0.0000)->after('calculation_details');
                }
            });
        }
    }
};
