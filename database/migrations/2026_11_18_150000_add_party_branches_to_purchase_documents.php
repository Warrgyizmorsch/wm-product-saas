<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_orders', 'vendor_branch_id')) {
                    $table->foreignId('vendor_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_orders', 'delivery_branch_id')) {
                    $table->foreignId('delivery_branch_id')->nullable()->constrained('branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_orders', 'delivery_party_branch_id')) {
                    $table->foreignId('delivery_party_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_orders', 'site_contact_id')) {
                    $table->foreignId('site_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_orders', 'supplier_gstin')) {
                    $table->string('supplier_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('purchase_orders', 'supplier_state_code')) {
                    $table->string('supplier_state_code', 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('purchase_rfq_vendors')) {
            Schema::table('purchase_rfq_vendors', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_rfq_vendors', 'vendor_branch_id')) {
                    $table->foreignId('vendor_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                if (! Schema::hasColumn('vendor_bills', 'vendor_branch_id')) {
                    $table->foreignId('vendor_branch_id')->nullable()->constrained('party_branches')->nullOnDelete();
                }
                if (! Schema::hasColumn('vendor_bills', 'supplier_gstin')) {
                    $table->string('supplier_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('vendor_bills', 'buyer_gstin')) {
                    $table->string('buyer_gstin', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_bills')) {
            Schema::table('vendor_bills', function (Blueprint $table) {
                if (Schema::hasColumn('vendor_bills', 'vendor_branch_id')) {
                    $table->dropConstrainedForeignId('vendor_branch_id');
                }
                $cols = array_values(array_filter(['supplier_gstin', 'buyer_gstin'], fn ($c) => Schema::hasColumn('vendor_bills', $c)));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('purchase_rfq_vendors')) {
            Schema::table('purchase_rfq_vendors', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_rfq_vendors', 'vendor_branch_id')) {
                    $table->dropConstrainedForeignId('vendor_branch_id');
                }
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach (['vendor_branch_id', 'delivery_branch_id', 'delivery_party_branch_id', 'site_contact_id'] as $fk) {
                    if (Schema::hasColumn('purchase_orders', $fk)) {
                        $table->dropConstrainedForeignId($fk);
                    }
                }
                $cols = array_values(array_filter(['supplier_gstin', 'supplier_state_code'], fn ($c) => Schema::hasColumn('purchase_orders', $c)));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
