<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->string('payroll_month'); // YYYY-MM
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status')->default('draft'); // draft, locked, paid
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
                $table->unique(['company_id', 'payroll_month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
