<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. New Table: production_alert_configurations
        Schema::create('production_alert_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type');
            $table->decimal('threshold', 10, 2);
            $table->string('severity')->default('info'); // info | warning | critical
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'alert_type']);
        });

        // 2. New Table: production_dashboard_preferences
        Schema::create('production_dashboard_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dashboard_type'); // executive | work_center | machine | andon | analytics
            $table->json('widgets')->nullable();
            $table->json('default_filters')->nullable();
            $table->string('layout')->default('default');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'dashboard_type'], 'p_dash_pref_unique');
        });

        // 3. New Table: production_kpi_targets
        Schema::create('production_kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kpi_name'); // oee | availability | performance | quality | throughput | utilization | scrap_rate | downtime
            $table->decimal('target_value', 10, 2);
            $table->timestamps();

            $table->unique(['tenant_id', 'kpi_name'], 'p_kpi_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_kpi_targets');
        Schema::dropIfExists('production_dashboard_preferences');
        Schema::dropIfExists('production_alert_configurations');
    }
};
