<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operations', 'overlap_enabled') && !Schema::hasColumn('production_routing_operations', 'queue_threshold_enabled')) {
                $table->renameColumn('overlap_enabled', 'queue_threshold_enabled');
            }
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_operations', 'overlap_enabled') && !Schema::hasColumn('production_order_operations', 'queue_threshold_enabled')) {
                $table->renameColumn('overlap_enabled', 'queue_threshold_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operations', 'queue_threshold_enabled') && !Schema::hasColumn('production_routing_operations', 'overlap_enabled')) {
                $table->renameColumn('queue_threshold_enabled', 'overlap_enabled');
            }
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_operations', 'queue_threshold_enabled') && !Schema::hasColumn('production_order_operations', 'overlap_enabled')) {
                $table->renameColumn('queue_threshold_enabled', 'overlap_enabled');
            }
        });
    }
};
