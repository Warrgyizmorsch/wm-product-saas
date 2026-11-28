<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subcontract simplification (docs/production_ui_rules_review_2026-11-27.md, SF-1..SF-5):
 *  - SF-1 routing / order operation: vendor + 3 times in hours (dispatch, processing, receipt); supply type,
 *    processing mode, rate, service product and the day columns are removed
 *  - SF-2 BOM: "Vendor-supplied materials" is an operation tick (json on the BOM + snapshot on the order operation);
 *    the per-row supplied_by switch is removed (a row is always our material)
 *  - SF-4 no PR/PO: the PR line → operation link and the receipt → PO line link are removed
 *  - SF-5 vendor bill: account 5034 Job Work Charges for every tenant that has a chart of accounts
 */
return new class extends Migration
{
    private const OP_TABLES = ['production_routing_operations', 'production_order_operations'];

    private const OLD_OP_COLUMNS = [
        'material_supply_type',
        'subcontract_input_type',
        'subcontract_cost_per_unit',
        'subcontract_service_product_id',
        'subcontract_lead_time_days',
        'dispatch_buffer_days',
        'return_buffer_days',
    ];

    public function up(): void
    {
        foreach (self::OP_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['vendor_dispatch_hours', 'vendor_processing_hours', 'vendor_receipt_hours'] as $column) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        $table->decimal($column, 10, 2)->default(0)->after('vendor_id');
                    }
                }
            });

            // the old whole-day values carry over as hours (calendar time)
            if (Schema::hasColumn($tableName, 'subcontract_lead_time_days')) {
                DB::table($tableName)->update([
                    'vendor_dispatch_hours' => DB::raw('COALESCE(dispatch_buffer_days, 0) * 24'),
                    'vendor_processing_hours' => DB::raw('COALESCE(subcontract_lead_time_days, 0) * 24'),
                    'vendor_receipt_hours' => DB::raw('COALESCE(return_buffer_days, 0) * 24'),
                ]);
            }
        }

        Schema::table('production_boms', function (Blueprint $table) {
            if (!Schema::hasColumn('production_boms', 'operation_vendor_material_flags')) {
                // {routing_operation_id: true} — true = the vendor uses its own materials on this outsourced operation
                $table->json('operation_vendor_material_flags')->nullable()->after('operation_input_flags');
            }
        });

        Schema::table('production_order_operations', function (Blueprint $table) {
            if (!Schema::hasColumn('production_order_operations', 'vendor_supplies_materials')) {
                $table->boolean('vendor_supplies_materials')->default(false)->after('uses_previous_op_output');
            }
        });

        $this->carryVendorRowsIntoOperationTick();

        foreach (self::OP_TABLES as $tableName) {
            $this->dropServiceProductForeignKey($tableName);
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $drop = array_values(array_filter(self::OLD_OP_COLUMNS, fn ($column) => Schema::hasColumn($tableName, $column)));
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasColumn('production_routing_operation_materials', 'supplied_by')) {
            Schema::table('production_routing_operation_materials', function (Blueprint $table) {
                $table->dropColumn('supplied_by');
            });
        }

        if (Schema::hasColumn('purchase_requisition_items', 'production_order_operation_id')) {
            Schema::table('purchase_requisition_items', function (Blueprint $table) {
                $table->dropIndex('pri_prod_op_idx');
                $table->dropColumn('production_order_operation_id');
            });
        }

        if (Schema::hasColumn('production_subcontract_receipts', 'purchase_order_item_id')) {
            Schema::table('production_subcontract_receipts', function (Blueprint $table) {
                $table->dropColumn('purchase_order_item_id');
            });
        }

        $this->addJobWorkAccount();
    }

    public function down(): void
    {
        // The removed columns held data the simplified flow no longer has (rate, service product, supply mode);
        // a rollback restores the columns empty with their old defaults.
        foreach (self::OP_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'subcontract_lead_time_days')) {
                    $table->integer('subcontract_lead_time_days')->default(0);
                    $table->decimal('subcontract_cost_per_unit', 15, 4)->default(0);
                    $table->unsignedBigInteger('subcontract_service_product_id')->nullable();
                    $table->string('material_supply_type')->nullable()->default('company_supplied');
                    $table->string('subcontract_input_type', 50)->default('bom_raw_materials');
                    $table->integer('dispatch_buffer_days')->default(0);
                    $table->integer('return_buffer_days')->default(0);
                }
            });
            if (Schema::hasColumn($tableName, 'vendor_processing_hours')) {
                DB::table($tableName)->update([
                    'dispatch_buffer_days' => DB::raw('CEIL(vendor_dispatch_hours / 24)'),
                    'subcontract_lead_time_days' => DB::raw('CEIL(vendor_processing_hours / 24)'),
                    'return_buffer_days' => DB::raw('CEIL(vendor_receipt_hours / 24)'),
                ]);
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn(['vendor_dispatch_hours', 'vendor_processing_hours', 'vendor_receipt_hours']);
                });
            }
        }

        if (Schema::hasColumn('production_order_operations', 'vendor_supplies_materials')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                $table->dropColumn('vendor_supplies_materials');
            });
        }
        if (Schema::hasColumn('production_boms', 'operation_vendor_material_flags')) {
            Schema::table('production_boms', function (Blueprint $table) {
                $table->dropColumn('operation_vendor_material_flags');
            });
        }
        if (!Schema::hasColumn('production_routing_operation_materials', 'supplied_by')) {
            Schema::table('production_routing_operation_materials', function (Blueprint $table) {
                $table->string('supplied_by', 20)->default('company');
            });
        }
        if (!Schema::hasColumn('purchase_requisition_items', 'production_order_operation_id')) {
            Schema::table('purchase_requisition_items', function (Blueprint $table) {
                $table->unsignedBigInteger('production_order_operation_id')->nullable();
                $table->index('production_order_operation_id', 'pri_prod_op_idx');
            });
        }
        if (Schema::hasTable('production_subcontract_receipts') && !Schema::hasColumn('production_subcontract_receipts', 'purchase_order_item_id')) {
            Schema::table('production_subcontract_receipts', function (Blueprint $table) {
                $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            });
        }
    }

    /**
     * BOM rows marked vendor-supplied (old TF-02) become the operation tick; the rows themselves are removed,
     * because a material row now always means "our material sent to the vendor". A vendor_supplied routing
     * operation also gets the tick.
     */
    private function carryVendorRowsIntoOperationTick(): void
    {
        $flags = []; // bom_id => [routing_operation_id => true]

        if (Schema::hasColumn('production_routing_operation_materials', 'supplied_by')) {
            $vendorRows = DB::table('production_routing_operation_materials')
                ->where('supplied_by', 'vendor')
                ->get(['id', 'bom_id', 'routing_operation_id']);
            foreach ($vendorRows as $row) {
                if ($row->bom_id && $row->routing_operation_id) {
                    $flags[$row->bom_id][(string) $row->routing_operation_id] = true;
                }
            }
            if ($vendorRows->isNotEmpty()) {
                DB::table('production_routing_operation_materials')->whereIn('id', $vendorRows->pluck('id'))->delete();
            }
        }

        if (Schema::hasColumn('production_routing_operations', 'material_supply_type')) {
            $vendorOps = DB::table('production_routing_operations')
                ->where('material_supply_type', 'vendor_supplied')
                ->pluck('routing_id', 'id');
            foreach ($vendorOps as $opId => $routingId) {
                foreach (DB::table('production_boms')->where('routing_id', $routingId)->pluck('id') as $bomId) {
                    $flags[$bomId][(string) $opId] = true;
                }
            }
        }

        foreach ($flags as $bomId => $opFlags) {
            $existing = json_decode((string) DB::table('production_boms')->where('id', $bomId)->value('operation_vendor_material_flags'), true) ?: [];
            DB::table('production_boms')->where('id', $bomId)->update([
                'operation_vendor_material_flags' => json_encode($existing + $opFlags),
            ]);
        }

        if (Schema::hasColumn('production_order_operations', 'material_supply_type')) {
            DB::table('production_order_operations')
                ->where('material_supply_type', 'vendor_supplied')
                ->update(['vendor_supplies_materials' => true]);
        }
    }

    private function dropServiceProductForeignKey(string $tableName): void
    {
        if (!Schema::hasColumn($tableName, 'subcontract_service_product_id')) {
            return;
        }
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (in_array('subcontract_service_product_id', $foreignKey['columns'], true)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($foreignKey['name']));
            }
        }
    }

    /** 5034 Job Work Charges under the 5000 Expenses header, for every tenant that already has a chart */
    private function addJobWorkAccount(): void
    {
        if (!Schema::hasTable('chart_of_accounts')) {
            return;
        }
        $headers = DB::table('chart_of_accounts')->where('code', '5000')->whereNull('parent_id')->get(['id', 'tenant_id', 'company_id', 'branch_id']);
        foreach ($headers as $header) {
            $exists = DB::table('chart_of_accounts')->where('tenant_id', $header->tenant_id)
                ->where(fn ($q) => $q->where('system_key', 'job_work_charges')->orWhere('code', '5034'))
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('chart_of_accounts')->insert([
                'tenant_id' => $header->tenant_id,
                'company_id' => $header->company_id,
                'branch_id' => $header->branch_id,
                'code' => '5034',
                'system_key' => 'job_work_charges',
                'name' => 'Job Work Charges',
                'type' => 'expense',
                'subtype' => 'cogs',
                'normal_balance' => 'debit',
                'parent_id' => $header->id,
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
