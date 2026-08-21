<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_revisions')) {
            Schema::create('salary_revisions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('old_salary_structure_id')->nullable();
                $table->unsignedBigInteger('new_salary_structure_id')->nullable();
                $table->date('effective_date');
                $table->decimal('old_ctc', 15, 2)->default(0.00);
                $table->decimal('new_ctc', 15, 2)->default(0.00);
                $table->boolean('arrears_paid')->default(false);
                $table->timestamps();

                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('old_salary_structure_id')->references('id')->on('salary_structures')->nullOnDelete();
                $table->foreign('new_salary_structure_id')->references('id')->on('salary_structures')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_revisions');
    }
};
