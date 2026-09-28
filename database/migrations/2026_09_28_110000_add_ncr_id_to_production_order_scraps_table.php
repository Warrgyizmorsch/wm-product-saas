<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_order_scraps') && !Schema::hasColumn('production_order_scraps', 'ncr_id')) {
            Schema::table('production_order_scraps', function (Blueprint $table) {
                $table->unsignedBigInteger('ncr_id')->nullable()->after('production_order_operation_id');
                $table->foreign('ncr_id')->references('id')->on('production_ncrs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_order_scraps') && Schema::hasColumn('production_order_scraps', 'ncr_id')) {
            Schema::table('production_order_scraps', function (Blueprint $table) {
                $table->dropForeign(['ncr_id']);
                $table->dropColumn('ncr_id');
            });
        }
    }
};
