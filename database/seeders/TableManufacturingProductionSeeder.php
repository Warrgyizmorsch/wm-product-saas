<?php

namespace Database\Seeders;

use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionAlertConfiguration;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionEcoApproval;
use App\Domains\Production\Models\ProductionEcoItem;
use App\Domains\Production\Models\ProductionKpiTarget;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionPmSchedule;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableManufacturingProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Step 0: Clean previous production scenario & transactional data ──
        Schema::disableForeignKeyConstraints();

        $tablesToTruncate = [
            // Transactional Execution & Progress (Cleaned for manual demo creation)
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

            // Masters (Cleanly reset & populated by this seeder)
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
            'production_dashboard_preferences',
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

            // 1. Work Centers (Enriched with Capacity & Costs)
            $workCenters = $this->seedWorkCenters($tenantId);

            // 2. Machines
            $machines = $this->seedMachines($tenantId, $workCenters);

            // 3. Shifts & Working Hours
            $this->seedShifts($tenantId, $workCenters);

            // 4. Plant Calendars & Holidays
            $this->seedCalendars($tenantId);

            // 5. Operator Skills
            $this->seedOperatorSkills($tenantId, $userId, $workCenters, $machines);

            // 6. Quality Plans & Parameters
            $this->seedQualityPlans($tenantId, $userId, $fgTable, $sfgFrame, $sfgTop);

            // 7. Multi-Level BOM Tree
            $boms = $this->seedBoms($tenantId, $userId, $pcs, $mtr, $fgTable, $sfgFrame, $sfgLeg, $sfgSupport, $sfgTop, $rmPipe, $rmTopBoard, $rmFastener);

            // 8. Master Routings & Operations (With Labor/Machine Costs & QC Enabled on FG)
            $routings = $this->seedRoutings($tenantId, $userId, $pcs, $fgTable, $sfgFrame, $sfgLeg, $sfgSupport, $sfgTop, $rmPipe, $rmTopBoard, $rmFastener, $workCenters, $machines, $boms, $vendor);

            // 9. Engineering Change Orders (ECO)
            $this->seedEcos($tenantId, $userId, $fgTable, $boms, $routings);

            // 10. Preventive Maintenance (PM) Schedules
            $this->seedPmSchedules($tenantId, $machines);

            // 11. KPI Targets & Alert Configurations
            $this->seedKpisAndAlerts($tenantId);
        });

        $this->command->info('Table Manufacturing Production Masters seeded successfully (Execution tables cleared for demo).');
    }

    private function seedWorkCenters(int $tenantId): array
    {
        $wcs = [
            'cut' => [
                'code' => 'WC-TBL-CUT',
                'name' => 'Tube & Component Cutting',
                'description' => 'CNC cold circular saw and automatic tube cutting section for steel square pipes and structural profiles.',
                'capacity_per_hour' => 12.0,
                'cost_per_hour' => 450.00,
            ],
            'weld' => [
                'code' => 'WC-TBL-WELD',
                'name' => 'Frame Welding & Fabrication',
                'description' => 'MIG welding cell and precision jig assembly section for heavy steel table frames and legs.',
                'capacity_per_hour' => 8.0,
                'cost_per_hour' => 380.00,
            ],
            'top' => [
                'code' => 'WC-TBL-TOP',
                'name' => 'Table Top Processing',
                'description' => 'Panel sizing saw cutting, PUR edge banding, and corner rounding section for engineered wood table tops.',
                'capacity_per_hour' => 10.0,
                'cost_per_hour' => 420.00,
            ],
            'finish' => [
                'code' => 'WC-TBL-FINISH',
                'name' => 'Surface Finishing & Coating',
                'description' => 'Weld seam grinding, shot blasting, and thermoset electrostatic powder coating preparation section.',
                'capacity_per_hour' => 10.0,
                'cost_per_hour' => 280.00,
            ],
            'assy' => [
                'code' => 'WC-TBL-ASSY',
                'name' => 'Dining Table Final Assembly',
                'description' => 'Final assembly bay for structural bolting, table top mounting, 100% QA inspection, and protective packaging.',
                'capacity_per_hour' => 6.0,
                'cost_per_hour' => 320.00,
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
                'name' => 'CNC Circular Saw Pipe Cutter 01 (Primary)',
                'work_center' => $workCenters['cut']->id,
            ],
            'cut_02' => [
                'code' => 'MAC-TBL-CUT-02',
                'name' => 'Automatic Band Saw Tube Cutter 02 (Alternate)',
                'work_center' => $workCenters['cut']->id,
            ],
            'weld_01' => [
                'code' => 'MAC-TBL-WELD-01',
                'name' => 'MIG Pulse Welding Station 01',
                'work_center' => $workCenters['weld']->id,
            ],
            'top_01' => [
                'code' => 'MAC-TBL-TOP-01',
                'name' => 'Precision Panel Saw & Edge Bander 01',
                'work_center' => $workCenters['top']->id,
            ],
            'fin_01' => [
                'code' => 'MAC-TBL-FIN-01',
                'name' => 'Angle Grinding & Surface Prep Station 01',
                'work_center' => $workCenters['finish']->id,
            ],
            'assy_01' => [
                'code' => 'MAC-TBL-ASSY-01',
                'name' => 'Hydraulic Lift Final Assembly Jig 01',
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

    private function seedShifts(int $tenantId, array $workCenters): void
    {
        $companyId = Company::where('tenant_id', $tenantId)->value('id')
            ?? Company::value('id')
            ?? 1;

        $shifts = [
            [
                'name' => 'Morning Production Shift',
                'code' => 'SH-MORN',
                'start_time' => '06:00:00',
                'end_time' => '14:00:00',
                'break_minutes' => 30,
                'overtime_allowed' => true,
                'active' => true,
            ],
            [
                'name' => 'Evening Production Shift',
                'code' => 'SH-EVE',
                'start_time' => '14:00:00',
                'end_time' => '22:00:00',
                'break_minutes' => 30,
                'overtime_allowed' => false,
                'active' => true,
            ],
        ];

        // Clear existing work center shift associations for this tenant
        DB::table('production_work_center_shifts')->where('tenant_id', $tenantId)->delete();

        foreach ($shifts as $data) {
            $shift = ProductionShift::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $data['code']],
                array_merge($data, ['company_id' => $companyId])
            );

            // Link shifts to all manufacturing work centers
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
        $calendar = ProductionCalendar::updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Standard Plant Manufacturing Calendar 2026'],
            [
                'working_days' => [1, 2, 3, 4, 5, 6], // Monday to Saturday (Sunday off)
                'is_default' => true,
            ]
        );

        $holidays = [
            ['name' => 'Republic Day', 'holiday_date' => '2026-01-26', 'holiday_type' => 'gazetted', 'description' => 'National Holiday'],
            ['name' => 'Maharashtra Day / May Day', 'holiday_date' => '2026-05-01', 'holiday_type' => 'state', 'description' => 'State Labour Day'],
            ['name' => 'Independence Day', 'holiday_date' => '2026-08-15', 'holiday_type' => 'gazetted', 'description' => 'National Holiday'],
            ['name' => 'Gandhi Jayanti', 'holiday_date' => '2026-10-02', 'holiday_type' => 'gazetted', 'description' => 'National Holiday'],
            ['name' => 'Diwali Festival', 'holiday_date' => '2026-11-08', 'holiday_type' => 'festival', 'description' => 'Annual Festival of Lights'],
        ];

        foreach ($holidays as $h) {
            ProductionCalendarHoliday::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'production_calendar_id' => $calendar->id,
                    'name' => $h['name'],
                ],
                [
                    'holiday_date' => $h['holiday_date'],
                    'holiday_type' => $h['holiday_type'],
                    'description' => $h['description'],
                    'is_full_day' => true,
                    'active' => true,
                ]
            );
        }
    }

    private function seedOperatorSkills(int $tenantId, int $userId, array $workCenters, array $machines): void
    {
        $skills = [
            [
                'skill_code' => 'SKL-CNC-CUT',
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_01']->id,
            ],
            [
                'skill_code' => 'SKL-WELD-MIG',
                'work_center_id' => $workCenters['weld']->id,
                'machine_id' => $machines['weld_01']->id,
            ],
            [
                'skill_code' => 'SKL-WOOD-FIN',
                'work_center_id' => $workCenters['top']->id,
                'machine_id' => $machines['top_01']->id,
            ],
            [
                'skill_code' => 'SKL-POWDER-COAT',
                'work_center_id' => $workCenters['finish']->id,
                'machine_id' => $machines['fin_01']->id,
            ],
            [
                'skill_code' => 'SKL-FINAL-QC',
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
            ],
        ];

        foreach ($skills as $s) {
            ProductionOperatorSkill::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'skill_code' => $s['skill_code'],
                ],
                [
                    'work_center_id' => $s['work_center_id'],
                    'machine_id' => $s['machine_id'],
                    'active' => true,
                ]
            );
        }
    }

    private function seedQualityPlans(int $tenantId, int $userId, Product $fgTable, Product $sfgFrame, Product $sfgTop): ProductionQualityPlan
    {
        // 1. Master In-Process Quality Plan
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

        // 3. Final Assembly & Stability Quality Plan (Used for OP40 QC Gate)
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

        RoutingOperation::where('routing_id', $rtLeg->id)->delete();

        $op10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtLeg->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Table Leg Pipe Cutting & Deburring',
                'description' => 'Precision cut hollow square steel pipe to 750mm and deburr cut ends.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 2.0,
                'labor_cost_rate' => 250.00,
                'machine_cost_rate' => 180.00,
                'expected_yield_percentage' => 98.0,
                'instructions' => 'Set CNC stop to 750mm (+/- 1.5mm). Ensure clean cut without thermal discoloration.',
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

        RoutingOperation::where('routing_id', $rtSupport->id)->delete();

        $suppOp10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtSupport->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Horizontal Support Pipe Sizing',
                'description' => 'Cut structural cross support pipes to 600mm with mitered 45-degree ends.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['cut']->id,
                'machine_id' => $machines['cut_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 2.0,
                'labor_cost_rate' => 250.00,
                'machine_cost_rate' => 180.00,
                'expected_yield_percentage' => 98.0,
                'instructions' => 'Verify miter angle 45 degrees for flush corner welding joints.',
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $suppOp10->id, 'material_id' => $rmPipe->id],
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

        RoutingOperation::where('routing_id', $rtFrame->id)->delete();

        $frameOp10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFrame->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Frame MIG Welding Assembly',
                'description' => 'Clamp 4 legs and 2 support pipes in fabrication jig and MIG weld all joint perimeters.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['weld']->id,
                'machine_id' => $machines['weld_01']->id,
                'setup_time_minutes' => 15.0,
                'processing_time_minutes' => 12.0,
                'labor_cost_rate' => 350.00,
                'machine_cost_rate' => 150.00,
                'expected_yield_percentage' => 97.0,
                'instructions' => 'Clamp tightly in jig. Check diagonal measurements across corners before continuous welding.',
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $frameOp10->id, 'material_id' => $sfgLeg->id],
            ['quantity' => 4.0, 'uom_id' => $pcs->id]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $frameOp10->id, 'material_id' => $sfgSupport->id],
            ['quantity' => 2.0, 'uom_id' => $pcs->id]
        );

        $frameOp20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFrame->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'Frame Surface Finishing & Powder Coating',
                'description' => 'Grind weld seams flush, degrease, and apply matte black architectural powder coating.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['finish']->id,
                'machine_id' => $machines['fin_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 6.0,
                'labor_cost_rate' => 250.00,
                'machine_cost_rate' => 120.00,
                'expected_yield_percentage' => 99.0,
                'instructions' => 'Inspect for weld splatter removal before powder coat application.',
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

        RoutingOperation::where('routing_id', $rtTop->id)->delete();

        $topOp10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtTop->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Table Top Panel Sizing & PUR Edge Banding',
                'description' => 'Panel saw sizing, corner easing, and heavy-duty 2mm PVC edge banding application.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['top']->id,
                'machine_id' => $machines['top_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 8.0,
                'labor_cost_rate' => 280.00,
                'machine_cost_rate' => 180.00,
                'expected_yield_percentage' => 98.0,
                'instructions' => 'Check glue pot temperature (190C). Inspect edge band seam for zero void line.',
                'quality_required' => false,
            ]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $topOp10->id, 'material_id' => $rmTopBoard->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        $boms['top']->update(['routing_id' => $rtTop->id]);

        // 5. Industrial Dining Table Master FG Routing (Sequences 10, 20, 30)
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

        // Clear existing operations for RT-TBL-FG to clean up legacy sequences
        RoutingOperation::where('routing_id', $rtFg->id)->delete();

        // OP10: Final Dining Table Assembly
        $fgOp10 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFg->id, 'sequence' => 10],
            [
                'operation_number' => 'OP10',
                'name' => 'Final Dining Table Assembly',
                'description' => 'Assemble legs, welded frame, and table top using heavy-duty fastener bolts.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 10.0,
                'processing_time_minutes' => 15.0,
                'labor_cost_rate' => 320.00,
                'machine_cost_rate' => 120.00,
                'expected_yield_percentage' => 99.0,
                'instructions' => 'Torque all structural bolts to 28 Nm. Align table top flush with frame perimeter.',
                'quality_required' => false,
            ]
        );

        // Consumes 1 Frame, 1 Top, and 1 Fastener Set per Table
        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfgFrame->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfgTop->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        RoutingOperationMaterial::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_operation_id' => $fgOp10->id, 'material_id' => $rmFastener->id],
            ['quantity' => 1.0, 'uom_id' => $pcs->id]
        );

        // OP20: Final Quality Inspection & Stability Testing (MANDATORY QC GATE ENABLED)
        $fgOp20 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFg->id, 'sequence' => 20],
            [
                'operation_number' => 'OP20',
                'name' => 'Final Quality Inspection & Stability Testing',
                'description' => 'Mandatory QA check for diagonal squareness, load stability, leveling feet, and surface finish.',
                'operation_type' => RoutingOperation::TYPE_INSPECTION,
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 5.0,
                'processing_time_minutes' => 10.0,
                'labor_cost_rate' => 350.00,
                'machine_cost_rate' => 80.00,
                'expected_yield_percentage' => 100.0,
                'instructions' => 'Perform 100% inspection on leveling feet, check diagonal stability under load, and verify bolt torque at 28 Nm.',
                'quality_required' => true, // << QC CHECK ENABLED!
            ]
        );

        // OP30: Protective Packaging & Palletizing
        $fgOp30 = RoutingOperation::updateOrCreate(
            ['tenant_id' => $tenantId, 'routing_id' => $rtFg->id, 'sequence' => 30],
            [
                'operation_number' => 'OP30',
                'name' => 'Protective Packaging & Palletizing',
                'description' => 'Attach edge corner protectors, shrink wrap table, and apply finished goods barcode label.',
                'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
                'work_center_id' => $workCenters['assy']->id,
                'machine_id' => $machines['assy_01']->id,
                'setup_time_minutes' => 5.0,
                'processing_time_minutes' => 8.0,
                'labor_cost_rate' => 220.00,
                'machine_cost_rate' => 50.00,
                'expected_yield_percentage' => 100.0,
                'instructions' => 'Fit 4 corner foam guards, wrap in heavy gauge polythene, and scan serialized barcode label.',
                'quality_required' => false,
            ]
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

    private function seedEcos(int $tenantId, int $userId, Product $fgTable, array $boms, array $routings): void
    {
        // ECO 1: BOM Component Specification Upgrade (Approved)
        $eco1 = ProductionEco::updateOrCreate(
            ['tenant_id' => $tenantId, 'eco_number' => 'ECO-2026-001'],
            [
                'title' => 'Upgrade Table Fasteners to Stainless Steel Grade 304',
                'description' => 'Engineering change to replace standard galvanized fastener screws with Grade 304 stainless steel hardware to prevent corrosion in humid environments.',
                'reason' => 'Customer feedback and warranty reduction initiative.',
                'change_type' => ProductionEco::CHANGE_TYPE_BOM,
                'product_id' => $fgTable->id,
                'current_bom_id' => $boms['fg']->id,
                'current_bom_revision' => 1,
                'proposed_bom_revision' => 2,
                'effective_date' => now()->addDays(7)->toDateString(),
                'status' => ProductionEco::STATUS_APPROVED,
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now()->subDay(),
            ]
        );

        ProductionEcoItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'eco_id' => $eco1->id, 'entity_type' => ProductionEcoItem::ENTITY_BOM],
            [
                'action_type' => ProductionEcoItem::ACTION_REPLACE_COMPONENT,
                'target_id' => $boms['fg']->id,
                'old_value' => ['material' => 'Galvanized Steel Screws M8', 'grade' => 'Mild Steel'],
                'new_value' => ['material' => 'Stainless Steel Screws M8', 'grade' => 'SS-304'],
                'notes' => 'Direct drop-in replacement with zero routing tooling impact.',
            ]
        );

        ProductionEcoApproval::updateOrCreate(
            ['tenant_id' => $tenantId, 'eco_id' => $eco1->id, 'user_id' => $userId, 'action' => 'approved'],
            [
                'comments' => 'Approved by Chief Design Engineer. Validated against salt spray testing requirements.',
            ]
        );

        // ECO 2: Routing Assembly Optimization (Under Review)
        $eco2 = ProductionEco::updateOrCreate(
            ['tenant_id' => $tenantId, 'eco_number' => 'ECO-2026-002'],
            [
                'title' => 'Introduction of Dedicated QC Gate & Bolt Torque Inspection',
                'description' => 'Introduce mandatory OP20 Quality Inspection gate in the master routing before packaging to guarantee 100% stability and zero loose bolts.',
                'reason' => 'Six Sigma quality stabilization program.',
                'change_type' => ProductionEco::CHANGE_TYPE_ROUTING,
                'product_id' => $fgTable->id,
                'current_routing_id' => $routings['fg']->id,
                'current_routing_revision' => 1,
                'proposed_routing_revision' => 2,
                'effective_date' => now()->addDays(14)->toDateString(),
                'status' => ProductionEco::STATUS_UNDER_REVIEW,
                'created_by' => $userId,
            ]
        );

        ProductionEcoItem::updateOrCreate(
            ['tenant_id' => $tenantId, 'eco_id' => $eco2->id, 'entity_type' => ProductionEcoItem::ENTITY_ROUTING],
            [
                'action_type' => ProductionEcoItem::ACTION_ADD_OPERATION,
                'target_id' => $routings['fg']->id,
                'old_value' => ['operation' => 'None (Integrated in Packaging)'],
                'new_value' => ['operation' => 'OP20 Final Quality Inspection', 'quality_required' => true],
                'notes' => 'Separate QA inspection step to record digital inspection parameter logs.',
            ]
        );
    }

    private function seedPmSchedules(int $tenantId, array $machines): void
    {
        $schedules = [
            [
                'machine_id' => $machines['cut_01']->id,
                'name' => 'Weekly Circular Saw Blade Inspection & Lubrication',
                'code' => 'PM-CUT-01-WK',
                'maintenance_type' => ProductionPmSchedule::TYPE_PREVENTIVE,
                'frequency_type' => ProductionPmSchedule::FREQ_WEEKS,
                'frequency_value' => 1,
                'last_completed_date' => now()->subDays(5)->toDateString(),
                'next_due_date' => now()->addDays(2)->toDateString(),
                'estimated_duration_hours' => 1.5,
                'priority' => ProductionPmSchedule::PRIORITY_MEDIUM,
                'checklist_json' => [
                    'Inspect carbide teeth on circular saw blade for chipping',
                    'Check pneumatic clamp pressure gauge (min 6 bar)',
                    'Top up cutting fluid coolant reservoir (1:10 water-oil emulsion)',
                    'Grease linear slide bearing rails',
                ],
            ],
            [
                'machine_id' => $machines['weld_01']->id,
                'name' => 'Monthly MIG Torch Liner & Grounding Cable Check',
                'code' => 'PM-WELD-01-MO',
                'maintenance_type' => ProductionPmSchedule::TYPE_PREVENTIVE,
                'frequency_type' => ProductionPmSchedule::FREQ_MONTHS,
                'frequency_value' => 1,
                'last_completed_date' => now()->subDays(20)->toDateString(),
                'next_due_date' => now()->addDays(10)->toDateString(),
                'estimated_duration_hours' => 2.0,
                'priority' => ProductionPmSchedule::PRIORITY_HIGH,
                'checklist_json' => [
                    'Replace contact tip and clean nozzle gas diffuser',
                    'Check shielding gas flow rate with flowmeter (15 L/min Ar/CO2)',
                    'Inspect earth clamp and cable insulation for burns',
                    'Blow out wire feeder drive rolls with dry compressed air',
                ],
            ],
            [
                'machine_id' => $machines['top_01']->id,
                'name' => 'Monthly Edge Bander Glue Pot Cleaning & Cutter Calibration',
                'code' => 'PM-TOP-01-MO',
                'maintenance_type' => ProductionPmSchedule::TYPE_PREVENTIVE,
                'frequency_type' => ProductionPmSchedule::FREQ_MONTHS,
                'frequency_value' => 1,
                'last_completed_date' => now()->subDays(15)->toDateString(),
                'next_due_date' => now()->addDays(15)->toDateString(),
                'estimated_duration_hours' => 2.5,
                'priority' => ProductionPmSchedule::PRIORITY_MEDIUM,
                'checklist_json' => [
                    'Drain and scrape residual PUR glue pot',
                    'Inspect diamond edge trimming cutters for wear',
                    'Calibrate buffing wheel height and pressure',
                    'Test emergency stop trip cords along feed conveyor',
                ],
            ],
            [
                'machine_id' => $machines['fin_01']->id,
                'name' => 'Bi-Weekly Grinding Station Dust Collector Filter Cleaning',
                'code' => 'PM-FIN-01-BW',
                'maintenance_type' => ProductionPmSchedule::TYPE_PREVENTIVE,
                'frequency_type' => ProductionPmSchedule::FREQ_WEEKS,
                'frequency_value' => 2,
                'last_completed_date' => now()->subDays(10)->toDateString(),
                'next_due_date' => now()->addDays(4)->toDateString(),
                'estimated_duration_hours' => 1.0,
                'priority' => ProductionPmSchedule::PRIORITY_MEDIUM,
                'checklist_json' => [
                    'Empty metal swarf cyclone separator collection bin',
                    'Pulse reverse air filter cartridges',
                    'Inspect flexible grinding hood exhaust ducting for tears',
                ],
            ],
        ];

        foreach ($schedules as $data) {
            ProductionPmSchedule::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $data['code']],
                array_merge($data, ['is_active' => true])
            );
        }
    }

    private function seedKpisAndAlerts(int $tenantId): void
    {
        $kpis = [
            ['kpi_name' => 'Overall Equipment Effectiveness (OEE)', 'target_value' => 85.0],
            ['kpi_name' => 'Machine Availability', 'target_value' => 92.0],
            ['kpi_name' => 'Performance Efficiency', 'target_value' => 95.0],
            ['kpi_name' => 'First Pass Quality Yield', 'target_value' => 98.5],
            ['kpi_name' => 'Maximum Scrap Rate Allowance', 'target_value' => 1.5],
            ['kpi_name' => 'On-Time In-Full Delivery (OTIF)', 'target_value' => 96.0],
        ];

        foreach ($kpis as $kpi) {
            ProductionKpiTarget::updateOrCreate(
                ['tenant_id' => $tenantId, 'kpi_name' => $kpi['kpi_name']],
                ['target_value' => $kpi['target_value']]
            );
        }

        $alerts = [
            [
                'alert_type' => 'downtime_exceeded',
                'threshold' => 30.0, // minutes
                'severity' => 'warning',
                'active' => true,
            ],
            [
                'alert_type' => 'scrap_rate_high',
                'threshold' => 3.0, // percent
                'severity' => 'critical',
                'active' => true,
            ],
            [
                'alert_type' => 'yield_below_target',
                'threshold' => 90.0, // percent
                'severity' => 'critical',
                'active' => true,
            ],
            [
                'alert_type' => 'qc_failure_spike',
                'threshold' => 2.0, // incidents per shift
                'severity' => 'warning',
                'active' => true,
            ],
        ];

        foreach ($alerts as $a) {
            ProductionAlertConfiguration::updateOrCreate(
                ['tenant_id' => $tenantId, 'alert_type' => $a['alert_type']],
                $a
            );
        }
    }
}
