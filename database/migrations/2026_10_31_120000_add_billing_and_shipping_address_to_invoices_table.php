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
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('invoices', 'billing_address')) {
                    $table->text('billing_address')->nullable()->after('customer_id');
                }
                if (!Schema::hasColumn('invoices', 'shipping_address')) {
                    $table->text('shipping_address')->nullable()->after('billing_address');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('invoices', 'billing_address')) {
                    $cols[] = 'billing_address';
                }
                if (Schema::hasColumn('invoices', 'shipping_address')) {
                    $cols[] = 'shipping_address';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
