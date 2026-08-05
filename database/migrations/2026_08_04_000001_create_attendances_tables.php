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
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->dateTime('check_in');
                $table->dateTime('check_out')->nullable();
                $table->string('location_type')->nullable(); // office, wfh, onsite
                $table->string('status')->nullable(); // present, late, under_hours, on_leave
                $table->decimal('total_work_hours', 5, 2)->default(0.00);
                $table->decimal('total_break_hours', 5, 2)->default(0.00);
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('attendance_breaks')) {
            Schema::create('attendance_breaks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_id');
                $table->dateTime('break_in');
                $table->dateTime('break_out')->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->timestamps();

                $table->foreign('attendance_id')->references('id')->on('attendances')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_breaks');
        Schema::dropIfExists('attendances');
    }
};
