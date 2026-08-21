<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_retroactive_adjustments')) {
            Schema::create('payroll_retroactive_adjustments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->string('target_payroll_month'); // YYYY-MM
                $table->integer('reversal_days');
                $table->decimal('amount_reversal', 15, 2)->default(0.00);
                $table->string('status')->default('pending'); // pending, processed
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_retroactive_adjustments');
    }
};
