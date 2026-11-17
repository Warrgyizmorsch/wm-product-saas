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
        Schema::create('production_work_center_sections', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'pwcs_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('work_center_id');
            $table->foreign('work_center_id', 'pwcs_wc_fk')
                ->references('id')
                ->on('production_work_centers')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->unsignedInteger('workers_required')->default(1)
                ->comment('Number of workers required to operate this section.');

            $table->text('description')->nullable();
            $table->string('status', 50)->default('active')
                ->comment('active | inactive');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'work_center_id'], 'pwcs_tenant_wc_idx');
            $table->index(['tenant_id', 'status'], 'pwcs_tenant_status_idx');
            $table->unique(['tenant_id', 'work_center_id', 'code'], 'pwcs_tenant_wc_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_work_center_sections');
    }
};
