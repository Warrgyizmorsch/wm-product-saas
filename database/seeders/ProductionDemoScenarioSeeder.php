<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanRequirement;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOperatorAssignmentLog;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityInspectionResult;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\ProductionKpiTarget;
use App\Domains\Production\Models\ProductionAlertConfiguration;
use App\Domains\Production\Models\ProductionEventTimeline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionDemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign keys for cleanup
        Schema::disableForeignKeyConstraints();
        DB::table('production_plans')->truncate();
        DB::table('production_plan_requirements')->truncate();
        DB::table('production_plan_operations')->truncate();
        DB::table('production_orders')->truncate();
        DB::table('production_order_operations')->truncate();
        DB::table('production_order_reservations')->truncate();
        DB::table('production_order_progress_logs')->truncate();
        DB::table('production_order_receipts')->truncate();
        DB::table('production_order_issues')->truncate();
        DB::table('production_schedules')->truncate();
        DB::table('production_schedule_operations')->truncate();
        DB::table('production_shifts')->truncate();
        DB::table('production_calendars')->truncate();
        DB::table('production_operator_skills')->truncate();
        DB::table('production_operator_assignments')->truncate();
        DB::table('production_operator_assignment_logs')->truncate();
        DB::table('production_batches')->truncate();
        DB::table('production_batch_genealogies')->truncate();
        DB::table('production_serial_numbers')->truncate();
        DB::table('production_lot_traces')->truncate();
        DB::table('production_scan_logs')->truncate();
        DB::table('production_machine_state_histories')->truncate();
        DB::table('production_machine_downtimes')->truncate();
        DB::table('production_quality_plans')->truncate();
        DB::table('production_quality_plan_parameters')->truncate();
        DB::table('production_quality_inspections')->truncate();
        DB::table('production_quality_inspection_results')->truncate();
        DB::table('production_ncrs')->truncate();
        DB::table('production_capas')->truncate();
        DB::table('production_rework_orders')->truncate();
        DB::table('production_rework_operations')->truncate();
        DB::table('production_scrap_disposals')->truncate();
        DB::table('production_kpi_targets')->truncate();
        DB::table('production_alert_configurations')->truncate();
        DB::table('production_event_timelines')->truncate();
        Schema::enableForeignKeyConstraints();

        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();
        if (!$tenant) {
            return;
        }

        // Fetch Master Users
        $adminUser = User::where('tenant_id', $tenant->id)->first();
        $userId = $adminUser ? $adminUser->id : 1;

        // Fetch UOMs
        $pcs = Uom::where('tenant_id', $tenant->id)->where('code', 'PCS')->first();
        $m = Uom::where('tenant_id', $tenant->id)->where('code', 'M')->first();

        // Fetch Products
        $ebike = Product::where('tenant_id', $tenant->id)->where('sku', 'FG-EBIKE-X1')->first();
        $frameAssy = Product::where('tenant_id', $tenant->id)->where('sku', 'SF-FRAME-ASSY')->first();
        $battPack = Product::where('tenant_id', $tenant->id)->where('sku', 'SF-BATT-48V')->first();

        // Fetch BOMs & Routings
        $bomEbike = ProductionBom::where('tenant_id', $tenant->id)->where('product_id', $ebike->id)->first();
        $rtEbike = Routing::where('tenant_id', $tenant->id)->where('product_id', $ebike->id)->first();

        // Fetch Machines
        $machTig = Machine::where('tenant_id', $tenant->id)->where('code', 'MCH-TIG-200')->first();
        $machSpot = Machine::where('tenant_id', $tenant->id)->where('code', 'MCH-SPOT-500')->first();
        $machLift = Machine::where('tenant_id', $tenant->id)->where('code', 'MCH-LIFT-100')->first();

        // Fetch Work Centers
        $wcTig = WorkCenter::where('tenant_id', $tenant->id)->where('code', 'WC-TIG-WELD')->first();
        $wcCellWeld = WorkCenter::where('tenant_id', $tenant->id)->where('code', 'WC-CELL-WELD')->first();
        $wcMainLine = WorkCenter::where('tenant_id', $tenant->id)->where('code', 'WC-MAIN-LINE')->first();

        // --- 0. SEED SHIFTS & CALENDARS ---
        $shiftDay = ProductionShift::create([
            'tenant_id'  => $tenant->id,
            'name'       => 'Day Shift',
            'code'       => 'SHIFT-DAY',
            'start_time' => '08:00:00',
            'end_time'   => '16:00:00',
            'active'     => true,
        ]);

        $calendar = ProductionCalendar::create([
            'tenant_id'    => $tenant->id,
            'name'         => 'Standard 2026 Calendar',
            'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'is_default'   => true,
        ]);

        // --- 0.5 SEED KPI TARGETS & ALERTS ---
        $kpis = [
            'oee'          => 85.00,
            'availability' => 90.00,
            'performance'  => 95.00,
            'quality'      => 99.00,
            'throughput'   => 150.00,
            'utilization'  => 80.00,
            'scrap_rate'   => 2.00,
            'downtime'     => 10.00,
        ];
        foreach ($kpis as $name => $val) {
            ProductionKpiTarget::create([
                'tenant_id'    => $tenant->id,
                'kpi_name'     => $name,
                'target_value' => $val,
            ]);
        }

        ProductionAlertConfiguration::create([
            'tenant_id'      => $tenant->id,
            'alert_type'     => 'downtime_duration',
            'threshold'      => 30.00, // 30 minutes
            'severity'       => 'critical',
            'active'         => true,
        ]);
        ProductionAlertConfiguration::create([
            'tenant_id'      => $tenant->id,
            'alert_type'     => 'scrap_rate',
            'threshold'      => 5.00, // 5%
            'severity'       => 'warning',
            'active'         => true,
        ]);

        // --- 0.75 SEED QUALITY PLANS ---
        $qPlan = ProductionQualityPlan::create([
            'tenant_id'      => $tenant->id,
            'name'           => 'E-Bike Final Integration Test Plan',
            'version'        => '1.0.0',
            'status'         => 'approved',
            'type'           => 'final',
            'product_id'     => $ebike->id,
            'work_center_id' => $wcMainLine->id,
            'created_by'     => $userId,
            'approved_by'    => $userId,
            'approved_at'    => now(),
        ]);

        $param1 = ProductionQualityPlanParameter::create([
            'tenant_id'       => $tenant->id,
            'quality_plan_id' => $qPlan->id,
            'name'            => 'Brake Distance Audit',
            'type'            => 'numeric',
            'min_value'       => 1.50,
            'max_value'       => 3.50,
            'unit_of_measure' => 'meters',
            'is_mandatory'    => true,
        ]);

        $param2 = ProductionQualityPlanParameter::create([
            'tenant_id'       => $tenant->id,
            'quality_plan_id' => $qPlan->id,
            'name'            => 'Electrical Continuity Inspection',
            'type'            => 'pass_fail',
            'is_mandatory'    => true,
        ]);

        // --- 0.85 SEED OPERATOR SKILLS ---
        ProductionOperatorSkill::create([
            'tenant_id'      => $tenant->id,
            'user_id'        => $userId,
            'skill_code'     => 'SKILL-WIRING',
            'work_center_id' => $wcMainLine->id,
            'active'         => true,
        ]);

        // ==========================================
        // SCENARIO 1: PERFECT PRODUCTION (Succeeds)
        // ==========================================
        $plan1 = ProductionPlan::create([
            'tenant_id'        => $tenant->id,
            'plan_number'      => 'PLAN-2026-S1',
            'name'             => 'Scenario 1: High Yield Perfect Run',
            'product_id'       => $ebike->id,
            'quantity'         => 10.00,
            'start_date'       => Carbon::now()->subDays(5),
            'end_date'         => Carbon::now()->subDays(2),
            'bom_id'           => $bomEbike->id,
            'routing_id'       => $rtEbike->id,
            'status'           => 'released',
        ]);

        $ord1 = ProductionOrder::create([
            'tenant_id'          => $tenant->id,
            'order_number'       => 'ORD-2026-001',
            'production_plan_id' => $plan1->id,
            'product_id'         => $ebike->id,
            'bom_id'             => $bomEbike->id,
            'routing_id'         => $rtEbike->id,
            'quantity_ordered'   => 10.00,
            'quantity_produced'  => 10.00,
            'start_date'         => Carbon::now()->subDays(5),
            'end_date'           => Carbon::now()->subDays(2),
            'status'             => 'closed',
            'created_by'         => $userId,
        ]);

        // Order Operations
        $op10 = ProductionOrderOperation::create([
            'tenant_id'               => $tenant->id,
            'production_order_id'     => $ord1->id,
            'sequence'                => 10,
            'operation_number'        => 'OP-010',
            'name'                    => 'Mechanical Chassis Assembly',
            'work_center_id'          => $wcMainLine->id,
            'status'                  => 'completed',
            'setup_time_planned'      => 10,
            'processing_time_planned' => 150,
            'total_time_planned'      => 160,
            'setup_time_actual'       => 10,
            'processing_time_actual'  => 145,
            'quantity_produced'       => 10,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id'               => $tenant->id,
            'production_order_id'     => $ord1->id,
            'sequence'                => 20,
            'operation_number'        => 'OP-020',
            'name'                    => 'Battery and Electronics Integration',
            'work_center_id'          => $wcMainLine->id,
            'status'                  => 'completed',
            'setup_time_planned'      => 15,
            'processing_time_planned' => 300,
            'total_time_planned'      => 315,
            'setup_time_actual'       => 15,
            'processing_time_actual'  => 290,
            'quantity_produced'       => 10,
            'previous_operation_id'   => $op10->id,
        ]);

        // Reservations
        ProductionOrderReservation::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $ord1->id,
            'product_id'          => $frameAssy->id,
            'quantity_planned'    => 10.00,
            'quantity_reserved'   => 10.00,
            'quantity_issued'     => 10.00,
            'uom_id'              => $pcs->id,
        ]);
        ProductionOrderReservation::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $ord1->id,
            'product_id'          => $battPack->id,
            'quantity_planned'    => 10.00,
            'quantity_reserved'   => 10.00,
            'quantity_issued'     => 10.00,
            'uom_id'              => $pcs->id,
        ]);

        // Progress Logs
        ProductionOrderProgressLog::create([
            'tenant_id'            => $tenant->id,
            'production_order_id'  => $ord1->id,
            'operation_id'         => $op10->id,
            'quantity_produced'    => 10,
            'setup_minutes_logged' => 10,
            'run_minutes_logged'   => 145,
            'recorded_by'          => $userId,
            'recorded_at'          => Carbon::now()->subDays(4),
            'start_time'           => Carbon::now()->subDays(4)->subMinutes(160),
            'stop_time'            => Carbon::now()->subDays(4),
        ]);

        // Schedule
        $sched1 = ProductionSchedule::create([
            'tenant_id'           => $tenant->id,
            'schedule_number'     => 'SCHED-2026-001',
            'production_order_id' => $ord1->id,
            'scheduling_type'     => 'forward',
            'status'              => 'completed',
            'scheduled_at'        => Carbon::now()->subDays(5),
            'completed_at'        => Carbon::now()->subDays(2),
            'created_by'          => $userId,
        ]);

        ProductionScheduleOperation::create([
            'tenant_id'                     => $tenant->id,
            'production_schedule_id'        => $sched1->id,
            'production_order_id'           => $ord1->id,
            'production_order_operation_id' => $op10->id,
            'sequence'                      => 10,
            'status'                        => 'completed',
            'work_center_id'                => $wcMainLine->id,
            'planned_start'                 => Carbon::now()->subDays(5),
            'planned_finish'                => Carbon::now()->subDays(4),
            'actual_start'                  => Carbon::now()->subDays(5),
            'actual_finish'                 => Carbon::now()->subDays(4),
        ]);

        // Operator Assignments
        ProductionOperatorAssignment::create([
            'tenant_id'                    => $tenant->id,
            'production_order_operation_id'=> $op10->id,
            'user_id'                      => $userId,
            'assigned_by'                  => $userId,
            'assigned_at'                  => Carbon::now()->subDays(5),
            'status'                       => 'accepted',
        ]);

        // Batch & Serials & Scans
        $batch1 = ProductionBatch::create([
            'tenant_id'           => $tenant->id,
            'batch_number'        => 'BAT-2026-PERFECT',
            'production_order_id' => $ord1->id,
            'product_id'          => $ebike->id,
            'planned_quantity'    => 10.00,
            'actual_quantity'     => 10.00,
            'status'              => 'completed',
        ]);

        for ($i = 1; $i <= 10; $i++) {
            ProductionSerialNumber::create([
                'tenant_id'           => $tenant->id,
                'production_order_id' => $ord1->id,
                'batch_id'            => $batch1->id,
                'product_id'          => $ebike->id,
                'serial_number'       => 'SN-PERF-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'status'              => 'produced',
            ]);
        }

        ProductionScanLog::create([
            'tenant_id'         => $tenant->id,
            'entity_type'       => 'order',
            'entity_id'         => $ord1->id,
            'scan_type'         => 'order',
            'scanned_by'        => $userId,
            'device_identifier' => 'SCANNER-MAIN-01',
            'scanned_at'        => Carbon::now()->subDays(3),
        ]);

        // Quality Inspection
        $insp1 = ProductionQualityInspection::create([
            'tenant_id'           => $tenant->id,
            'quality_plan_id'     => $qPlan->id,
            'stage'               => 'final',
            'status'              => 'approved',
            'result'              => 'passed',
            'production_order_id' => $ord1->id,
            'batch_id'            => $batch1->id,
            'audited_by'          => $userId,
            'audited_at'          => Carbon::now()->subDays(2),
            'esignature'          => hash('sha256', 'perf_signature'),
        ]);

        ProductionQualityInspectionResult::create([
            'tenant_id'                 => $tenant->id,
            'quality_inspection_id'     => $insp1->id,
            'quality_plan_parameter_id' => $param1->id,
            'recorded_value_numeric'    => 2.45,
            'result'                    => 'passed',
        ]);
        ProductionQualityInspectionResult::create([
            'tenant_id'                 => $tenant->id,
            'quality_inspection_id'     => $insp1->id,
            'quality_plan_parameter_id' => $param2->id,
            'recorded_value_pass'       => true,
            'result'                    => 'passed',
        ]);

        // Goods Receipt
        ProductionOrderReceipt::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $ord1->id,
            'product_id'          => $ebike->id,
            'quantity_received'   => 10.00,
            'quality_status'      => 'passed',
            'received_by'         => $userId,
            'received_at'         => Carbon::now()->subDays(2),
        ]);

        // Timeline Event
        ProductionEventTimeline::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $ord1->id,
            'event_type'          => 'Production Closed',
            'title'               => 'Scenario 1 Complete',
            'description'         => 'Order ORD-2026-001 has been fully verified and closed.',
            'severity'            => 'success',
            'event_source'        => 'Seeder',
            'triggered_by'        => $userId,
            'event_time'          => Carbon::now()->subDays(2),
        ]);

        // ==========================================
        // SCENARIO 2: MACHINE BREAKDOWN (Downtime)
        // ==========================================
        ProductionMachineStateHistory::create([
            'tenant_id'   => $tenant->id,
            'machine_id'  => $machTig->id,
            'state'       => 'Breakdown',
            'reason'      => 'Hydraulic Line Leakage',
            'started_at'  => Carbon::now()->subHours(5),
            'ended_at'    => Carbon::now()->subHours(2),
            'changed_by'  => $userId,
        ]);
        ProductionMachineStateHistory::create([
            'tenant_id'   => $tenant->id,
            'machine_id'  => $machTig->id,
            'state'       => 'Idle',
            'reason'      => 'Resolved and Cleaned',
            'started_at'  => Carbon::now()->subHours(2),
            'ended_at'    => null,
            'changed_by'  => $userId,
        ]);

        ProductionMachineDowntime::create([
            'tenant_id'         => $tenant->id,
            'machine_id'        => $machTig->id,
            'work_center_id'    => $wcTig->id,
            'category'          => 'unplanned_maintenance',
            'reason'            => 'Hydraulic Line Leakage',
            'start_time'        => Carbon::now()->subHours(5),
            'end_time'          => Carbon::now()->subHours(2),
            'duration_minutes'  => 180.00,
            'remarks'           => 'Miller Welder hydraulic pressure loss required hose replacement.',
            'status'            => 'closed',
            'created_by'        => $userId,
        ]);

        // ==========================================
        // SCENARIO 3: QUALITY FAILURE + REWORK
        // ==========================================
        $ord3 = ProductionOrder::create([
            'tenant_id'          => $tenant->id,
            'order_number'       => 'ORD-2026-003',
            'product_id'         => $ebike->id,
            'bom_id'             => $bomEbike->id,
            'routing_id'         => $rtEbike->id,
            'quantity_ordered'   => 5.00,
            'quantity_produced'  => 5.00,
            'start_date'         => Carbon::now()->subDays(3),
            'end_date'           => Carbon::now()->subDay(),
            'status'             => 'completed',
            'created_by'         => $userId,
        ]);

        $batch3 = ProductionBatch::create([
            'tenant_id'           => $tenant->id,
            'batch_number'        => 'BAT-2026-REWORK',
            'production_order_id' => $ord3->id,
            'product_id'          => $ebike->id,
            'planned_quantity'    => 5.00,
            'actual_quantity'     => 5.00,
            'status'              => 'completed',
        ]);

        $insp3 = ProductionQualityInspection::create([
            'tenant_id'           => $tenant->id,
            'quality_plan_id'     => $qPlan->id,
            'stage'               => 'final',
            'status'              => 'approved',
            'result'              => 'failed',
            'production_order_id' => $ord3->id,
            'batch_id'            => $batch3->id,
            'audited_by'          => $userId,
            'audited_at'          => Carbon::now()->subDays(2),
        ]);

        ProductionQualityInspectionResult::create([
            'tenant_id'                 => $tenant->id,
            'quality_inspection_id'     => $insp3->id,
            'quality_plan_parameter_id' => $param1->id,
            'recorded_value_numeric'    => 4.80, // Fails
            'result'                    => 'failed',
        ]);

        $ncr3 = ProductionNcr::create([
            'tenant_id'             => $tenant->id,
            'ncr_number'            => 'NCR-2026-RWK',
            'category'              => 'process',
            'status'                => 'closed',
            'disposition_type'      => 'rework',
            'quality_inspection_id' => $insp3->id,
            'production_order_id'   => $ord3->id,
            'batch_id'              => $batch3->id,
            'description'           => 'Braking calibration distance failed standards during final audit.',
            'closed_at'             => Carbon::now()->subDay(),
            'closed_by'             => $userId,
        ]);

        $rework = ProductionReworkOrder::create([
            'tenant_id'                    => $tenant->id,
            'rework_number'                => 'RWK-2026-003',
            'ncr_id'                       => $ncr3->id,
            'original_production_order_id' => $ord3->id,
            'status'                       => 'completed',
            'cost_estimate'                => 200.00,
            'actual_cost'                  => 180.00,
        ]);

        ProductionReworkOperation::create([
            'tenant_id'              => $tenant->id,
            'rework_order_id'        => $rework->id,
            'sequence'               => 10,
            'name'                   => 'Calibrate Brake Tension',
            'work_center_id'         => $wcMainLine->id,
            'status'                 => 'completed',
            'processing_time_actual' => 90.00,
        ]);

        // CAPA integration
        $capa = ProductionCapa::create([
            'tenant_id'           => $tenant->id,
            'capa_number'         => 'CAPA-2026-003',
            'ncr_id'              => $ncr3->id,
            'status'              => 'closed',
            'root_cause_category' => 'process',
            'corrective_action'   => 'Tighten calipers using lock nuts to prevent slack slipping during test.',
            'preventive_action'   => 'Specify standard lock washers in process routing.',
            'action_owner_id'     => $userId,
            'rca_analysis_json'   => [
                'five_whys' => [
                    'Why did brakes fail? Slack in cable.',
                    'Why was there slack? Nut was not lock-tight.',
                    'Why? Standard lock washers not specified in routing.',
                ]
            ],
            'closed_by'         => $userId,
            'closed_at'         => now(),
        ]);

        // ==========================================
        // SCENARIO 4: QUALITY FAILURE + SCRAP
        // ==========================================
        $ord4 = ProductionOrder::create([
            'tenant_id'          => $tenant->id,
            'order_number'       => 'ORD-2026-004',
            'product_id'         => $ebike->id,
            'bom_id'             => $bomEbike->id,
            'routing_id'         => $rtEbike->id,
            'quantity_ordered'   => 5.00,
            'quantity_produced'  => 4.00,
            'start_date'         => Carbon::now()->subDays(2),
            'end_date'           => Carbon::now(),
            'status'             => 'completed',
            'created_by'         => $userId,
        ]);

        $batch4 = ProductionBatch::create([
            'tenant_id'           => $tenant->id,
            'batch_number'        => 'BAT-2026-SCRAP',
            'production_order_id' => $ord4->id,
            'product_id'          => $ebike->id,
            'planned_quantity'    => 5.00,
            'actual_quantity'     => 4.00,
            'status'              => 'completed',
        ]);

        $insp4 = ProductionQualityInspection::create([
            'tenant_id'           => $tenant->id,
            'quality_plan_id'     => $qPlan->id,
            'stage'               => 'final',
            'status'              => 'approved',
            'result'              => 'failed',
            'production_order_id' => $ord4->id,
            'batch_id'            => $batch4->id,
            'audited_by'          => $userId,
            'audited_at'          => Carbon::now()->subDay(),
        ]);

        $ncr4 = ProductionNcr::create([
            'tenant_id'             => $tenant->id,
            'ncr_number'            => 'NCR-2026-SCRAP',
            'category'              => 'process',
            'status'                => 'closed',
            'disposition_type'      => 'scrap',
            'quality_inspection_id' => $insp4->id,
            'production_order_id'   => $ord4->id,
            'batch_id'              => $batch4->id,
            'description'           => 'Severe structural crack discovered on E-Bike frame fork.',
            'closed_at'             => Carbon::now(),
            'closed_by'             => $userId,
        ]);

        ProductionScrapDisposal::create([
            'tenant_id'   => $tenant->id,
            'ncr_id'      => $ncr4->id,
            'category'    => 'semi_finished',
            'reason_code' => 'structural_defect',
            'quantity'    => 1.00,
            'cost'        => 350.00,
            'disposed_by' => $userId,
            'disposed_at' => Carbon::now(),
        ]);

        // ==========================================
        // SCENARIO 5: PRODUCTION DELAY (Capacity Overrun)
        // ==========================================
        $ord5 = ProductionOrder::create([
            'tenant_id'          => $tenant->id,
            'order_number'       => 'ORD-2026-DELAY',
            'product_id'         => $ebike->id,
            'bom_id'             => $bomEbike->id,
            'routing_id'         => $rtEbike->id,
            'quantity_ordered'   => 20.00,
            'quantity_produced'  => 5.00,
            'start_date'         => Carbon::now()->subDays(6),
            'end_date'           => Carbon::now()->addDays(5),
            'status'             => 'in_progress',
            'created_by'         => $userId,
        ]);

        ProductionOrderOperation::create([
            'tenant_id'               => $tenant->id,
            'production_order_id'     => $ord5->id,
            'sequence'                => 10,
            'operation_number'        => 'OP-010',
            'name'                    => 'Mechanical Chassis Assembly',
            'work_center_id'          => $wcMainLine->id,
            'status'                  => 'completed',
            'setup_time_planned'      => 10,
            'processing_time_planned' => 300,
            'total_time_planned'      => 310,
            'setup_time_actual'       => 45,
            'processing_time_actual'  => 520,
            'quantity_produced'       => 20,
        ]);

        ProductionOrderOperation::create([
            'tenant_id'               => $tenant->id,
            'production_order_id'     => $ord5->id,
            'sequence'                => 20,
            'operation_number'        => 'OP-020',
            'name'                    => 'Battery and Electronics Integration',
            'work_center_id'          => $wcMainLine->id,
            'status'                  => 'running',
            'setup_time_planned'      => 15,
            'processing_time_planned' => 600,
            'total_time_planned'      => 615,
            'setup_time_actual'       => 20,
            'processing_time_actual'  => 100,
            'quantity_produced'       => 5,
        ]);

        // ==========================================
        // SCENARIO 6: MULTIPLE RUNNING ORDERS
        // ==========================================
        $activeOrders = [
            ['num' => 'ORD-RUN-01', 'qty' => 8, 'prod' => $frameAssy, 'bom' => 'BOM-FRAME-001', 'rt' => 'RT-FRAME-001', 'wc' => $wcTig, 'mac' => $machTig],
            ['num' => 'ORD-RUN-02', 'qty' => 15, 'prod' => $battPack, 'bom' => 'BOM-BATT-001', 'rt' => 'RT-BATT-001', 'wc' => $wcCellWeld, 'mac' => $machSpot],
        ];

        foreach ($activeOrders as $idx => $ao) {
            $bom = ProductionBom::where('tenant_id', $tenant->id)->where('bom_number', $ao['bom'])->first();
            $rt = Routing::where('tenant_id', $tenant->id)->where('routing_number', $ao['rt'])->first();

            $pOrd = ProductionOrder::create([
                'tenant_id'          => $tenant->id,
                'order_number'       => $ao['num'],
                'product_id'         => $ao['prod']->id,
                'bom_id'             => $bom->id,
                'routing_id'         => $rt->id,
                'quantity_ordered'   => $ao['qty'],
                'quantity_produced'  => 0.00,
                'start_date'         => Carbon::now(),
                'end_date'           => Carbon::now()->addDays(3),
                'status'             => 'in_progress',
                'created_by'         => $userId,
            ]);

            $pOp = ProductionOrderOperation::create([
                'tenant_id'               => $tenant->id,
                'production_order_id'     => $pOrd->id,
                'sequence'                => 10,
                'operation_number'        => 'OP-010',
                'name'                    => 'Primary Step ' . $ao['num'],
                'work_center_id'          => $ao['wc']->id,
                'machine_id'              => $ao['mac']->id,
                'status'                  => 'running',
                'setup_time_planned'      => 10,
                'processing_time_planned' => 100,
                'total_time_planned'      => 110,
            ]);

            ProductionOperatorAssignment::create([
                'tenant_id'                     => $tenant->id,
                'production_order_operation_id' => $pOp->id,
                'user_id'                       => $userId,
                'assigned_by'                   => $userId,
                'assigned_at'                   => now(),
                'status'                        => 'accepted',
            ]);
        }

        // Write dashboard preferences
        \App\Domains\Production\Models\ProductionDashboardPreference::create([
            'tenant_id'       => $tenant->id,
            'user_id'         => $userId,
            'dashboard_type'  => 'oee',
            'widgets'         => ['oee_widget', 'yield_widget', 'downtime_widget'],
            'default_filters' => ['period' => 'today'],
            'layout'          => 'grid',
        ]);
    }
}
