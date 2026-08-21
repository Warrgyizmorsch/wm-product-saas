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
        if (Schema::hasTable('travel_requests') && !Schema::hasColumn('travel_requests', 'approved_budget')) {
            Schema::table('travel_requests', function (Blueprint $table) {
                $table->decimal('approved_budget', 10, 2)->nullable()->after('estimated_budget');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('travel_requests') && Schema::hasColumn('travel_requests', 'approved_budget')) {
            Schema::table('travel_requests', function (Blueprint $table) {
                $table->dropColumn('approved_budget');
            });
        }
    }
};
