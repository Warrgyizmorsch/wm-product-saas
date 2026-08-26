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
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'freight_terms')) {
                $table->string('freight_terms')->nullable()->after('gst_type');
            }
            if (!Schema::hasColumn('purchase_orders', 'freight_amount')) {
                $table->decimal('freight_amount', 15, 2)->default(0)->after('freight_terms');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'freight_terms')) {
                $table->dropColumn('freight_terms');
            }
            if (Schema::hasColumn('purchase_orders', 'freight_amount')) {
                $table->dropColumn('freight_amount');
            }
        });
    }
};