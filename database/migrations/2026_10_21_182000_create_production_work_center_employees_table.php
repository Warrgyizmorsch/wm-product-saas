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
        if (!Schema::hasTable('production_work_center_employees')) {
            Schema::create('production_work_center_employees', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pwce_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('work_center_id');
                $table->foreign('work_center_id', 'pwce_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('employee_id');
                $table->foreign('employee_id', 'pwce_emp_fk')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();

                $table->boolean('active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                // Unique per Work Center and Employee (one assignment record per employee per work center)
                $table->unique(['work_center_id', 'employee_id'], 'pwce_wc_emp_unique');
                $table->index(['tenant_id', 'work_center_id'], 'pwce_tenant_wc_idx');
                $table->index(['tenant_id', 'employee_id'], 'pwce_tenant_emp_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_work_center_employees');
    }
};
