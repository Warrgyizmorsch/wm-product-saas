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
        Schema::create('production_schedule_optimization_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->json('scope_filters')->nullable();
            $table->json('summary')->nullable();
            $table->json('proposed_changes')->nullable();
            $table->json('capacity_before')->nullable();
            $table->json('capacity_after')->nullable();
            $table->json('version_snapshot')->nullable();
            $table->string('status', 32)->default('preview')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_schedule_optimization_runs');
    }
};
