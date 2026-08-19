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
        // 1. Products: default_production_model
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'default_production_model')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('default_production_model')->default('pure_manufacturing')->after('planning_type');
            });
        }

        // 2. Production Orders: production_model
        if (Schema::hasTable('production_orders') && !Schema::hasColumn('production_orders', 'production_model')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->string('production_model')->default('pure_manufacturing')->after('status');
            });
        }

        // 3. Routing Operations: Subcontract fields
        if (Schema::hasTable('production_routing_operations')) {
            Schema::table('production_routing_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_routing_operations', 'subcontract_lead_time_days')) {
                    $table->integer('subcontract_lead_time_days')->default(0)->after('vendor_id');
                }
                if (!Schema::hasColumn('production_routing_operations', 'subcontract_cost_per_unit')) {
                    $table->decimal('subcontract_cost_per_unit', 15, 4)->default(0)->after('subcontract_lead_time_days');
                }
                if (!Schema::hasColumn('production_routing_operations', 'subcontract_service_product_id')) {
                    $table->foreignId('subcontract_service_product_id')
                        ->nullable()
                        ->after('subcontract_cost_per_unit')
                        ->constrained('products', 'id', 'pro_rot_ops_sub_svc_fk')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('production_routing_operations', 'material_supply_type')) {
                    $table->string('material_supply_type')->nullable()->default('company_supplied')->after('subcontract_service_product_id');
                }
                if (!Schema::hasColumn('production_routing_operations', 'dispatch_buffer_days')) {
                    $table->integer('dispatch_buffer_days')->default(0)->after('material_supply_type');
                }
                if (!Schema::hasColumn('production_routing_operations', 'return_buffer_days')) {
                    $table->integer('return_buffer_days')->default(0)->after('dispatch_buffer_days');
                }
            });
        }

        // 4. Production Order Operations: Subcontract fields & PO links
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_order_operations', 'is_external')) {
                    $table->boolean('is_external')->default(false)->after('operator_id');
                }
                if (!Schema::hasColumn('production_order_operations', 'vendor_id')) {
                    $table->foreignId('vendor_id')->nullable()->after('is_external')->constrained('vendors', 'id', 'pro_ord_ops_vendor_fk')->nullOnDelete();
                }
                if (!Schema::hasColumn('production_order_operations', 'subcontract_lead_time_days')) {
                    $table->integer('subcontract_lead_time_days')->default(0)->after('vendor_id');
                }
                if (!Schema::hasColumn('production_order_operations', 'subcontract_cost_per_unit')) {
                    $table->decimal('subcontract_cost_per_unit', 15, 4)->default(0)->after('subcontract_lead_time_days');
                }
                if (!Schema::hasColumn('production_order_operations', 'subcontract_service_product_id')) {
                    $table->foreignId('subcontract_service_product_id')
                        ->nullable()
                        ->after('subcontract_cost_per_unit')
                        ->constrained('products', 'id', 'pro_ord_ops_sub_svc_fk')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('production_order_operations', 'material_supply_type')) {
                    $table->string('material_supply_type')->nullable()->default('company_supplied')->after('subcontract_service_product_id');
                }
                if (!Schema::hasColumn('production_order_operations', 'dispatch_buffer_days')) {
                    $table->integer('dispatch_buffer_days')->default(0)->after('material_supply_type');
                }
                if (!Schema::hasColumn('production_order_operations', 'return_buffer_days')) {
                    $table->integer('return_buffer_days')->default(0)->after('dispatch_buffer_days');
                }
                if (!Schema::hasColumn('production_order_operations', 'purchase_order_id')) {
                    $table->foreignId('purchase_order_id')
                        ->nullable()
                        ->after('return_buffer_days')
                        ->constrained('purchase_orders', 'id', 'pro_ord_ops_po_fk')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('production_order_operations', 'purchase_order_item_id')) {
                    $table->foreignId('purchase_order_item_id')
                        ->nullable()
                        ->after('purchase_order_id')
                        ->constrained('purchase_order_items', 'id', 'pro_ord_ops_poi_fk')
                        ->nullOnDelete();
                }
            });
        }

        // 5. Warehouses: type & vendor_id
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                if (!Schema::hasColumn('warehouses', 'type')) {
                    $table->string('type')->default('standard')->after('name');
                }
                if (!Schema::hasColumn('warehouses', 'vendor_id')) {
                    $table->foreignId('vendor_id')->nullable()->after('type')->constrained('vendors', 'id', 'wh_vendor_fk')->nullOnDelete();
                }
            });
        }

        // 6. Purchase Orders: is_subcontract & production_order_id
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'is_subcontract')) {
                    $table->boolean('is_subcontract')->default(false)->after('status');
                }
                if (!Schema::hasColumn('purchase_orders', 'production_order_id')) {
                    $table->foreignId('production_order_id')->nullable()->after('is_subcontract')->constrained('production_orders', 'id', 'po_pro_order_fk')->nullOnDelete();
                }
            });
        }

        // 7. Purchase Order Items: production references
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_order_items', 'production_order_id')) {
                    $table->foreignId('production_order_id')->nullable()->after('product_id')->constrained('production_orders', 'id', 'poi_pro_order_fk')->nullOnDelete();
                }
                if (!Schema::hasColumn('purchase_order_items', 'production_order_operation_id')) {
                    $table->foreignId('production_order_operation_id')->nullable()->after('production_order_id')->constrained('production_order_operations', 'id', 'poi_pro_ord_op_fk')->nullOnDelete();
                }
                if (!Schema::hasColumn('purchase_order_items', 'production_batch_id')) {
                    $table->foreignId('production_batch_id')->nullable()->after('production_order_operation_id')->constrained('production_batches', 'id', 'poi_pro_batch_fk')->nullOnDelete();
                }
            });
        }

        // 8. Goods Receipt Notes: production_order_id
        if (Schema::hasTable('goods_receipt_notes')) {
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                if (!Schema::hasColumn('goods_receipt_notes', 'production_order_id')) {
                    $table->foreignId('production_order_id')->nullable()->after('purchase_order_id')->constrained('production_orders', 'id', 'grn_pro_order_fk')->nullOnDelete();
                }
            });
        }

        // 9. Goods Receipt Note Items: production references
        if (Schema::hasTable('goods_receipt_note_items')) {
            Schema::table('goods_receipt_note_items', function (Blueprint $table) {
                if (!Schema::hasColumn('goods_receipt_note_items', 'production_order_id')) {
                    $table->foreignId('production_order_id')->nullable()->after('product_id')->constrained('production_orders', 'id', 'grni_pro_order_fk')->nullOnDelete();
                }
                if (!Schema::hasColumn('goods_receipt_note_items', 'production_order_operation_id')) {
                    $table->foreignId('production_order_operation_id')->nullable()->after('production_order_id')->constrained('production_order_operations', 'id', 'grni_pro_ord_op_fk')->nullOnDelete();
                }
                if (!Schema::hasColumn('goods_receipt_note_items', 'production_batch_id')) {
                    $table->foreignId('production_batch_id')->nullable()->after('production_order_operation_id')->constrained('production_batches', 'id', 'grni_pro_batch_fk')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('goods_receipt_note_items')) {
            Schema::table('goods_receipt_note_items', function (Blueprint $table) {
                $table->dropForeign('grni_pro_order_fk');
                $table->dropForeign('grni_pro_ord_op_fk');
                $table->dropForeign('grni_pro_batch_fk');
                $table->dropColumn(['production_order_id', 'production_order_operation_id', 'production_batch_id']);
            });
        }

        if (Schema::hasTable('goods_receipt_notes')) {
            Schema::table('goods_receipt_notes', function (Blueprint $table) {
                $table->dropForeign('grn_pro_order_fk');
                $table->dropColumn(['production_order_id']);
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropForeign('poi_pro_order_fk');
                $table->dropForeign('poi_pro_ord_op_fk');
                $table->dropForeign('poi_pro_batch_fk');
                $table->dropColumn(['production_order_id', 'production_order_operation_id', 'production_batch_id']);
            });
        }

        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropForeign('po_pro_order_fk');
                $table->dropColumn(['is_subcontract', 'production_order_id']);
            });
        }

        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->dropForeign('wh_vendor_fk');
                $table->dropColumn(['type', 'vendor_id']);
            });
        }

        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                $table->dropForeign('pro_ord_ops_sub_svc_fk');
                $table->dropForeign('pro_ord_ops_po_fk');
                $table->dropForeign('pro_ord_ops_poi_fk');
                $table->dropColumn([
                    'subcontract_lead_time_days',
                    'subcontract_cost_per_unit',
                    'subcontract_service_product_id',
                    'material_supply_type',
                    'dispatch_buffer_days',
                    'return_buffer_days',
                    'purchase_order_id',
                    'purchase_order_item_id',
                ]);
            });
        }

        if (Schema::hasTable('production_routing_operations')) {
            Schema::table('production_routing_operations', function (Blueprint $table) {
                $table->dropForeign('pro_rot_ops_sub_svc_fk');
                $table->dropColumn([
                    'subcontract_lead_time_days',
                    'subcontract_cost_per_unit',
                    'subcontract_service_product_id',
                    'material_supply_type',
                    'dispatch_buffer_days',
                    'return_buffer_days',
                ]);
            });
        }

        if (Schema::hasTable('production_orders') && Schema::hasColumn('production_orders', 'production_model')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->dropColumn('production_model');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'default_production_model')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('default_production_model');
            });
        }
    }
};
