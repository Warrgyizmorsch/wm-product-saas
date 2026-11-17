<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RT-IMPL-10 (RT-12): routing operation times are stored with 4 decimals — 0.0167 min was saved as 0.02
 * (≈ 20 % error at 3,600 units/hr).
 * RT-IMPL-13 (RT-16): two setup times. `setup_time_minutes` keeps its name and now means
 * "setup per operation (once per run)"; new `setup_time_per_piece_minutes` = setup per piece.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->decimal('setup_time_minutes', 12, 4)->default(0)->change();
            $table->decimal('processing_time_minutes', 12, 4)->default(0)->change();
            $table->decimal('wait_time_minutes', 12, 4)->default(0)->change();
        });

        if (!Schema::hasColumn('production_routing_operations', 'setup_time_per_piece_minutes')) {
            Schema::table('production_routing_operations', function (Blueprint $table) {
                $table->decimal('setup_time_per_piece_minutes', 12, 4)->nullable()->after('setup_time_minutes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('production_routing_operations', 'setup_time_per_piece_minutes')) {
            Schema::table('production_routing_operations', function (Blueprint $table) {
                $table->dropColumn('setup_time_per_piece_minutes');
            });
        }

        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->decimal('setup_time_minutes', 10, 2)->default(0)->change();
            $table->decimal('processing_time_minutes', 10, 2)->default(0)->change();
            $table->decimal('wait_time_minutes', 10, 2)->default(0)->change();
        });
    }
};
