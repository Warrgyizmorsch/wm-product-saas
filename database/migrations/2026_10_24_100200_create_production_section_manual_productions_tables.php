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
        // 1. Create production_section_manual_productions
        if (!Schema::hasTable('production_section_manual_productions')) {
            Schema::create('production_section_manual_productions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('section_id')
                    ->constrained('production_work_center_sections')
                    ->cascadeOnDelete();
                $table->foreignId('shift_id')
                    ->constrained('production_shifts')
                    ->cascadeOnDelete();

                $table->string('batch_name');
                $table->decimal('capacity_per_hour', 12, 4)->default(0.0000);
                $table->boolean('active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'section_id', 'shift_id'], 'psmp_sec_shift_idx');
                $table->index(['tenant_id', 'active'], 'psmp_tenant_active_idx');
            });
        }

        // 2. Create production_section_manual_production_employees pivot
        if (!Schema::hasTable('production_section_manual_production_employees')) {
            Schema::create('production_section_manual_production_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manual_production_id')
                    ->constrained('production_section_manual_productions', 'id', 'psmpe_manual_fk')
                    ->cascadeOnDelete();
                $table->foreignId('work_center_employee_id')
                    ->constrained('production_work_center_employees', 'id', 'psmpe_emp_fk')
                    ->cascadeOnDelete();

                $table->timestamps();

                $table->unique(['manual_production_id', 'work_center_employee_id'], 'psmpe_manual_emp_unique');
            });
        }

        // 3. Add manual_production_id to production_external_worker_allocations with short foreign key name
        if (Schema::hasTable('production_external_worker_allocations')) {
            Schema::table('production_external_worker_allocations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_external_worker_allocations', 'manual_production_id')) {
                    $table->unsignedBigInteger('manual_production_id')->nullable()->after('pay_grade_id');
                    $table->foreign('manual_production_id', 'pewa_manual_prod_fk')
                        ->references('id')
                        ->on('production_section_manual_productions')
                        ->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_external_worker_allocations')) {
            Schema::table('production_external_worker_allocations', function (Blueprint $table) {
                if (Schema::hasColumn('production_external_worker_allocations', 'manual_production_id')) {
                    try {
                        $table->dropForeign('pewa_manual_prod_fk');
                    } catch (\Throwable $e) {
                    }
                    $table->dropColumn('manual_production_id');
                }
            });
        }

        Schema::dropIfExists('production_section_manual_production_employees');
        Schema::dropIfExists('production_section_manual_productions');
    }
};
