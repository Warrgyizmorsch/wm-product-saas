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
        if (!Schema::hasColumn('purchase_requisitions', 'rejection_reason')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('notes');
            });
        }

        if (!Schema::hasColumn('purchase_orders', 'rejection_reason')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('purchase_requisitions', 'rejection_reason')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }

        if (Schema::hasColumn('purchase_orders', 'rejection_reason')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
