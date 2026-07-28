<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_advance_payments')) {
            Schema::table('purchase_advance_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_advance_payments')) {
            Schema::table('purchase_advance_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_order_id')->nullable(false)->change();
            });
        }
    }
};
