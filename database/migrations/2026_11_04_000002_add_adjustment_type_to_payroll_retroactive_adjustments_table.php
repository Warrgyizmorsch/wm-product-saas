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
        Schema::table('payroll_retroactive_adjustments', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_retroactive_adjustments', 'adjustment_type')) {
                $table->string('adjustment_type', 50)->nullable()->default('general')->after('target_payroll_month');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_retroactive_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_retroactive_adjustments', 'adjustment_type')) {
                $table->dropColumn('adjustment_type');
            }
        });
    }
};

