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
        if (!Schema::hasTable('production_work_center_activity_logs')) {
            Schema::create('production_work_center_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->foreign('tenant_id', 'pwcal_tenant_fk')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('work_center_id');
                $table->foreign('work_center_id', 'pwcal_wc_fk')
                    ->references('id')
                    ->on('production_work_centers')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('section_id')->nullable();
                $table->foreign('section_id', 'pwcal_sec_fk')
                    ->references('id')
                    ->on('production_work_center_sections')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id', 'pwcal_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->string('event_type', 60);
                $table->string('entity_type', 100);
                $table->unsignedBigInteger('entity_id')->nullable();

                $table->string('description', 255);
                $table->json('properties')->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'work_center_id'], 'pwcal_wc_idx');
                $table->index(['tenant_id', 'section_id'], 'pwcal_sec_idx');
                $table->index(['event_type'], 'pwcal_event_idx');
                $table->index(['created_at'], 'pwcal_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_work_center_activity_logs');
    }
};
