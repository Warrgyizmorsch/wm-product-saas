<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_holds')) {
            Schema::create('payroll_holds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->string('payroll_month'); // YYYY-MM
                $table->string('status')->default('on_hold'); // on_hold, released
                $table->string('release_in_month')->nullable(); // YYYY-MM
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->unique(['employee_id', 'payroll_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_holds');
    }
};
