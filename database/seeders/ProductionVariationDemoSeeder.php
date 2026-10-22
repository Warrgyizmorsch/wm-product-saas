<?php

namespace Database\Seeders;

use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ProductionVariationDemoSeeder
 *
 * Provides a rich set of production scenarios to thoroughly test new features:
 * 1. per_unit Mode with staged consumption across multiple operations (100% allocated).
 * 2. per_bom_base_quantity Mode with batch BOM (Base Qty = 10 Pieces).
 * 3. Partial Allocation Scenario triggering the Partial Allocation Notice banner (Rule B).
 * 4. Subcontracted / Outsourced Operation with upstream material consumption.
 * 5. High-batch automation scenario (Base Qty = 50 Pieces).
 * 6. Alternative revision version (V2 draft) with single-cell allocation.
 */
class ProductionVariationDemoSeeder extends Seeder
{
    public function run(): void
    {
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
            'production_scan_logs',
            'production_serial_numbers',
            'production_lot_traces',
            'production_batch_genealogies',
            'production_batches',
            'production_operator_assignment_logs',
            'production_operator_assignments',
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
            'production_maintenance_work_order_spares',
            'production_maintenance_work_orders',
            'production_pm_schedules',
            'production_alert_configurations',
            'production_kpi_targets',
            'production_calendar_holidays',
            'production_calendars',
            'production_operator_skills',
            'production_work_center_shifts',
            'production_shifts',
            'production_eco_approvals',
            'production_eco_items',
            'production_ecos',
            'production_quality_plan_parameters',
            'production_quality_plans',
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
            $this->command->warn('No tenant found for Production Variation seeding.');
            return;
        }

        $tenantId = $tenant->id;
        $adminUser = User::where('tenant_id', $tenantId)->first() ?? User::first();
        $userId = $adminUser?->id ?? 1;

        // Ensure UOMs
        $pcs = Uom::where('tenant_id', $tenantId)->where('code', 'Pcs')->first()
            ?? Uom::firstOrCreate(['tenant_id' => $tenantId, 'code' => 'Pcs'], ['name' => 'Pieces']);
        $mtr = Uom::where('tenant_id', $tenantId)->where('code', 'Mtr')->first()
            ?? Uom::firstOrCreate(['tenant_id' => $tenantId, 'code' => 'Mtr'], ['name' => 'Meters']);

        // Resolve Products
        $fgTable    = Product::where('tenant_id', $tenantId)->where('sku', 'FG-TBL-001')->first();
        $sfgFrame   = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-FRAME')->first();
        $sfgLeg     = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-LEG')->first();
        $sfgSupport = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-SUPPORT')->first();
        $sfgTop     = Product::where('tenant_id', $tenantId)->where('sku', 'SFG-TBL-TOP')->first();

        $rmPipe     = Product::where('tenant_id', $tenantId)->where('sku', 'RM-TBL-PIPE')->first();
        $rmTopBoard = Product::where('tenant_id', $tenantId)->where('sku', 'RM-TBL-TOP-BOARD')->first();
        $rmFastener = Product::where('tenant_id', $tenantId)->where('sku', 'RM-TBL-FASTENER')->first();

