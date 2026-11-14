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
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('attendances', 'total_distance_km')) {
                    $table->decimal('total_distance_km', 8, 2)->default(0.00)->after('total_break_hours');
                }
                if (!Schema::hasColumn('attendances', 'route_polyline')) {
                    $table->text('route_polyline')->nullable()->after('total_distance_km');
                }
                if (!Schema::hasColumn('attendances', 'route_snapshot_url')) {
                    $table->string('route_snapshot_url')->nullable()->after('route_polyline');
                }
                if (!Schema::hasColumn('attendances', 'route_summary')) {
                    $table->json('route_summary')->nullable()->after('route_snapshot_url');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $columnsToDrop = [];
                if (Schema::hasColumn('attendances', 'total_distance_km')) {
                    $columnsToDrop[] = 'total_distance_km';
                }
                if (Schema::hasColumn('attendances', 'route_polyline')) {
                    $columnsToDrop[] = 'route_polyline';
                }
                if (Schema::hasColumn('attendances', 'route_snapshot_url')) {
                    $columnsToDrop[] = 'route_snapshot_url';
                }
                if (Schema::hasColumn('attendances', 'route_summary')) {
                    $columnsToDrop[] = 'route_summary';
                }

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
