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
        if (!Schema::hasTable('production_internal_worker_allocations')) {
            Schema::create('production_internal_worker_allocations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'piwa_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('section_shift_id')->nullable();
                $table->foreign('section_shift_id', 'piwa_section_shift_fk')
                    ->references('id')
                    ->on('production_section_shifts')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('section_id');
                $table->foreign('section_id', 'piwa_section_fk')
                    ->references('id')
                    ->on('production_work_center_sections')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('shift_id');
                $table->foreign('shift_id', 'piwa_shift_fk')
                    ->references('id')
                    ->on('production_shifts')
                    ->cascadeOnDelete();

                $table->unsignedInteger('headcount')->default(1);
                $table->decimal('rate', 15, 4)->default(100.0000);
                $table->boolean('active')->default(true);

                $table->timestamps();
                $table->softDeletes();

                // Logical grouping per (Section, Shift)
                $table->unique(['section_id', 'shift_id'], 'piwa_sec_shift_unique');
                $table->index(['tenant_id', 'section_id', 'shift_id'], 'piwa_tenant_sec_shift_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_internal_worker_allocations');
    }
};
