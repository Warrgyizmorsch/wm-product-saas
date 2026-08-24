<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispatch_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('dispatch_orders', 'transporter_id')) {
                $table->unsignedBigInteger('transporter_id')->nullable()->after('sales_order_id')->index();
            }
            if (!Schema::hasColumn('dispatch_orders', 'eway_bill_number')) {
                $table->string('eway_bill_number', 50)->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('dispatch_orders', 'eway_bill_date')) {
                $table->date('eway_bill_date')->nullable()->after('eway_bill_number');
            }
            if (!Schema::hasColumn('dispatch_orders', 'lr_number')) {
                $table->string('lr_number', 50)->nullable()->after('eway_bill_date');
            }
            if (!Schema::hasColumn('dispatch_orders', 'lr_date')) {
                $table->date('lr_date')->nullable()->after('lr_number');
            }
            if (!Schema::hasColumn('dispatch_orders', 'freight_terms')) {
                $table->string('freight_terms', 50)->nullable()->default('To Pay')->after('lr_date');
            }
            if (!Schema::hasColumn('dispatch_orders', 'freight_amount')) {
                $table->decimal('freight_amount', 12, 2)->default(0.00)->after('freight_terms');
            }
            if (!Schema::hasColumn('dispatch_orders', 'shipping_address')) {
                $table->text('shipping_address')->nullable()->after('freight_amount');
            }
            if (!Schema::hasColumn('dispatch_orders', 'total_packages')) {
                $table->integer('total_packages')->nullable()->after('shipping_address');
            }
            if (!Schema::hasColumn('dispatch_orders', 'gross_weight')) {
                $table->decimal('gross_weight', 10, 3)->nullable()->after('total_packages');
            }
            if (!Schema::hasColumn('dispatch_orders', 'net_weight')) {
                $table->decimal('net_weight', 10, 3)->nullable()->after('gross_weight');
            }
            if (!Schema::hasColumn('dispatch_orders', 'volume_cbm')) {
                $table->decimal('volume_cbm', 10, 3)->nullable()->after('net_weight');
            }
            if (!Schema::hasColumn('dispatch_orders', 'gate_pass_number')) {
                $table->string('gate_pass_number', 50)->nullable()->after('volume_cbm');
            }
            if (!Schema::hasColumn('dispatch_orders', 'pod_attachment_path')) {
                $table->string('pod_attachment_path', 255)->nullable()->after('gate_pass_number');
            }
            if (!Schema::hasColumn('dispatch_orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('pod_attachment_path');
            }
        });

        Schema::table('dispatch_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('dispatch_order_items', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('warehouse_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dispatch_orders', function (Blueprint $table) {
            $table->dropColumn([
                'transporter_id',
                'eway_bill_number',
                'eway_bill_date',
                'lr_number',
                'lr_date',
                'freight_terms',
                'freight_amount',
                'shipping_address',
                'total_packages',
                'gross_weight',
                'net_weight',
                'volume_cbm',
                'gate_pass_number',
                'pod_attachment_path',
                'delivered_at',
            ]);
        });

        Schema::table('dispatch_order_items', function (Blueprint $table) {
            $table->dropColumn(['batch_number']);
        });
    }
};
