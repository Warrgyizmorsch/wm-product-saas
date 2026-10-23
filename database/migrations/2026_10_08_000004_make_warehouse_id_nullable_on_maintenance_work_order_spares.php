<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::table('production_maintenance_work_order_spares', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_maintenance_work_order_spares')) {
            Schema::table('production_maintenance_work_order_spares', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
            });
        }
    }
};
