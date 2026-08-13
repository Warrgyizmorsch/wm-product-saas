<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedule_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('source_type')->default('live_schedule');
            $table->foreignId('source_schedule_id')->nullable()->constrained('production_schedules')->onDelete('set null');
            $table->string('status')->default('draft');
            $table->string('scenario_type')->default('custom');
            $table->json('scope_filters')->nullable();
            $table->json('assumptions')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'source_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedule_scenarios');
    }
};
