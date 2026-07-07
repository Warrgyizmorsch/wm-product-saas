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
        Schema::table('salary_components', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_components', 'is_adhoc')) {
                $table->boolean('is_adhoc')->default(false)->after('status');
            }
        });

        if (!Schema::hasTable('employee_adhoc_components')) {
            Schema::create('employee_adhoc_components', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('salary_component_id');
                $table->decimal('amount', 15, 2);
                $table->string('payroll_month'); // e.g. '2026-07'
                $table->string('status')->default('pending'); // pending, processed, cancelled
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('salary_component_id')->references('id')->on('salary_components')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_adhoc_components');

        Schema::table('salary_components', function (Blueprint $table) {
            if (Schema::hasColumn('salary_components', 'is_adhoc')) {
                $table->dropColumn('is_adhoc');
            }
        });
    }
};
