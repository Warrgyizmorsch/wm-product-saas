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
        Schema::table('business_units', function (Blueprint $table) {
            $table->foreign('head_employee_id')
                  ->references('id')
                  ->on('employees')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('manager_employee_id')
                  ->references('id')
                  ->on('employees')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_employee_id')
                  ->references('id')
                  ->on('employees')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_employee_id']);
        });

    }
};
