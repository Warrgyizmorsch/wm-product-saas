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
                if (!Schema::hasColumn('production_order_operations', 'is_external')) {
                    $table->boolean('is_external')->default(false)->after('operator_id');
                }
                if (!Schema::hasColumn('production_order_operations', 'vendor_id')) {
                    $table->foreignId('vendor_id')->nullable()->after('is_external')->constrained('vendors', 'id', 'pro_ord_ops_vendor_fk_v2')->nullOnDelete();
                }
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
                if (Schema::hasColumn('production_order_operations', 'vendor_id')) {
                    $table->dropForeign('pro_ord_ops_vendor_fk_v2');
                    $table->dropColumn('vendor_id');
                }
                if (Schema::hasColumn('production_order_operations', 'is_external')) {
                    $table->dropColumn('is_external');
                }
            });
        }
    }
};
