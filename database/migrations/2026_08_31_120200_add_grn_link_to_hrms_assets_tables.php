<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assets') && !Schema::hasColumn('assets', 'goods_receipt_note_item_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->unsignedBigInteger('goods_receipt_note_item_id')->nullable();
            });
        }

        if (Schema::hasTable('asset_categories') && !Schema::hasColumn('asset_categories', 'fixed_asset_account_id')) {
            Schema::table('asset_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('fixed_asset_account_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_categories') && Schema::hasColumn('asset_categories', 'fixed_asset_account_id')) {
            Schema::table('asset_categories', function (Blueprint $table) {
                $table->dropColumn('fixed_asset_account_id');
            });
        }

        if (Schema::hasTable('assets') && Schema::hasColumn('assets', 'goods_receipt_note_item_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('goods_receipt_note_item_id');
            });
        }
    }
};
