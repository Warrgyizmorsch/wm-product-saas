<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                if (! Schema::hasColumn('branches', 'gst_number')) {
                    $table->string('gst_number', 20)->nullable()->after('code');
                }
                if (! Schema::hasColumn('branches', 'gst_state_code')) {
                    $table->string('gst_state_code', 2)->nullable()->after('gst_number');
                }
                if (! Schema::hasColumn('branches', 'is_head_office')) {
                    $table->boolean('is_head_office')->default(false)->after('gst_state_code');
                }
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_orders', 'buyer_gstin')) {
                    $table->string('buyer_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('purchase_orders', 'buyer_state_code')) {
                    $table->string('buyer_state_code', 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('sales_orders', 'seller_gstin')) {
                    $table->string('seller_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('sales_orders', 'seller_state_code')) {
                    $table->string('seller_state_code', 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('invoices', 'seller_gstin')) {
                    $table->string('seller_gstin', 20)->nullable();
                }
                if (! Schema::hasColumn('invoices', 'seller_state_code')) {
                    $table->string('seller_state_code', 2)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $cols = array_values(array_filter(['seller_gstin', 'seller_state_code'], fn ($c) => Schema::hasColumn('invoices', $c)));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('sales_orders')) {
            Schema::table('sales_orders', function (Blueprint $table) {
                $cols = array_values(array_filter(['seller_gstin', 'seller_state_code'], fn ($c) => Schema::hasColumn('sales_orders', $c)));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $cols = array_values(array_filter(['buyer_gstin', 'buyer_state_code'], fn ($c) => Schema::hasColumn('purchase_orders', $c)));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('branches')) {
            Schema::table('branches', function (Blueprint $table) {
                $cols = array_values(array_filter(['gst_number', 'gst_state_code', 'is_head_office'], fn ($c) => Schema::hasColumn('branches', $c)));
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
