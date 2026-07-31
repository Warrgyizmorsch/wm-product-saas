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
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->unique()
                    ->after('tenant_id')
                    ->constrained('users')
                    ->onDelete('set null');
            }

            $table->foreignId('company_id')->nullable()->change();
            $table->foreignId('department_id')->nullable()->change();
            $table->foreignId('designation_id')->nullable()->change();

            if (!Schema::hasColumn('employees', 'role')) {
                $table->string('role')->nullable()->after('job_title');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable(false)->change();
                $table->foreignId('department_id')->nullable(false)->change();
                $table->foreignId('designation_id')->nullable(false)->change();
            });
        } catch (\Exception $e) {}
    }
};
