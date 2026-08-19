<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders') && !Schema::hasColumn('purchase_orders', 'completed_at')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->timestamp('completed_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'completed_at')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('completed_at');
            });
        }
    }
};
