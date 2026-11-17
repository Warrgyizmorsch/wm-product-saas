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
        if (Schema::hasTable('production_section_shifts')) {
            Schema::table('production_section_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('production_section_shifts', 'target_headcount')) {
                    $table->dropColumn('target_headcount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_section_shifts')) {
            Schema::table('production_section_shifts', function (Blueprint $table) {
                if (!Schema::hasColumn('production_section_shifts', 'target_headcount')) {
                    $table->unsignedInteger('target_headcount')->default(1);
                }
            });
        }
    }
};
