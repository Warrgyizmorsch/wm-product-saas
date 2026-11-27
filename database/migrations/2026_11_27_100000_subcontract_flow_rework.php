<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subcontract flow rework (docs/subcontracting_module_audit_2026-11-26.md, sections 10–12):
 *  - TF-01 BOM "Previous OP materials" per operation (BOM json + order-operation snapshot)
 *  - TF-02 vendor-supplied materials on outsourced operations (supplied_by on the BOM allocation)
 *  - TF-04 dispatch slip quantities per line (material / WIP), received / returned / consumed / scrapped
 *  - TF-06 one receipt record per subcontract receipt (menu or shop floor) + the system GRN it creates
 *  - N-15 PR line → outsourced operation link
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_boms', function (Blueprint $table) {
            if (!Schema::hasColumn('production_boms', 'operation_input_flags')) {
                // {routing_operation_id: true|false} — true = the operation works on the previous operation's output
                $table->json('operation_input_flags')->nullable()->after('notes');
            }
        });

        Schema::table('production_routing_operation_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('production_routing_operation_materials', 'supplied_by')) {
                // company = requested from the store and issued; vendor = the subcontractor brings it (never requested)
                $table->string('supplied_by', 20)->default('company')->after('is_alternative');
            }
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_order_operations', 'uses_previous_op_output')) {
                $table->boolean('uses_previous_op_output')->default(false)->after('subcontract_input_type');
            }
        });

        Schema::table('delivery_challans', function (Blueprint $table) {
            foreach (['received_qty', 'accepted_qty', 'rejected_qty', 'scrapped_qty'] as $column) {
                if (!Schema::hasColumn('delivery_challans', $column)) {
                    $table->decimal($column, 14, 4)->default(0)->after('dispatched_wip_qty');
                }
            }
            if (!Schema::hasColumn('delivery_challans', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('delivery_challans', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('dispatched_at');
            }
        });

        Schema::table('delivery_challan_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_challan_items', 'item_type')) {
                // material = company material issued to the order; wip = the previous operation's output
                $table->string('item_type', 20)->default('material')->after('product_id');
            }
            if (!Schema::hasColumn('delivery_challan_items', 'required_qty')) {
                $table->decimal('required_qty', 14, 4)->nullable()->after('quantity');
            }
            foreach (['consumed_qty', 'returned_qty', 'scrapped_qty'] as $column) {
                if (!Schema::hasColumn('delivery_challan_items', $column)) {
                    $table->decimal($column, 14, 4)->default(0)->after('quantity');
                }
            }
        });

        if (!Schema::hasTable('production_subcontract_receipts')) {
            Schema::create('production_subcontract_receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('receipt_number', 40);
                $table->unsignedBigInteger('production_order_id');
                $table->unsignedBigInteger('production_order_operation_id');
                $table->unsignedBigInteger('delivery_challan_id')->nullable();
                $table->unsignedBigInteger('purchase_order_item_id')->nullable();
                $table->unsignedBigInteger('goods_receipt_note_id')->nullable();
                $table->string('source', 20); // challan (Subcontracting menu) | shop_floor (MES)
                $table->string('status', 20)->default('booked'); // booked | qc_pending | rejected_in_qc
                $table->decimal('received_qty', 14, 4)->default(0);
                $table->decimal('accepted_qty', 14, 4)->default(0);
                $table->decimal('rejected_qty', 14, 4)->default(0);
                $table->decimal('scrapped_qty', 14, 4)->default(0);
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('received_by')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'production_order_operation_id'], 'psr_tenant_op_idx');
                $table->index(['tenant_id', 'delivery_challan_id'], 'psr_tenant_challan_idx');
                $table->unique(['tenant_id', 'receipt_number'], 'psr_tenant_number_unique');
            });
        }

        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('goods_receipt_notes', 'source_type')) {
                $table->string('source_type', 40)->nullable()->after('status');
            }
            if (!Schema::hasColumn('goods_receipt_notes', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        Schema::table('purchase_requisition_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requisition_items', 'production_order_operation_id')) {
                $table->unsignedBigInteger('production_order_operation_id')->nullable()->after('project_task_id');
                $table->index('production_order_operation_id', 'pri_prod_op_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requisition_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_requisition_items', 'production_order_operation_id')) {
                $table->dropIndex('pri_prod_op_idx');
                $table->dropColumn('production_order_operation_id');
            }
        });
        Schema::table('goods_receipt_notes', function (Blueprint $table) {
            foreach (['source_type', 'source_id'] as $column) {
                if (Schema::hasColumn('goods_receipt_notes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('production_subcontract_receipts');
        Schema::table('delivery_challan_items', function (Blueprint $table) {
            foreach (['item_type', 'required_qty', 'consumed_qty', 'returned_qty', 'scrapped_qty'] as $column) {
                if (Schema::hasColumn('delivery_challan_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('delivery_challans', function (Blueprint $table) {
            foreach (['received_qty', 'accepted_qty', 'rejected_qty', 'scrapped_qty', 'dispatched_at', 'completed_at'] as $column) {
                if (Schema::hasColumn('delivery_challans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('production_order_operations', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_operations', 'uses_previous_op_output')) {
                $table->dropColumn('uses_previous_op_output');
            }
        });
        Schema::table('production_routing_operation_materials', function (Blueprint $table) {
            if (Schema::hasColumn('production_routing_operation_materials', 'supplied_by')) {
                $table->dropColumn('supplied_by');
            }
        });
        Schema::table('production_boms', function (Blueprint $table) {
            if (Schema::hasColumn('production_boms', 'operation_input_flags')) {
                $table->dropColumn('operation_input_flags');
            }
        });
    }
};
