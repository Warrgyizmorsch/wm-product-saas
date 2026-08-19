<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('deal_statuses') && !Schema::hasColumn('deal_statuses', 'probability')) {
            Schema::table('deal_statuses', function (Blueprint $table) {
                $table->integer('probability')->default(0)->after('color');
            });

            // Update default probabilities for standard stages
            DB::table('deal_statuses')->where('name', 'Qualification')->update(['probability' => 10]);
            DB::table('deal_statuses')->where('name', 'Needs Analysis')->update(['probability' => 30]);
            DB::table('deal_statuses')->where('name', 'Proposal')->update(['probability' => 60]);
            DB::table('deal_statuses')->where('name', 'Negotiation')->update(['probability' => 80]);
            DB::table('deal_statuses')->where('name', 'Won')->orWhere('name', 'Closed Won')->update(['probability' => 100]);
            DB::table('deal_statuses')->where('name', 'Lost')->orWhere('name', 'Closed Lost')->update(['probability' => 0]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('deal_statuses') && Schema::hasColumn('deal_statuses', 'probability')) {
            Schema::table('deal_statuses', function (Blueprint $table) {
                $table->dropColumn('probability');
            });
        }
    }
};
