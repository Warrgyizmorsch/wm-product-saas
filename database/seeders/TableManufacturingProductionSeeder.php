<?php

namespace Database\Seeders;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableManufacturingProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Step 0: Clean previous production scenario data ──────────────────
        Schema::disableForeignKeyConstraints();

        $tablesToTruncate = [
            'production_wip_transactions',
            'production_wips',
            'production_cost_adjustments',
            'production_event_timelines',
            'production_scrap_disposals',
            'production_order_scraps',
            'production_rework_operations',
            'production_rework_orders',
            'production_order_reworks',
            'production_capas',
            'production_ncrs',
            'production_deviations',
            'production_quality_inspection_results',
            'production_quality_inspections',
            'production_quality_plan_parameters',
            'production_quality_plans',
            'production_scan_logs',
            'production_serial_numbers',
            'production_lot_traces',
            'production_batch_genealogies',
            'production_batches',
            'production_operator_assignment_logs',
            'production_operator_assignments',
            'production_operator_skills',
            'production_schedule_operations',
            'production_schedules',
            'production_schedule_change_logs',
            'production_schedule_optimization_runs',
            'production_schedule_scenario_operations',
            'production_schedule_scenarios',
            'production_requisition_slip_items',
            'production_requisition_slips',
            'purchase_requisition_items',
            'purchase_requisitions',
            'production_order_requests',
            'production_order_issue_batches',
            'production_order_issues',
            'production_order_receipts',
            'production_order_progress_logs',
            'production_order_reservations',
            'production_order_operation_dependencies',
            'production_order_operations',
            'production_orders',
            'production_plan_operations',
            'production_plan_requirements',
            'production_plans',
            'production_machine_downtimes',
            'production_machine_state_histories',
            'production_alert_configurations',
            'production_kpi_targets',
            'production_shifts',
            'production_calendars',
            'production_dashboard_preferences',
            'production_calendar_holidays',
            'production_routing_operation_alternate_machines',
            'production_routing_operation_materials',
            'production_routing_approvals',
            'production_routing_operations',
            'routings',
            'production_bom_approvals',
            'production_bom_items',
            'production_boms',
            'production_machines',
            'production_work_centers',
            'production_work_center_shifts',
        ];

        foreach ($tablesToTruncate as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();

        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            $this->command->warn('No tenant found for Table Manufacturing Production seeding.');
            return;
        }

        $tenantId = $tenant->id;
        $adminUser = User::where('tenant_id', $tenantId)->first() ?? User::first();
        $userId = $adminUser?->id ?? 1;

        // Resolve UOMs & Products created by TableManufacturingProductSeeder
        $pcs = Uom::where('tenant_id', $tenantId)->where('code', 'Pcs')->first()
            ?? Uom::firstOrCreate(['tenant_id' => $tenantId, 'code' => 'Pcs'], ['name' => 'Pieces']);
        $mtr = Uom::where('tenant_id', $tenantId)->where('code', 'Mtr')->first()
            ?? Uom::firstOrCreate(['tenant_id' => $tenantId, 'code' => 'Mtr'], ['name' => 'Meters']);

        $fgTable = Product::where('tenant_id', $tenantId)->where('sku', 'FG-TBL-001')->first();
        $sfgFrame = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-FRAME')->first();
        $sfgLeg = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-LEG')->first();
        $sfgSupport = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-SUPPORT')->first();
        $sfgTop = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-TOP')->first();

        $rmPipe = Product::where('tenant_id', $tenantId)->where('sku', 'RM-TBL-PIPE')->first();
        $rmTopBoard = Product::where('tenant_id', $tenantId)->where('sku', 'RM-TBL-TOP-BOARD')->first();
        $rmFastener = Product::where('tenant_id', $tenantId)->where('sku', 'RM-TBL-FASTENER')->first();

        if (!$fgTable || !$sfgFrame || !$sfgLeg || !$sfgSupport || !$sfgTop || !$rmPipe) {
            $this->command->error('Table Manufacturing products missing! Ensure TableManufacturingProductSeeder runs first.');
            return;
        }

        DB::transaction(function () use (
            $tenantId,
            $userId,
            $pcs,
            $mtr,
            $fgTable,
            $sfgFrame,
            $sfgLeg,
            $sfgSupport,
            $sfgTop,
            $rmPipe,
            $rmTopBoard,
            $rmFastener,
            $adminUser
        ) {
            // 0. Subcontracting Vendor
            $vendor = Vendor::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'VEND-APEX'],
                [
                    'name' => 'Apex Surface Coating & Electroplating Corp',
                    'email' => 'sales@apexcoating.com',
                    'phone' => '020-27489911',
                    'status' => 'active',
                ]
            );

            // 1. Work Centers
            $workCenters = $this->seedWorkCenters($tenantId);

            // 2. Machines
            $machines = $this->seedMachines($tenantId, $workCenters);

            // 3. Quality Plans
            $qualityPlan = $this->seedQualityPlans($tenantId, $userId, $fgTable, $sfgFrame, $sfgTop);

            // 4. Multi-Level BOM Tree
            $boms = $this->seedBoms($tenantId, $userId, $pcs, $mtr, $fgTable, $sfgFrame, $sfgLeg, $sfgSupport, $sfgTop, $rmPipe, $rmTopBoard, $rmFastener);

            // 5. Master Routing & Routing Operations
            $routings = $this->seedRoutings($tenantId, $userId, $pcs, $fgTable, $sfgFrame, $sfgLeg, $sfgSupport, $sfgTop, $rmPipe, $rmTopBoard, $rmFastener, $workCenters, $machines, $boms, $vendor);
        });
    }

    private function seedWorkCenters(int $tenantId): array
    {
        $wcs = [
            'cut' => [
                'code' => 'WC-TBL-CUT',
                'name' => 'Tube & Component Cutting',
                'description' => 'CNC plasma and automatic saw cutting section for steel square pipes and structural profiles.',
                'capacity_per_hour' => 10.0,
                'cost_per_hour' => 450.00,
            ],
            'weld' => [
                'code' => 'WC-TBL-WELD',
                'name' => 'Frame Welding & Fabrication',
                'description' => 'MIG welding cell and precision jig assembly section for table frames and legs.',
                'capacity_per_hour' => 8.0,
                'cost_per_hour' => 350.00,
            ],
            'top' => [
                'code' => 'WC-TBL-TOP',
                'name' => 'Table Top Processing',
                'description' => 'Panel saw cutting, edge banding, and sealing section for engineered wood table tops.',
                'capacity_per_hour' => 8.0,
                'cost_per_hour' => 400.00,
            ],
            'finish' => [
                'code' => 'WC-TBL-FINISH',
                'name' => 'Surface Finishing',
                'description' => 'Weld seam grinding, shot blasting, and powder coating surface preparation section.',
                'capacity_per_hour' => 10.0,
                'cost_per_hour' => 250.00,
            ],
            'assy' => [
                'code' => 'WC-TBL-ASSY',
                'name' => 'Dining Table Final Assembly',
                'description' => 'Final assembly bay for fitting table tops, welded steel frames, and hardware sets.',
                'capacity_per_hour' => 5.0,
                'cost_per_hour' => 300.00,
            ],
        ];

        $created = [];
        foreach ($wcs as $key => $data) {
            $wc = WorkCenter::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'type' => 'work_center',
                    'work_center_type' => 'machining',
                    'description' => $data['description'],
                    'capacity_per_hour' => $data['capacity_per_hour'],
                    'efficiency_percentage' => 95.0,
                    'cost_per_hour' => $data['cost_per_hour'],
                    'status' => 'active',
                ]
            );
            $created[$key] = $wc;
        }

        return $created;
    }

    private function seedMachines(int $tenantId, array $workCenters): array
    {
        $machines = [
            'cut_01' => [
                'code' => 'MAC-TBL-CUT-01',
                'name' => 'Tube Cutting Machine 01 (Primary)',
                'work_center' => $workCenters['cut']->id,
            ],
            'cut_02' => [
                'code' => 'MAC-TBL-CUT-02',
                'name' => 'Tube Cutting Machine 02 (Alternate)',
                'work_center' => $workCenters['cut']->id,
            ],
            'weld_01' => [
                'code' => 'MAC-TBL-WELD-01',
                'name' => 'MIG Welding Station 01',
                'work_center' => $workCenters['weld']->id,
            ],
            'top_01' => [
                'code' => 'MAC-TBL-TOP-01',
                'name' => 'Panel Saw & Edge Bander 01',
                'work_center' => $workCenters['top']->id,
            ],
            'fin_01' => [
                'code' => 'MAC-TBL-FIN-01',
                'name' => 'Grinding & Surface Finishing Bay',
                'work_center' => $workCenters['finish']->id,
            ],
            'assy_01' => [
                'code' => 'MAC-TBL-ASSY-01',
                'name' => 'Final Fitting Assembly Station',
                'work_center' => $workCenters['assy']->id,
            ],
        ];

        $created = [];
        foreach ($machines as $key => $data) {
            $mch = Machine::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $data['code']],
                [
                    'work_center_id' => $data['work_center'],
                    'name' => $data['name'],
                    'status' => Machine::STATUS_ACTIVE,
                ]
            );
            $created[$key] = $mch;
        }

        return $created;
    }

    private function seedQualityPlans(int $tenantId, int $userId, Product $fgTable, Product $sfgFrame, Product $sfgTop): ProductionQualityPlan
    {
        // 1. Master Assembly Quality Plan
        $qp = ProductionQualityPlan::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Industrial Dining Table Master Quality Control Plan'],
            [
                'product_id' => $fgTable->id,
                'type' => 'in_process',
                'version' => '1.0',
                'status' => 'approved',
                'created_by' => $userId,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qp->id, 'name' => 'Weld Seam Penetration & Alignment'],
            [
                'type' => 'visual',
                'is_mandatory' => true,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qp->id, 'name' => 'Frame Squareness & Diagonals'],
            [
                'type' => 'numeric',
                'min_value' => 1798.0,
                'max_value' => 1802.0,
                'unit_of_measure' => 'mm',
                'is_mandatory' => true,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qp->id, 'name' => 'Powder Coating Thickness'],
            [
                'type' => 'numeric',
                'min_value' => 70.0,
                'max_value' => 95.0,
                'unit_of_measure' => 'microns',
                'is_mandatory' => true,
            ]
        );

        // 2. Tube Cutting & Prep Quality Plan
        $qpCut = ProductionQualityPlan::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Steel Tube Cutting & Dimension Inspection Plan'],
            [
                'product_id' => $sfgFrame->id,
                'type' => 'in_process',
                'version' => '1.0',
                'status' => 'approved',
                'created_by' => $userId,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qpCut->id, 'name' => 'Cut Pipe Length Tolerance'],
            [
                'type' => 'numeric',
                'min_value' => 748.0,
                'max_value' => 752.0,
                'unit_of_measure' => 'mm',
                'is_mandatory' => true,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qpCut->id, 'name' => 'Burr-Free Edge & Cut Squareness'],
            [
                'type' => 'visual',
                'is_mandatory' => true,
            ]
        );

        // 3. Final Assembly & Packaging Quality Plan
        $qpAssy = ProductionQualityPlan::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Final Assembly & Stability Quality Plan'],
            [
                'product_id' => $fgTable->id,
                'type' => 'final',
                'version' => '1.0',
                'status' => 'approved',
                'created_by' => $userId,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qpAssy->id, 'name' => 'Assembly Bolt Torque Specification'],
            [
                'type' => 'numeric',
                'min_value' => 25.0,
                'max_value' => 30.0,
                'unit_of_measure' => 'Nm',
                'is_mandatory' => true,
            ]
        );

        ProductionQualityPlanParameter::updateOrCreate(
            ['tenant_id' => $tenantId, 'quality_plan_id' => $qpAssy->id, 'name' => 'Leveling Foot Alignment & Table Stability'],
            [
                'type' => 'visual',
                'is_mandatory' => true,
            ]
        );

        return $qp;
    }

    private function seedBoms(
        int $tenantId,
        int $userId,
        $pcs,
        $mtr,
        $fgTable,
        $sfgFrame,
        $sfgLeg,
        $sfgSupport,
        $sfgTop,
        $rmPipe,
        $rmTopBoard,
        $rmFastener
    ): array {
        // 1. Table Leg BOM
        $bomLeg = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-LEG'],
            [
                'bom_name' => 'Table Leg Steel BOM',
                'product_id' => $sfgLeg->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomLeg->id, 'material_id' => $rmPipe->id],
            ['quantity' => 0.75, 'uom_id' => $mtr->id, 'sequence' => 1]
        );

        // 2. Horizontal Support BOM
        $bomSupport = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-SUPPORT'],
            [
                'bom_name' => 'Horizontal Support Beam BOM',
                'product_id' => $sfgSupport->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomSupport->id, 'material_id' => $rmPipe->id],
            ['quantity' => 0.60, 'uom_id' => $mtr->id, 'sequence' => 1]
        );

        // 3. Table Frame BOM
        $bomFrame = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-FRAME'],
            [
                'bom_name' => 'Table Frame Assembly BOM',
                'product_id' => $sfgFrame->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFrame->id, 'material_id' => $sfgLeg->id],
            ['child_bom_id' => $bomLeg->id, 'quantity' => 4.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFrame->id, 'material_id' => $sfgSupport->id],
            ['child_bom_id' => $bomSupport->id, 'quantity' => 2.0, 'uom_id' => $pcs->id, 'sequence' => 2]
        );

        // 4. Table Top BOM
        $bomTop = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-TOP'],
            [
                'bom_name' => 'Engineered Wood Table Top BOM',
                'product_id' => $sfgTop->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomTop->id, 'material_id' => $rmTopBoard->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );

        // 5. Industrial Dining Table FG BOM
        $bomFg = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-FG'],
            [
                'bom_name' => 'Industrial Dining Table Master BOM',
                'product_id' => $fgTable->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFg->id, 'material_id' => $sfgFrame->id],
            ['child_bom_id' => $bomFrame->id, 'quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFg->id, 'material_id' => $sfgTop->id],
            ['child_bom_id' => $bomTop->id, 'quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 2]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFg->id, 'material_id' => $rmFastener->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 3]
        );

        return [
            'leg' => $bomLeg,
            'support' => $bomSupport,
            'frame' => $bomFrame,
            'top' => $bomTop,
            'fg' => $bomFg,
        ];
    }

    private function seedRoutings(
        int $tenantId,
        int $userId,
        $pcs,
        $fgTable,
        $sfgFrame,
        $sfgLeg,
        $sfgSupport,
        $sfgTop,
        $rmPipe,
        $rmTopBoard,
        $rmFastener,
        array $workCenters,
        array $machines,
        array $boms,
        Vendor $vendor
    ): array {
        // 1. Table Leg Routing
        $rtLeg = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-LEG'],
            [
                'name' => 'Table Leg Steel Pipe Cutting Routing',
                'product_id' => $sfgLeg->id,
                'status' => 'active',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        $op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtLeg->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Table Leg Pipe Cutting',
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 2.0,
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op10->id, 'material_id' => $rmPipe->id],
            ['quantity' => 0.75, 'uom_id' => $rmPipe->uom_id]
        );

        RoutingOperationAlternateMachine::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op10->id, 'machine_id' => $machines['cut_02']->id],
            ['priority' => 2]
        );

        $boms['leg']->update(['routing_id' => $rtLeg->id]);

        // 2. Horizontal Support Routing
        $rtSupport = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-SUPPORT'],
            [
                'name' => 'Horizontal Support Pipe Cutting Routing',
                'product_id' => $sfgSupport->id,
                'status' => 'active',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        $op20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtSupport->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'Horizontal Support Pipe Cutting',
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 2.0,
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op20->id, 'material_id' => $rmPipe->id],
            ['quantity' => 0.60, 'uom_id' => $rmPipe->uom_id]
        );

        $boms['support']->update(['routing_id' => $rtSupport->id]);

        // 3. Table Frame Routing
        $rtFrame = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-FRAME'],
            [
                'name' => 'Table Frame Welding & Finishing Routing',
                'product_id' => $sfgFrame->id,
                'status' => 'active',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        $op30 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFrame->id, 'sequence' => 30],
            [
                'operation_number' => 'OP30',
                'name' => 'Frame MIG Welding Assembly',
                'work_center_id' => $workCenters['weld']->id,
                'machine_id' => $machines['weld_01']->id,
                'setup_time_minutes' => 15.0,
                'processing_time_minutes' => 12.0,
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op30->id, 'material_id' => $sfgLeg->id],
            ['quantity' => 4.0, 'uom_id' => $pcs->id]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op30->id, 'material_id' => $sfgSupport->id],
            ['quantity' => 2.0, 'uom_id' => $pcs->id]
        );

        $op50 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFrame->id, 'sequence' => 50],
            [
                'operation_number' => 'OP50',
                'name' => 'Frame Surface Finishing & Powder Coating',
                'work_center_id' => $workCenters['finish']->id,
                'machine_id' => $machines['fin_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 6.0,
                'quality_required' => false,
                'is_external' => false,
            ]
        );

        $boms['frame']->update(['routing_id' => $rtFrame->id]);

        // 4. Table Top Routing
        $rtTop = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-TOP'],
            [
                'name' => 'Table Top Processing Routing',
                'product_id' => $sfgTop->id,
                'status' => 'active',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        $op40 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtTop->id, 'sequence' => 40],
            [
                'operation_number' => 'OP40',
                'name' => 'Table Top Panel Processing',
                'work_center_id' => $workCenters['top']->id,
                'machine_id' => $machines['top_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 8.0,
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op40->id, 'material_id' => $rmTopBoard->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        $boms['top']->update(['routing_id' => $rtTop->id]);

        // 5. Industrial Dining Table Master FG Routing
        $rtFg = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-FG'],
            [
                'name' => 'Industrial Dining Table Master Assembly Routing',
                'product_id' => $fgTable->id,
                'status' => 'active',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        $op60 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFg->id, 'sequence' => 60],
            [
                'operation_number' => 'OP60',
                'name' => 'Final Dining Table Assembly',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 15.0,
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op60->id, 'material_id' => $sfgFrame->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op60->id, 'material_id' => $sfgTop->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op60->id, 'material_id' => $rmFastener->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        $boms['fg']->update(['routing_id' => $rtFg->id]);

        return [
            'leg' => $rtLeg,
            'support' => $rtSupport,
            'frame' => $rtFrame,
            'top' => $rtTop,
            'fg' => $rtFg,
        ];
    }
}
