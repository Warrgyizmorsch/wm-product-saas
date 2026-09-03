<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goods_receipt_notes') && !Schema::hasColumn('goods_receipt_notes', 'transporter_id')) {
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                $table->unsignedBigInteger('transporter_id')->nullable()->after('transporter_name')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('goods_receipt_notes') && Schema::hasColumn('goods_receipt_notes', 'transporter_id')) {
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                $table->dropColumn('transporter_id');
            });
        }
    }
};
