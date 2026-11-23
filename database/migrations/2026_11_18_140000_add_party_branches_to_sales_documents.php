<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_orders', 'billing_branch_id')) {
                    $table->foreignId('billing_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales_orders', 'shipping_branch_id')) {
                    $table->foreignId('shipping_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales_orders', 'site_contact_id')) {
                    $table->foreignId('site_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                }
                if (! Schema::hasColumn('sales_orders', 'bill_to_gstin')) {
                    $table->string('bill_to_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('sales_orders', 'ship_to_gstin')) {
                    $table->string('ship_to_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('sales_orders', 'place_of_supply_state')) {
                    $table->string('place_of_supply_state', 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'billing_branch_id')) {
                    $table->foreignId('billing_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoices', 'shipping_branch_id')) {
                    $table->foreignId('shipping_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoices', 'site_contact_id')) {
                    $table->foreignId('site_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                }
                if (! Schema::hasColumn('invoices', 'bill_to_gstin')) {
                    $table->string('bill_to_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('invoices', 'ship_to_gstin')) {
                    $table->string('ship_to_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('invoices', 'place_of_supply_state')) {
                    $table->string('place_of_supply_state', 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('dispatch_orders', 'shipping_branch_id')) {
                    $table->foreignId('shipping_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('dispatch_orders', 'site_contact_id')) {
                    $table->foreignId('site_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                }
                if (! Schema::hasColumn('dispatch_orders', 'ship_to_gstin')) {
                    $table->string('ship_to_gstin', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispatch_orders')) {
            Schema::table('dispatch_orders', function (Blueprint $table) {
                foreach (['shipping_branch_id', 'site_contact_id'] as $fk) {
                    if (Schema::hasColumn('dispatch_orders', $fk)) {
                        $table->dropConstrainedForeignId($fk);
                    }
                }
                if (Schema::hasColumn('dispatch_orders', 'ship_to_gstin')) {
                    $table->dropColumn('ship_to_gstin');
                }
            });
        }

        foreach (['invoices', 'sales_orders'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    foreach (['billing_branch_id', 'shipping_branch_id', 'site_contact_id'] as $fk) {
                        if (Schema::hasColumn($tbl, $fk)) {
                            $table->dropConstrainedForeignId($fk);
                        }
                    }
                    $cols = array_values(array_filter(
                        ['bill_to_gstin', 'ship_to_gstin', 'place_of_supply_state'],
                        fn ($c) => Schema::hasColumn($tbl, $c)
                    ));
                    if ($cols !== []) {
                        $table->dropColumn($cols);
                    }
                });
            }
        }
    }
};
