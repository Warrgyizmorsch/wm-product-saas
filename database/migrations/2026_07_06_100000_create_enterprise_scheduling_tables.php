<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Shifts table
        Schema::create('production_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('break_minutes')->default(0);
            $table->boolean('overtime_allowed')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // 2. Work Center - Shift relation table (many-to-many)
        Schema::create('production_work_center_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'p_wcs_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            
            $table->unsignedBigInteger('work_center_id');
            $table->foreign('work_center_id', 'pwcs_wc_id_fk')->references('id')->on('production_work_centers')->cascadeOnDelete();

            $table->unsignedBigInteger('shift_id');
            $table->foreign('shift_id', 'pwcs_shift_id_fk')->references('id')->on('production_shifts')->cascadeOnDelete();

            $table->timestamps();
        });

        // 3. Production Calendars table
        Schema::create('production_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('working_days')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 4. Production Calendar Holidays table
        Schema::create('production_calendar_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            
            $table->unsignedBigInteger('production_calendar_id');
            $table->foreign('production_calendar_id', 'p_cal_hol_cal_id_fk')->references('id')->on('production_calendars')->cascadeOnDelete();

            $table->string('name');
            $table->date('holiday_date');
            $table->string('holiday_type'); // public_holiday | weekend | maintenance_shutdown | other
            $table->timestamps();
        });

        // 5. Alternate Machines mapping table
        Schema::create('production_routing_operation_alternate_machines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreign('tenant_id', 'p_ro_alt_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedBigInteger('routing_operation_id');
            $table->foreign('routing_operation_id', 'p_ro_alt_ro_id_fk')->references('id')->on('production_routing_operations')->cascadeOnDelete();

            $table->unsignedBigInteger('machine_id');
            $table->foreign('machine_id', 'p_ro_alt_m_id_fk')->references('id')->on('production_machines')->cascadeOnDelete();

            $table->integer('priority');
            $table->timestamps();

            $table->unique(['routing_operation_id', 'machine_id'], 'p_ro_alt_ro_m_unique');
        });

        // 6. Alterations
        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->foreignId('production_calendar_id')
                ->nullable()
                ->constrained('production_calendars')
                ->nullOnDelete();
        });

        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->string('lane')->nullable();
            $table->string('resource_id')->nullable();
            $table->json('warnings')->nullable();
            $table->boolean('locked')->default(false);
            $table->foreignId('actual_machine_id')
                ->nullable()
                ->constrained('production_machines')
                ->nullOnDelete();
        });

        Schema::table('production_schedules', function (Blueprint $table) {
            $table->string('generated_by')->default('forward');
            $table->decimal('capacity_utilization', 5, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::table('production_schedules', function (Blueprint $table) {
            $table->dropColumn(['generated_by', 'capacity_utilization']);
        });

        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->dropForeign(['actual_machine_id']);
            $table->dropColumn(['lane', 'resource_id', 'warnings', 'locked', 'actual_machine_id']);
        });

        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->dropForeign(['production_calendar_id']);
            $table->dropColumn(['production_calendar_id']);
        });

        Schema::dropIfExists('production_routing_operation_alternate_machines');
        Schema::dropIfExists('production_calendar_holidays');
        Schema::dropIfExists('production_calendars');
        Schema::dropIfExists('production_work_center_shifts');
        Schema::dropIfExists('production_shifts');
    }
};
