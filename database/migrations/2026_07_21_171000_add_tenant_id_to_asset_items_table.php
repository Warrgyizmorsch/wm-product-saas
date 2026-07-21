<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('asset_items', 'tenant_id')) {
            Schema::table('asset_items', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                      ->nullable()
                      ->after('id')
                      ->constrained()
                      ->cascadeOnDelete();
            });

            // Update existing asset_items to match their company/category tenant_id
            $items = DB::table('asset_items')->get();
            foreach ($items as $item) {
                // Find parent category to get tenant_id
                $category = DB::table('asset_categories')->where('id', $item->asset_category_id)->first();
                if ($category) {
                    DB::table('asset_items')
                      ->where('id', $item->id)
                      ->update(['tenant_id' => $category->tenant_id]);
                }
            }

            // Make it non-nullable after filling
            Schema::table('asset_items', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_items', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
