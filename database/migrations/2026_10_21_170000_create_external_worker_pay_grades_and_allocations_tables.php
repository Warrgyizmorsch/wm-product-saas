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
        // 1. External Worker Pay Grades table (Tenant-scoped)
        if (!Schema::hasTable('production_external_worker_pay_grades')) {
            Schema::create('production_external_worker_pay_grades', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pewpg_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->string('name', 100);
                $table->string('code', 50);
                $table->decimal('hourly_rate', 15, 4)->default(0.0000);
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code'], 'pewpg_tenant_code_unique');
                $table->index(['tenant_id', 'active'], 'pewpg_tenant_active_idx');
            });
        }

        // 2. External Worker Allocations table (Section–Shift–PayGrade headcounts)
        if (!Schema::hasTable('production_external_worker_allocations')) {
            Schema::create('production_external_worker_allocations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pewa_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('section_shift_id')->nullable();
                $table->foreign('section_shift_id', 'pewa_section_shift_fk')
                    ->references('id')
                    ->on('production_section_shifts')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('section_id');
                $table->foreign('section_id', 'pewa_section_fk')
                    ->references('id')
                    ->on('production_work_center_sections')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('shift_id');
                $table->foreign('shift_id', 'pewa_shift_fk')
                    ->references('id')
                    ->on('production_shifts')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('pay_grade_id');
                $table->foreign('pay_grade_id', 'pewa_pay_grade_fk')
                    ->references('id')
                    ->on('production_external_worker_pay_grades')
                    ->cascadeOnDelete();

                $table->unsignedInteger('headcount')->default(1);
                $table->boolean('active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                // Uniqueness per logical grouping: (Section, Shift, Pay Grade)
                $table->unique(['section_id', 'shift_id', 'pay_grade_id'], 'pewa_sec_shift_pg_unique');
                $table->index(['tenant_id', 'section_id', 'shift_id'], 'pewa_tenant_sec_shift_idx');
                $table->index(['tenant_id', 'pay_grade_id'], 'pewa_tenant_pg_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_external_worker_allocations');
        Schema::dropIfExists('production_external_worker_pay_grades');
    }
};
