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
        if (Schema::hasTable('branches') && !Schema::hasColumn('branches', 'timezone')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('timezone', 64)->nullable()->after('country')
                    ->comment('Branch timezone (e.g. America/New_York). Null inherits from company.');
            });
        }

        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'timezone')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('timezone', 64)->nullable()->after('office')
                    ->comment('Employee timezone override. Null inherits from branch -> company.');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'timezone')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'timezone')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('timezone');
            });
        }
    }
};
