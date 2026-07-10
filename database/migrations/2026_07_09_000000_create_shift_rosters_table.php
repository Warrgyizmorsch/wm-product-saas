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
        if (!Schema::hasTable('shift_rosters')) {
            Schema::create('shift_rosters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('shift_id')->nullable(); // null represents "Day Off" or unscheduled
                $table->date('date');
                $table->string('status')->default('scheduled'); // scheduled, approved, cancelled
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('shift_id')->references('id')->on('production_shifts')->nullOnDelete();

                $table->unique(['tenant_id', 'employee_id', 'date'], 'sr_tenant_employee_date_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_rosters');
    }
};