        if (!$fgTable || !$sfgFrame || !$sfgLeg || !$sfgSupport || !$sfgTop || !$rmPipe) {
            $this->command->error('Table Manufacturing products missing! Please run TableManufacturingProductSeeder first.');
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
            $rmFastener
        ) {
            // ─── 1. Subcontract Vendor ───────────────────────────────────────
            $vendor = Vendor::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'VEND-APEX'],
                [
                    'name' => 'Apex Surface Coating & Electroplating Corp',
                    'email' => 'sales@apexcoating.com',
                    'phone' => '020-27489911',
                    'status' => 'active',
                ]
            );

            // ─── 2. Work Centers ─────────────────────────────────────────────
            $workCenters = $this->seedWorkCenters($tenantId);

            // ─── 3. Machines ─────────────────────────────────────────────────
            $machines = $this->seedMachines($tenantId, $workCenters);

            // ─── 4. Shifts & Calendars ───────────────────────────────────────
            $this->seedShifts($tenantId, $workCenters);
            $this->seedCalendars($tenantId);

            // ─── 5. BOM Variations ───────────────────────────────────────────
            $boms = $this->seedBomVariations(
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
                $rmFastener
            );

            // ─── 6. Routing Variations ───────────────────────────────────────
            $this->seedRoutingVariations(
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
                $workCenters,
                $machines,
                $boms,
                $vendor
            );
        });

        $this->command->info('ProductionVariationDemoSeeder completed successfully with 6 diverse BOM & Routing test scenarios.');
    }

    private function seedWorkCenters(int $tenantId): array
    {
        $wcs = [
            'cut' => [
                'code' => 'WC-TBL-CUT',
                'name' => 'Tube & Component Cutting',
                'description' => 'Cold circular saw and automated tube cutting station.',
                'capacity_per_hour' => 15.0,
                'cost_per_hour' => 450.00,
            ],
            'weld' => [
                'code' => 'WC-TBL-WELD',
                'name' => 'Frame Welding & Fabrication',
                'description' => 'MIG/TIG welding fixtures and structural frame assembly bay.',
                'capacity_per_hour' => 8.0,
                'cost_per_hour' => 400.00,
            ],
            'top' => [
                'code' => 'WC-TBL-TOP',
                'name' => 'Wood CNC & Top Processing',
                'description' => 'Panel sizing, CNC routing, and PUR edge-banding station.',
                'capacity_per_hour' => 12.0,
                'cost_per_hour' => 420.00,
            ],
            'finish' => [
                'code' => 'WC-TBL-FINISH',
                'name' => 'Finishing & Powder Coating',
                'description' => 'Surface grinding, electrostatic powder spray, and curing oven.',
                'capacity_per_hour' => 10.0,
                'cost_per_hour' => 300.00,
            ],
            'assy' => [
                'code' => 'WC-TBL-ASSY',
                'name' => 'Dining Table Final Assembly',
                'description' => 'Mechanical fastener bolting, leveling, QC verification, and packing.',
                'capacity_per_hour' => 6.0,
                'cost_per_hour' => 350.00,
            ],
        ];

        $created = [];
        foreach ($wcs as $key => $data) {
            $created[$key] = WorkCenter::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $data['code']],
                [
                    'name' => $data['name'],
                    'work_center_type' => 'machining',
                    'description' => $data['description'],
                    'capacity_per_hour' => $data['capacity_per_hour'],
                    'efficiency_percentage' => 95.0,
                    'cost_per_hour' => $data['cost_per_hour'],
                    'status' => 'active',
                ]
            );
        }

        return $created;
    }

    private function seedMachines(int $tenantId, array $workCenters): array
    {
        $machines = [
            'cut_01' => [
                'code' => 'MAC-TBL-CUT-01',
                'name' => 'CNC Circular Saw Cutter 01',
                'work_center' => $workCenters['cut']->id,
            ],
            'cut_02' => [
                'code' => 'MAC-TBL-CUT-02',
                'name' => 'Band Saw Tube Cutter 02',
                'work_center' => $workCenters['cut']->id,
            ],
            'weld_01' => [
                'code' => 'MAC-TBL-WELD-01',
                'name' => 'MIG Pulse Welding Robot 01',
                'work_center' => $workCenters['weld']->id,
            ],
            'weld_02' => [
                'code' => 'MAC-TBL-WELD-02',
                'name' => 'Manual TIG Bench Welder 02',
                'work_center' => $workCenters['weld']->id,
            ],
            'top_01' => [
                'code' => 'MAC-TBL-TOP-01',
                'name' => '3-Axis CNC Wood Router 01',
                'work_center' => $workCenters['top']->id,
            ],
            'fin_01' => [
                'code' => 'MAC-TBL-FIN-01',
                'name' => 'Powder Coating Spray Booth 01',
                'work_center' => $workCenters['finish']->id,
            ],
            'assy_01' => [
                'code' => 'MAC-TBL-ASSY-01',
                'name' => 'Pneumatic Assembly Lift Station 01',
                'work_center' => $workCenters['assy']->id,
            ],
        ];

        $created = [];
        foreach ($machines as $key => $data) {
            $created[$key] = Machine::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $data['code']],
                [
                    'work_center_id' => $data['work_center'],
                    'name' => $data['name'],
                    'status' => Machine::STATUS_ACTIVE,
                ]
            );
        }

        return $created;
    }

    private function seedShifts(int $tenantId, array $workCenters): void
    {
        $companyId = Company::where('tenant_id', $tenantId)->value('id') ?? Company::value('id');

        $shift = ProductionShift::updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'SH-GEN'],
            [
                'company_id' => $companyId,
                'name' => 'General Plant Shift',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'break_minutes' => 60,
                'overtime_allowed' => true,
                'active' => true,
            ]
        );

        if (Schema::hasTable('production_work_center_shifts')) {
            foreach ($workCenters as $wc) {
                DB::table('production_work_center_shifts')->updateOrInsert(
                    [
                        'tenant_id' => $tenantId,
                        'work_center_id' => $wc->id,
                        'shift_id' => $shift->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedCalendars(int $tenantId): void
    {
        ProductionCalendar::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Standard Plant Manufacturing Calendar 2026'],
            [
                'working_days' => [1, 2, 3, 4, 5, 6],
                'is_default' => true,
            ]
        );
    }

    private function seedBomVariations(
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
        // ─── BOM 4: Table Leg Set Batch (Base Qty: 4 Pieces = 1 Set) ────────
        $bomLegB4 = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-LEG-BATCH4'],
            [
                'bom_name' => 'Table Leg Set Production BOM (4 Legs)',
                'product_id' => $sfgLeg->id,
                'base_quantity' => 4.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomLegB4->id, 'material_id' => $rmPipe->id],
            ['quantity' => 3.0, 'uom_id' => $mtr->id, 'sequence' => 1] // 0.75m * 4 = 3m
        );

        // ─── BOM 5: Horizontal Support High-Volume Batch (Base Qty: 50 Pieces) ──
        $bomSupportB50 = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-SUPPORT-B50'],
            [
                'bom_name' => 'Support Beam High-Volume Saw BOM (50 Pieces)',
                'product_id' => $sfgSupport->id,
                'base_quantity' => 50.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomSupportB50->id, 'material_id' => $rmPipe->id],
            ['quantity' => 30.0, 'uom_id' => $mtr->id, 'sequence' => 1] // 0.60m * 50 = 30m
        );

        // ─── BOM 2: Table Frame Batch BOM (Base Qty: 10 Pieces) ─────────────
        $bomFrameB10 = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-FRAME-B10'],
            [
                'bom_name' => 'Table Frame Batch Production BOM (10 Frames)',
                'product_id' => $sfgFrame->id,
                'base_quantity' => 10.0,
                'base_uom_id' => $pcs->id,
                'effective_date' => now()->toDateString(),
                'status' => 'approved',
                'bom_type' => 'manufacturing',
                'version' => '1.0',
                'created_by' => $userId,
            ]
        );

        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFrameB10->id, 'material_id' => $sfgLeg->id],
            ['quantity' => 40.0, 'uom_id' => $pcs->id, 'sequence' => 1, 'child_bom_id' => $bomLegB4->id]
        );
        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFrameB10->id, 'material_id' => $sfgSupport->id],
            ['quantity' => 20.0, 'uom_id' => $pcs->id, 'sequence' => 2, 'child_bom_id' => $bomSupportB50->id]
        );

        // ─── BOM 3: Table Top with Secondary Hardware (Base Qty: 1 Piece) ───
        $bomTopPartial = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-TOP-PARTIAL'],
            [
                'bom_name' => 'Engineered Wood Table Top with Inserts BOM',
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
            ['tenant_id' => $tenantId, 'bom_id' => $bomTopPartial->id, 'material_id' => $rmTopBoard->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );
        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomTopPartial->id, 'material_id' => $rmFastener->id],
            ['quantity' => 8.0, 'uom_id' => $pcs->id, 'sequence' => 2]
        );

        // ─── BOM 1: Standard FG Master BOM (Base Qty: 1 Piece) ─────────────
        $bomFgStd = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-TBL-FG-STD'],
            [
                'bom_name' => 'Industrial Dining Table Master BOM (1 Pcs)',
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
            ['tenant_id' => $tenantId, 'bom_id' => $bomFgStd->id, 'material_id' => $sfgFrame->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1, 'child_bom_id' => $bomFrameB10->id]
        );
        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFgStd->id, 'material_id' => $sfgTop->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 2, 'child_bom_id' => $bomTopPartial->id]
        );
        ProductionBomItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_id' => $bomFgStd->id, 'material_id' => $rmFastener->id],
            ['quantity' => 16.0, 'uom_id' => $pcs->id, 'sequence' => 3]
        );

        return [
            'fg_std'          => $bomFgStd,
            'frame_b10'       => $bomFrameB10,
            'top_partial'     => $bomTopPartial,
            'leg_b4'          => $bomLegB4,
            'support_b50'     => $bomSupportB50,
        ];
    }

    private function seedRoutingVariations(
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
        $rmFastener,
        array $workCenters,
        array $machines,
        array $boms,
        Vendor $vendor
    ): void {
        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO 1: per_unit Mode with Staged Consumption Across Operations
        // Product: FG Table | BOM: BOM-TBL-FG-STD (Base: 1 Pcs)
        // 100% Fully Allocated across Op 10, Op 20, Op 30.
        // Fasteners (16 Pcs) staged: 6 in Op 20, 10 in Op 30.
        // ─────────────────────────────────────────────────────────────────────
        $rt1 = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-FG-STAGED'],
            [
                'name' => 'Industrial Dining Table Multi-Stage Assembly (per_unit)',
                'product_id' => $fgTable->id,
                'bom_id' => $boms['fg_std']->id,
                'material_quantity_mode' => Routing::MODE_PER_UNIT,
                'status' => Routing::STATUS_ACTIVE,
                'version' => '1.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => now()->subMonths(1)->toDateString(),
                'description' => 'Standard dining table assembly demonstrating staged multi-operation material consumption in per_unit mode.',
                'created_by' => $userId,
            ]
        );

        // Op 10: Frame Alignment
        $op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt1->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Frame Lift & Station Fixture Alignment',
                'description' => 'Mount welded frame assembly onto assembly lift and check dimensional squaring.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 5.0,
                'processing_time_minutes' => 8.0,
                'labor_cost_rate' => 350.0 / 60.0,
                'expected_yield_percentage' => 100.0,
                'quality_required' => false,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op10->id, 'material_id' => $sfgFrame->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );

        // Op 20: Table Top Positioning & Initial Fastening
        $op20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt1->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'Table Top Placement & Initial Fastener Engagement',
                'description' => 'Place table top board flush onto steel frame. Hand-tighten 6 center anchor bolts.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 2.0,
                'processing_time_minutes' => 12.0,
                'labor_cost_rate' => 350.0 / 60.0,
                'expected_yield_percentage' => 100.0,
                'quality_required' => false,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op20->id, 'material_id' => $sfgTop->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op20->id, 'material_id' => $rmFastener->id],
            ['quantity' => 6.0, 'uom_id' => $pcs->id, 'sequence' => 2] // 6 of 16 fasteners
        );

        // Op 30: Final Perimeter Fastening & Torque Inspection
        $op30 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt1->id, 'sequence' => 30],
            [
                'operation_number' => 'OP30',
                'name' => 'Perimeter Bolt Torquing & 100% Quality Gate',
                'description' => 'Install remaining 10 perimeter bolts with pneumatic driver. Torque to 28 Nm.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 0.0,
                'processing_time_minutes' => 10.0,
                'labor_cost_rate' => 350.0 / 60.0,
                'expected_yield_percentage' => 100.0,
                'quality_required' => true,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $op30->id, 'material_id' => $rmFastener->id],
            ['quantity' => 10.0, 'uom_id' => $pcs->id, 'sequence' => 1] // remaining 10 of 16 fasteners
        );

        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO 2: per_bom_base_quantity Mode (Batch Qty = 10 Frames)
        // Product: SFG Table Frame | BOM: BOM-TBL-FRAME-B10 (Base: 10 Pieces)
        // Quantities entered on 10-piece batch basis (40 legs, 20 supports)
        // ─────────────────────────────────────────────────────────────────────
        $rt2 = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-FRAME-BATCH10'],
            [
                'name' => 'Table Frame Batch Fabrication Routing (per_bom_base_qty: 10)',
                'product_id' => $sfgFrame->id,
                'bom_id' => $boms['frame_b10']->id,
                'material_quantity_mode' => Routing::MODE_PER_BOM_BASE_QUANTITY,
                'status' => Routing::STATUS_ACTIVE,
                'version' => '1.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => now()->subMonths(1)->toDateString(),
                'description' => 'Frame fabrication entering batch component quantities per 10 finished frames (40 legs, 20 cross beams).',
                'created_by' => $userId,
            ]
        );

        $rt2Op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt2->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => '10-Frame Multi-Jig Leg Clamping',
                'description' => 'Load 40 fabricated steel legs into frame welding fixtures across batch run.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['weld']->id,
                'machine_id' => $machines['weld_01']->id,
                'setup_time_minutes' => 15.0,
                'processing_time_minutes' => 30.0,
                'labor_cost_rate' => 400.0 / 60.0,
                'expected_yield_percentage' => 100.0,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt2Op10->id, 'material_id' => $sfgLeg->id],
            ['quantity' => 40.0, 'uom_id' => $pcs->id, 'sequence' => 1] // Entered on 10-batch basis
        );

        $rt2Op20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt2->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'Cross-Beam Bracing & Tack Welding',
                'description' => 'Position 20 horizontal cross beams and tack-weld to corner legs.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['weld']->id,
                'machine_id' => $machines['weld_01']->id,
                'setup_time_minutes' => 5.0,
                'processing_time_minutes' => 25.0,
                'labor_cost_rate' => 400.0 / 60.0,
                'expected_yield_percentage' => 100.0,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt2Op20->id, 'material_id' => $sfgSupport->id],
            ['quantity' => 20.0, 'uom_id' => $pcs->id, 'sequence' => 1] // Entered on 10-batch basis
        );

        $rt2Op30 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt2->id, 'sequence' => 30],
            [
                'operation_number' => 'OP30',
                'name' => 'Full Continuous Seam MIG Welding & De-spatter',
                'description' => 'Continuous robotic seam welding and mechanical weld clean-up.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['weld']->id,
                'machine_id' => $machines['weld_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 45.0,
                'labor_cost_rate' => 400.0 / 60.0,
                'expected_yield_percentage' => 99.0,
                'quality_required' => true,
            ]
        );

        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO 3: Partial Allocation Scenario (Rule B Validation Warning)
        // Product: SFG Table Top | BOM: BOM-TBL-TOP-PARTIAL (Base: 1 Pcs)
        // Board: 1 Pcs (100% allocated in Op 10)
        // Fasteners: 8 Pcs required, but only 4 allocated in Op 20 (4 unallocated)
        // Triggers the Partial Allocation Notice banner in the UI!
        // ─────────────────────────────────────────────────────────────────────
        $rt3 = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TOP-PARTIAL'],
            [
                'name' => 'Engineered Wood Top Routing (Partial Allocation Demo)',
                'product_id' => $sfgTop->id,
                'bom_id' => $boms['top_partial']->id,
                'material_quantity_mode' => Routing::MODE_PER_UNIT,
                'status' => Routing::STATUS_DRAFT,
                'version' => '1.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => now()->toDateString(),
                'description' => 'Draft routing demonstrating Partial Allocation Notice (4 out of 8 hardware inserts unallocated).',
                'created_by' => $userId,
            ]
        );

        $rt3Op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt3->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'CNC Perimeter Profiling & Edge Sizing',
                'description' => 'Precision CNC cutting of engineered wood slab.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['top']->id,
                'machine_id' => $machines['top_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 15.0,
                'labor_cost_rate' => 420.0 / 60.0,
                'expected_yield_percentage' => 100.0,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt3Op10->id, 'material_id' => $rmTopBoard->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1] // 100% of board
        );

        $rt3Op20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt3->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'Corner Threaded Insert Fitting & Sealing',
                'description' => 'Press-fit threaded brass inserts into pre-drilled pilot holes.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 3.0,
                'processing_time_minutes' => 8.0,
                'labor_cost_rate' => 350.0 / 60.0,
                'expected_yield_percentage' => 100.0,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt3Op20->id, 'material_id' => $rmFastener->id],
            ['quantity' => 4.0, 'uom_id' => $pcs->id, 'sequence' => 1] // Only 4 of 8 allocated!
        );

        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO 4: Outsourced / Subcontract Operation with Upstream Material
        // Product: SFG Table Leg | BOM: BOM-TBL-LEG-BATCH4 (Base: 4 Pieces = 1 Set)
        // Op 10: In-house Tube Sawing (Consumes 3.0 Mtr steel pipe)
        // Op 20: External Electroplating / Surface Treatment (Vendor Subcontract)
        // Op 30: Final Deburring & Inspection
        // ─────────────────────────────────────────────────────────────────────
        $rt4 = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-LEG-OUTSOURCE'],
            [
                'name' => 'Table Leg 4-Piece Set with Subcontract Coating',
                'product_id' => $sfgLeg->id,
                'bom_id' => $boms['leg_b4']->id,
                'material_quantity_mode' => Routing::MODE_PER_BOM_BASE_QUANTITY,
                'status' => Routing::STATUS_ACTIVE,
                'version' => '1.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => now()->subMonths(1)->toDateString(),
                'description' => 'Demonstrates outsourced subcontract electroplating stage between in-house cutting and QC inspection.',
                'created_by' => $userId,
            ]
        );

        $rt4Op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt4->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Automated Cold Saw Cutting (4 Legs)',
                'description' => 'Precision cut four 750mm steel leg sections from raw pipe stock.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_01']->id,
                'setup_time_minutes' => 5.0,
                'processing_time_minutes' => 6.0,
                'labor_cost_rate' => 450.0 / 60.0,
                'expected_yield_percentage' => 100.0,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt4Op10->id, 'material_id' => $rmPipe->id],
            ['quantity' => 3.0, 'uom_id' => $mtr->id, 'sequence' => 1] // 3m pipe per 4-leg set
        );

        $rt4Op20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt4->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'External Chemical De-scaling & Matte Black Electroplating',
                'description' => 'Subcontract surface treatment for corrosion resistance and industrial matte black finish.',
                'operation_type' => 'outsourcing',
                'is_external' => true,
                'vendor_id' => $vendor->id,
                'material_supply_type' => 'company_supplied',
                'subcontract_input_type' => 'previous_operation_wip',
                'subcontract_lead_time_days' => 3,
                'subcontract_cost_per_unit' => 65.00,
                'dispatch_buffer_days' => 1,
                'return_buffer_days' => 1,
                'expected_yield_percentage' => 99.0,
            ]
        );

        $rt4Op30 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt4->id, 'sequence' => 30],
            [
                'operation_number' => 'OP30',
                'name' => 'Receiving Visual QC & Protective Sleeve Packaging',
                'description' => 'Check plated surface thickness and pack in protective mesh sleeves.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 0.0,
                'processing_time_minutes' => 4.0,
                'labor_cost_rate' => 350.0 / 60.0,
                'expected_yield_percentage' => 100.0,
                'quality_required' => true,
            ]
        );

        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO 5: High-Batch Automated Production (Base Qty: 50 Pieces)
        // Product: SFG Support Beam | BOM: BOM-TBL-SUPPORT-B50 (Base: 50 Pieces)
        // Raw pipe: 30 Mtr per batch of 50
        // ─────────────────────────────────────────────────────────────────────
        $rt5 = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-SUPPORT-B50'],
            [
                'name' => 'High-Speed Cross-Beam Saw Routing (per_bom_base_qty: 50)',
                'product_id' => $sfgSupport->id,
                'bom_id' => $boms['support_b50']->id,
                'material_quantity_mode' => Routing::MODE_PER_BOM_BASE_QUANTITY,
                'status' => Routing::STATUS_ACTIVE,
                'version' => '1.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => now()->subMonths(1)->toDateString(),
                'description' => 'High volume automated sawing batch entering 30 Mtr per 50 units (0.60 Mtr/unit).',
                'created_by' => $userId,
            ]
        );

        $rt5Op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt5->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Automatic Bar Feeder High-Speed Tube Saw Sizing',
                'description' => 'Feed full pipe bundles and precision cut 50 cross beams.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_02']->id,
                'setup_time_minutes' => 12.0,
                'processing_time_minutes' => 20.0,
                'labor_cost_rate' => 450.0 / 60.0,
                'expected_yield_percentage' => 99.5,
                'quality_required' => true,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt5Op10->id, 'material_id' => $rmPipe->id],
            ['quantity' => 30.0, 'uom_id' => $mtr->id, 'sequence' => 1] // 30 meters per 50 pieces
        );

        // ─────────────────────────────────────────────────────────────────────
        // SCENARIO 6: Alternative Revision Version V2 (Single-Cell Lean Flow)
        // Product: FG Table | BOM: BOM-TBL-FG-STD (Base: 1 Pcs)
        // Revision 1.0 (Draft V2.0) with single consolidated operation cell
        // ─────────────────────────────────────────────────────────────────────
        $rt6 = Routing::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_number' => 'RT-TBL-FG-V2-LEAN'],
            [
                'name' => 'Industrial Dining Table One-Piece Flow Cell Routing',
                'product_id' => $fgTable->id,
                'bom_id' => $boms['fg_std']->id,
                'material_quantity_mode' => Routing::MODE_PER_UNIT,
                'status' => Routing::STATUS_DRAFT,
                'version' => '2.0',
                'revision' => 1,
                'is_default' => false,
                'effective_from' => now()->toDateString(),
                'description' => 'Alternative revision V2 demonstrating a consolidated single-cell assembly consuming 100% of all BOM items.',
                'created_by' => $userId,
            ]
        );

        $rt6Op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rt6->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Lean Cell Complete Table Build & Package',
                'description' => 'Dedicated lean manufacturing cell assembling frame, top, and all fasteners in single unified station.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 5.0,
                'processing_time_minutes' => 25.0,
                'labor_cost_rate' => 350.0 / 60.0,
                'expected_yield_percentage' => 100.0,
                'quality_required' => true,
            ]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt6Op10->id, 'material_id' => $sfgFrame->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 1]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt6Op10->id, 'material_id' => $sfgTop->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id, 'sequence' => 2]
        );
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $rt6Op10->id, 'material_id' => $rmFastener->id],
            ['quantity' => 16.0, 'uom_id' => $pcs->id, 'sequence' => 3]
        );
    }
}
