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
        Schema::table('vendor_bills', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_bills', 'freight_allocation_method')) {
                $table->string('freight_allocation_method', 50)->nullable()->default('by_amount');
            }
            if (!Schema::hasColumn('vendor_bills', 'landed_cost_revaluation_data')) {
                $table->json('landed_cost_revaluation_data')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_bills', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_bills', 'freight_allocation_method')) {
                $table->dropColumn('freight_allocation_method');
            }
            if (Schema::hasColumn('vendor_bills', 'landed_cost_revaluation_data')) {
                $table->dropColumn('landed_cost_revaluation_data');
            }
        });
    }
};
