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
        Schema::create('production_work_center_section_shifts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'pwcss_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('section_id');
            $table->foreign('section_id', 'pwcss_section_fk')
                ->references('id')
                ->on('production_work_center_sections')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id', 'pwcss_shift_fk')
                ->references('id')
                ->on('production_shifts')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['section_id', 'shift_id'], 'pwcss_section_shift_unique');
            $table->index(['tenant_id', 'section_id'], 'pwcss_tenant_section_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_work_center_section_shifts');
    }
};
