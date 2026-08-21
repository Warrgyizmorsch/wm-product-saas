<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable()->change();
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable()->change();
        });

        Schema::table('production_plan_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable()->change();
        });

        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable()->change();
        });

        Schema::table('production_schedule_scenario_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable(false)->change();
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable(false)->change();
        });

        Schema::table('production_plan_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable(false)->change();
        });

        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable(false)->change();
        });

        Schema::table('production_schedule_scenario_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('work_center_id')->nullable(false)->change();
        });
    }
};
