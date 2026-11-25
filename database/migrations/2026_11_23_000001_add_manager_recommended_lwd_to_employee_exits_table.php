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
        if (Schema::hasTable('employee_exits') && !Schema::hasColumn('employee_exits', 'manager_recommended_lwd')) {
            Schema::table('employee_exits', function (Blueprint $table) {
                $table->date('manager_recommended_lwd')->nullable()->after('approved_lwd');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_exits') && Schema::hasColumn('employee_exits', 'manager_recommended_lwd')) {
            Schema::table('employee_exits', function (Blueprint $table) {
                $table->dropColumn('manager_recommended_lwd');
            });
        }
    }
};
