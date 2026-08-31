<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            if (!Schema::hasColumn('goods_receipt_note_items', 'line_type')) {
                $table->string('line_type')->default('stock')->after('product_id'); // stock, asset, expense
            }
            if (!Schema::hasColumn('goods_receipt_note_items', 'chart_of_account_id')) {
                $table->unsignedBigInteger('chart_of_account_id')->nullable()->after('line_type');
            }
            if (!Schema::hasColumn('goods_receipt_note_items', 'asset_category_id')) {
                $table->unsignedBigInteger('asset_category_id')->nullable()->after('chart_of_account_id');
            }
        });

        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_note_items', function (Blueprint $table) {
            if (Schema::hasColumn('goods_receipt_note_items', 'asset_category_id')) {
                $table->dropColumn('asset_category_id');
            }
            if (Schema::hasColumn('goods_receipt_note_items', 'chart_of_account_id')) {
                $table->dropColumn('chart_of_account_id');
            }
            if (Schema::hasColumn('goods_receipt_note_items', 'line_type')) {
                $table->dropColumn('line_type');
            }
        });
    }
};
