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
        if (Schema::hasTable('crm_deals')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                if (!Schema::hasColumn('crm_deals', 'product_ids')) {
                    $table->json('product_ids')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('crm_deals', 'product_items')) {
                    $table->json('product_items')->nullable()->after('product_ids');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('crm_deals')) {
            Schema::table('crm_deals', function (Blueprint $table) {
                if (Schema::hasColumn('crm_deals', 'product_items')) {
                    $table->dropColumn('product_items');
                }
                if (Schema::hasColumn('crm_deals', 'product_ids')) {
                    $table->dropColumn('product_ids');
                }
            });
        }
    }
};
