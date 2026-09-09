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
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'company_name')) {
                    $table->string('company_name')->nullable()->after('name');
                }
                if (!Schema::hasColumn('customers', 'billing_address')) {
                    $table->text('billing_address')->nullable()->after('gstin');
                }
                if (!Schema::hasColumn('customers', 'shipping_address')) {
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
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                $columnsToDrop = [];
                if (Schema::hasColumn('customers', 'company_name')) {
                    $columnsToDrop[] = 'company_name';
                }
                if (Schema::hasColumn('customers', 'billing_address')) {
                    $columnsToDrop[] = 'billing_address';
                }
                if (Schema::hasColumn('customers', 'shipping_address')) {
                    $columnsToDrop[] = 'shipping_address';
                }
                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
