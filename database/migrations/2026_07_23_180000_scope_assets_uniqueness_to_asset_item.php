<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assets')) {
            $currentIndexes = array_map(function ($idx) {
                return $idx['name'] ?? '';
            }, Schema::getIndexes('assets'));

            Schema::disableForeignKeyConstraints();

            Schema::table('assets', function (Blueprint $table) use ($currentIndexes) {
                if (!in_array('assets_tenant_item_code_unique', $currentIndexes)) {
                    $table->unique(['tenant_id', 'asset_item_id', 'asset_code'], 'assets_tenant_item_code_unique');
                }
                if (in_array('assets_asset_code_unique', $currentIndexes)) {
                    $table->dropUnique('assets_asset_code_unique');
                }
                if (in_array('assets_tenant_id_asset_code_unique', $currentIndexes)) {
                    $table->dropUnique('assets_tenant_id_asset_code_unique');
                }
            });

            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('assets')) {
            $currentIndexes = array_map(function ($idx) {
                return $idx['name'] ?? '';
            }, Schema::getIndexes('assets'));

            Schema::disableForeignKeyConstraints();

            Schema::table('assets', function (Blueprint $table) use ($currentIndexes) {
                if (!in_array('assets_tenant_id_asset_code_unique', $currentIndexes)) {
                    $table->unique(['tenant_id', 'asset_code'], 'assets_tenant_id_asset_code_unique');
                }
                if (in_array('assets_tenant_item_code_unique', $currentIndexes)) {
                    $table->dropUnique('assets_tenant_item_code_unique');
                }
            });

            Schema::enableForeignKeyConstraints();
        }
    }
};
