<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_scraps', function (Blueprint $table) {
            $table->string('scrap_type', 40)->default('operational_loss')->after('reason');
            $table->foreignId('scrap_warehouse_id')->nullable()->after('scrap_type')->constrained('warehouses')->nullOnDelete();
            $table->string('storage_location', 100)->nullable()->after('scrap_warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_scraps', function (Blueprint $table) {
            $table->dropForeign(['scrap_warehouse_id']);
            $table->dropColumn(['scrap_type', 'scrap_warehouse_id', 'storage_location']);
        });
    }
};
