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
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_order_operations', 'quality_required')) {
                    $table->boolean('quality_required')->default(false)->after('vendor_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (Schema::hasColumn('production_order_operations', 'quality_required')) {
                    $table->dropColumn('quality_required');
                }
            });
        }
    }
};
