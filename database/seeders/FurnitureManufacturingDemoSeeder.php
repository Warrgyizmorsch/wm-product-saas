<?php

namespace Database\Seeders;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionAlertConfiguration;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionDashboardPreference;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\ProductionKpiTarget;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Models\ProductionPlanRequirement;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityInspectionResult;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\ProductionReworkOperation;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionScrapDisposal;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FurnitureManufacturingDemoSeeder
 *
 * Comprehensive chair & table manufacturing scenario covering every production module stage:
 *
 *  MASTER DATA
 *  1.  Work Center Hierarchy  – 4 Depts → Sections → 6 leaf work centers
 *  2.  Machines               – CNC Router, Belt Sander, Spray Booth, Assembly Press
 *  3.  Shifts & Calendar      – Day / Evening shifts linked to all work centers
 *  4.  Products               – 11 raw materials, 5 semi-finished, 2 finished goods
 *  5.  BOMs                   – Chair leg sub-BOM + Dining Chair + Dining Table (multi-level)
 *  6.  Routings               – 5-step routings for Chair Legs, Chair, and Table
 *  7.  Quality Plans          – Chair inspection (4 params), Table inspection (3 params)
 *  8.  Operator Skills        – CNC, Sanding, Finishing, Assembly, QC per operator
 *  9.  Production Plan        – Q3 2026 plan with requirements and planned operations
 *
 *  SCENARIO DATA (5 Orders)
 *  A.  Perfect run            – 10x Dining Chair, all 5 ops completed, QC passed, closed
 *  B.  Machine Breakdown      – CNC Router thermal shutdown (downtime + machine state history)
 *  C.  Quality Fail → Rework  – Dining Table leg joint wobble → NCR → Rework → CAPA
 *  D.  Quality Fail → Scrap   – Dining Chair grain crack → NCR → Scrap disposal
 *  E.  Active In-Progress     – 10x Dining Table, OP-030 currently running in MES
 */
class FurnitureManufacturingDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Step 0: Clean tables ───────────────────────────────────────────
        Schema::disableForeignKeyConstraints();

        // Scenario-level tables
        DB::table('production_event_timelines')->truncate();
        DB::table('production_scrap_disposals')->truncate();
        DB::table('production_rework_operations')->truncate();
        DB::table('production_rework_orders')->truncate();
        DB::table('production_capas')->truncate();
        DB::table('production_ncrs')->truncate();
        DB::table('production_quality_inspection_results')->truncate();
        DB::table('production_quality_inspections')->truncate();
        DB::table('production_quality_plan_parameters')->truncate();
        DB::table('production_quality_plans')->truncate();
        DB::table('production_scan_logs')->truncate();
        DB::table('production_serial_numbers')->truncate();
        DB::table('production_batch_genealogies')->truncate();
        DB::table('production_batches')->truncate();
        DB::table('production_operator_assignment_logs')->truncate();
        DB::table('production_operator_assignments')->truncate();
        DB::table('production_operator_skills')->truncate();
        DB::table('production_schedule_operations')->truncate();
        DB::table('production_schedules')->truncate();
        DB::table('production_order_issues')->truncate();
        DB::table('production_order_receipts')->truncate();
        DB::table('production_order_progress_logs')->truncate();
        DB::table('production_order_reservations')->truncate();
        DB::table('production_order_operations')->truncate();
        DB::table('production_orders')->truncate();
        DB::table('production_plan_operations')->truncate();
        DB::table('production_plan_requirements')->truncate();
        DB::table('production_plans')->truncate();
        DB::table('production_machine_downtimes')->truncate();
        DB::table('production_machine_state_histories')->truncate();
        DB::table('production_alert_configurations')->truncate();
        DB::table('production_kpi_targets')->truncate();
        DB::table('production_shifts')->truncate();
        DB::table('production_calendars')->truncate();
        DB::table('production_dashboard_preferences')->truncate();
        DB::table('production_event_timelines')->truncate();
        DB::table('production_capas')->truncate();
        DB::table('production_scrap_disposals')->truncate();
        DB::table('production_rework_operations')->truncate();
        DB::table('production_rework_orders')->truncate();
        DB::table('production_ncrs')->truncate();
        DB::table('production_quality_inspection_results')->truncate();
        DB::table('production_quality_inspections')->truncate();
        DB::table('production_batches')->truncate();
        DB::table('production_serial_numbers')->truncate();
        DB::table('production_scan_logs')->truncate();
        DB::table('production_calendar_holidays')->truncate();

        // Master production tables
        DB::table('production_routing_operation_materials')->truncate();
        DB::table('production_routing_approvals')->truncate();
        DB::table('production_routing_operations')->truncate();
        DB::table('routings')->truncate();
        DB::table('production_bom_approvals')->truncate();
        DB::table('production_bom_items')->truncate();
        DB::table('production_boms')->truncate();
        DB::table('production_machines')->truncate();
        DB::table('production_work_centers')->truncate();
        DB::table('production_work_center_shifts')->truncate();

        // Inventory tables (no orphan FKs)
        DB::table('product_warehouse_stocks')->truncate();
        DB::table('stock_transactions')->truncate();
        DB::table('serial_numbers')->truncate();
        DB::table('batches')->truncate();
        DB::table('stock_reservations')->truncate();
        DB::table('products')->truncate();

        Schema::enableForeignKeyConstraints();

        // ─── Step 1: Resolve Tenant & Users ─────────────────────────────────
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();
        if (! $tenant) {
            $this->command->warn('No tenant found. Run DatabaseSeeder first.');

            return;
        }

        $adminUser = User::where('tenant_id', $tenant->id)->first();
        if (! $adminUser) {
            $this->command->warn('No users found for the demo tenant.');

            return;
        }
        $userId = $adminUser->id;

        // ─── Step 2: UOMs ────────────────────────────────────────────────────
        $pcs = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'PCS'], ['name' => 'Pieces']);
        $m = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'M'], ['name' => 'Meters']);
        $kg = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'KG'], ['name' => 'Kilograms']);
        $ltr = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'LTR'], ['name' => 'Liters']);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 1 — PRODUCTS
        // ═══════════════════════════════════════════════════════════════════════

        // Raw Materials
        $teakLumber = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-TEAK-LOG',   'name' => 'Teak Lumber (Grade A, 3" x 3")',         'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 8.50]);
        $pineLumber = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-PINE-BRD',   'name' => 'Pine Board (1" x 6" x 8ft)',              'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 3.20]);
        $plySheet = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-PLY-18MM',   'name' => 'Plywood Sheet (18mm, 4x8ft)',             'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 22.00]);
        $woodStain = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-STAIN-WAL',  'name' => 'Wood Stain – Walnut (1L)',                'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 12.00]);
        $lacquer = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-LACQ-CLEAR', 'name' => 'Clear Lacquer Coat (1L)',                 'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 9.00]);
        $dowels = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-DOWEL-10',   'name' => 'Wooden Dowels 10mm x 35mm (Pack 100)',    'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 4.50]);
        $screws = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-SCREW-M6',   'name' => 'Wood Screws M6 x 50mm (Box 200)',         'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 3.00]);
        $sandpaper = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-SAND-120',   'name' => 'Sandpaper 120-Grit (Pack 50)',            'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 6.50]);
        $glue = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-GLUE-PVA',   'name' => 'PVA Wood Glue (500ml)',                   'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 5.00]);
        $foam = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-FOAM-5CM',   'name' => 'Seat Foam Cushion 5cm (per sheet)',       'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 7.50]);
        $fabric = Product::create(['tenant_id' => $tenant->id, 'sku' => 'RM-FABR-GRY',   'name' => 'Upholstery Fabric Grey (per meter)',      'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 8.00]);

        // Semi-Finished Goods
        $chairLegs = Product::create(['tenant_id' => $tenant->id, 'sku' => 'SF-CHAIR-LEGS',  'name' => 'Turned Chair Leg Set (4 pcs)',             'type' => 'semi_finished', 'status' => 'active', 'unit_cost' => 0.00]);
        $tableTop = Product::create(['tenant_id' => $tenant->id, 'sku' => 'SF-TABLE-TOP',   'name' => 'Dining Table Top (teak, 1200x700mm)',      'type' => 'semi_finished', 'status' => 'active', 'unit_cost' => 0.00]);
        $tableFrame = Product::create(['tenant_id' => $tenant->id, 'sku' => 'SF-TABLE-FRAME', 'name' => 'Table Base Frame (teak apron + legs)',     'type' => 'semi_finished', 'status' => 'active', 'unit_cost' => 0.00]);

        // Finished Goods
        $diningChair = Product::create(['tenant_id' => $tenant->id, 'sku' => 'FG-CHAIR-DC1', 'name' => 'Dining Chair Teak DC-1',             'type' => 'finished_good', 'status' => 'active', 'unit_cost' => 0.00]);
        $diningTable = Product::create(['tenant_id' => $tenant->id, 'sku' => 'FG-TABLE-DT1', 'name' => 'Dining Table Teak 4-Seater DT-1',   'type' => 'finished_good', 'status' => 'active', 'unit_cost' => 0.00]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 2 — WORK CENTER HIERARCHY
        // ═══════════════════════════════════════════════════════════════════════

        // Dept 1: Woodworking Fabrication
        $deptWood = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Woodworking Fabrication Dept', 'code' => 'DEPT-WOOD',       'type' => 'department',  'work_center_type' => 'machining', 'department_name' => 'Woodworking', 'location' => 'Factory A – North',    'capacity_per_hour' => 12, 'efficiency_percentage' => 90, 'cost_per_hour' => 35, 'overhead_rate' => 15, 'status' => 'active', 'parent_id' => null]);
        $secCnc = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'CNC Machining Section',        'code' => 'SEC-CNC',         'type' => 'section',     'work_center_type' => 'machining', 'department_name' => 'Woodworking', 'location' => 'Factory A – Bay 1',   'capacity_per_hour' => 8,  'efficiency_percentage' => 92, 'cost_per_hour' => 45, 'overhead_rate' => 20, 'status' => 'active', 'parent_id' => $deptWood->id]);
        $wcCnc = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'CNC Router Work Center',       'code' => 'WC-CNC-RTR',      'type' => 'work_center', 'work_center_type' => 'machining', 'department_name' => 'Woodworking', 'location' => 'Factory A – Stn 1',   'capacity_per_hour' => 4,  'efficiency_percentage' => 88, 'cost_per_hour' => 55, 'overhead_rate' => 25, 'status' => 'active', 'parent_id' => $secCnc->id]);
        $secSanding = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Sanding & Smoothing Section',  'code' => 'SEC-SAND',        'type' => 'section',     'work_center_type' => 'machining', 'department_name' => 'Woodworking', 'location' => 'Factory A – Bay 2',   'capacity_per_hour' => 10, 'efficiency_percentage' => 94, 'cost_per_hour' => 30, 'overhead_rate' => 10, 'status' => 'active', 'parent_id' => $deptWood->id]);
        $wcSanding = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Belt Sanding Work Center',     'code' => 'WC-BELT-SAND',    'type' => 'work_center', 'work_center_type' => 'machining', 'department_name' => 'Woodworking', 'location' => 'Factory A – Stn 3',   'capacity_per_hour' => 6,  'efficiency_percentage' => 95, 'cost_per_hour' => 32, 'overhead_rate' => 12, 'status' => 'active', 'parent_id' => $secSanding->id]);

        // Dept 2: Finishing & Coating
        $deptFinish = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Finishing & Coating Dept',   'code' => 'DEPT-FINISH',     'type' => 'department',  'work_center_type' => 'assembly',  'department_name' => 'Finishing',   'location' => 'Factory B – South',   'capacity_per_hour' => 14, 'efficiency_percentage' => 96, 'cost_per_hour' => 28, 'overhead_rate' => 8,  'status' => 'active', 'parent_id' => null]);
        $secSpray = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Spray Booth Section',         'code' => 'SEC-SPRAY',       'type' => 'section',     'work_center_type' => 'assembly',  'department_name' => 'Finishing',   'location' => 'Factory B – Bay 1',   'capacity_per_hour' => 6,  'efficiency_percentage' => 94, 'cost_per_hour' => 35, 'overhead_rate' => 15, 'status' => 'active', 'parent_id' => $deptFinish->id]);
        $wcSprayBooth = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Spray Booth Work Center',    'code' => 'WC-SPRAY-BTH',    'type' => 'work_center', 'work_center_type' => 'assembly',  'department_name' => 'Finishing',   'location' => 'Factory B – Stn 1',   'capacity_per_hour' => 5,  'efficiency_percentage' => 92, 'cost_per_hour' => 38, 'overhead_rate' => 18, 'status' => 'active', 'parent_id' => $secSpray->id]);

        // Dept 3: Assembly
        $deptAssy = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Furniture Assembly Dept',    'code' => 'DEPT-FURN-ASSY',  'type' => 'department',  'work_center_type' => 'assembly',  'department_name' => 'Assembly',    'location' => 'Factory C – East',    'capacity_per_hour' => 18, 'efficiency_percentage' => 97, 'cost_per_hour' => 25, 'overhead_rate' => 8,  'status' => 'active', 'parent_id' => null]);
        $secJoinery = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Joinery & Assembly Section', 'code' => 'SEC-JOIN',        'type' => 'section',     'work_center_type' => 'assembly',  'department_name' => 'Assembly',    'location' => 'Factory C – Bay 1',   'capacity_per_hour' => 10, 'efficiency_percentage' => 96, 'cost_per_hour' => 28, 'overhead_rate' => 10, 'status' => 'active', 'parent_id' => $deptAssy->id]);
        $wcAssembly = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Furniture Assembly Line 1',  'code' => 'WC-FURN-LINE1',   'type' => 'work_center', 'work_center_type' => 'assembly',  'department_name' => 'Assembly',    'location' => 'Factory C – Line 1',  'capacity_per_hour' => 6,  'efficiency_percentage' => 95, 'cost_per_hour' => 30, 'overhead_rate' => 12, 'status' => 'active', 'parent_id' => $secJoinery->id]);

        // Dept 4: Quality Control
        $deptQc = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Quality Control Dept',       'code' => 'DEPT-QC',         'type' => 'department',  'work_center_type' => 'inspection', 'department_name' => 'Quality',     'location' => 'Factory C – QC Bay',  'capacity_per_hour' => 20, 'efficiency_percentage' => 99, 'cost_per_hour' => 20, 'overhead_rate' => 5,  'status' => 'active', 'parent_id' => null]);
        $wcQcFinal = WorkCenter::create(['tenant_id' => $tenant->id, 'name' => 'Final QC Inspection Station', 'code' => 'WC-QC-FINAL',     'type' => 'work_center', 'work_center_type' => 'inspection', 'department_name' => 'Quality',     'location' => 'Factory C – QC Desk', 'capacity_per_hour' => 15, 'efficiency_percentage' => 99, 'cost_per_hour' => 22, 'overhead_rate' => 6,  'status' => 'active', 'parent_id' => $deptQc->id]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 3 — MACHINES
        // ═══════════════════════════════════════════════════════════════════════

        $machCnc = Machine::create(['tenant_id' => $tenant->id, 'work_center_id' => $wcCnc->id,      'name' => 'Biesse Rover CNC Router C600',     'code' => 'MCH-CNC-C600',   'machine_type' => 'CNC Router',       'manufacturer' => 'Biesse',       'model_number' => 'Rover C 6.40',  'capacity' => 4.0, 'status' => 'active', 'current_state' => 'Idle']);
        $machSander = Machine::create(['tenant_id' => $tenant->id, 'work_center_id' => $wcSanding->id,  'name' => 'Holytek Belt Sander BS-1300',      'code' => 'MCH-SAND-BS13',  'machine_type' => 'Belt Sander',      'manufacturer' => 'Holytek',      'model_number' => 'BS-1300',       'capacity' => 6.0, 'status' => 'active', 'current_state' => 'Idle']);
        $machSpray = Machine::create(['tenant_id' => $tenant->id, 'work_center_id' => $wcSprayBooth->id, 'name' => 'Anest Iwata Spray System W-400',  'code' => 'MCH-SPRAY-W400', 'machine_type' => 'Spray Gun System', 'manufacturer' => 'Anest Iwata',  'model_number' => 'W-400-134G',   'capacity' => 5.0, 'status' => 'active', 'current_state' => 'Idle']);
        $machPress = Machine::create(['tenant_id' => $tenant->id, 'work_center_id' => $wcAssembly->id, 'name' => 'Pneumatic Assembly Clamp Press',   'code' => 'MCH-PRESS-AC1',  'machine_type' => 'Assembly Press',   'manufacturer' => 'Bessey',       'model_number' => 'STC-AP50',     'capacity' => 6.0, 'status' => 'active', 'current_state' => 'Idle']);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 4 — SHIFTS & CALENDAR
        // ═══════════════════════════════════════════════════════════════════════

        $shiftDay = ProductionShift::create(['tenant_id' => $tenant->id, 'name' => 'Day Shift',     'code' => 'SHIFT-DAY', 'start_time' => '07:00:00', 'end_time' => '15:30:00', 'break_minutes' => 30, 'overtime_allowed' => false, 'active' => true]);
        $shiftEve = ProductionShift::create(['tenant_id' => $tenant->id, 'name' => 'Evening Shift', 'code' => 'SHIFT-EVE', 'start_time' => '15:30:00', 'end_time' => '23:00:00', 'break_minutes' => 30, 'overtime_allowed' => true,  'active' => true]);

        foreach ([$wcCnc, $wcSanding, $wcSprayBooth, $wcAssembly, $wcQcFinal] as $wc) {
            foreach ([$shiftDay->id, $shiftEve->id] as $shiftId) {
                DB::table('production_work_center_shifts')->insertOrIgnore([
                    'tenant_id' => $tenant->id,
                    'work_center_id' => $wc->id,
                    'shift_id' => $shiftId,
                ]);
            }
        }

        $calendar = ProductionCalendar::create(['tenant_id' => $tenant->id, 'name' => 'Furniture Plant 2026 Calendar', 'working_days' => [1, 2, 3, 4, 5, 6], 'is_default' => true]);
        ProductionCalendarHoliday::create(['tenant_id' => $tenant->id, 'production_calendar_id' => $calendar->id, 'holiday_date' => Carbon::parse('2026-08-15'), 'name' => 'Independence Day', 'holiday_type' => 'public']);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 5 — KPI TARGETS & ALERT CONFIGURATIONS
        // ═══════════════════════════════════════════════════════════════════════

        foreach (['oee' => 80, 'availability' => 88, 'performance' => 90, 'quality' => 98, 'throughput' => 60, 'utilization' => 78, 'scrap_rate' => 3, 'downtime' => 15] as $name => $val) {
            ProductionKpiTarget::create(['tenant_id' => $tenant->id, 'kpi_name' => $name, 'target_value' => $val]);
        }
        ProductionAlertConfiguration::create(['tenant_id' => $tenant->id, 'alert_type' => 'downtime_duration', 'threshold' => 45.00, 'severity' => 'critical', 'active' => true]);
        ProductionAlertConfiguration::create(['tenant_id' => $tenant->id, 'alert_type' => 'scrap_rate',        'threshold' => 5.00,  'severity' => 'warning',  'active' => true]);
        ProductionAlertConfiguration::create(['tenant_id' => $tenant->id, 'alert_type' => 'schedule_delay',   'threshold' => 60.00, 'severity' => 'warning',  'active' => true]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 6 — OPERATOR SKILLS
        // ═══════════════════════════════════════════════════════════════════════

        ProductionOperatorSkill::create(['tenant_id' => $tenant->id, 'user_id' => $userId, 'skill_code' => 'SKILL-CNC',      'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id, 'active' => true]);
        ProductionOperatorSkill::create(['tenant_id' => $tenant->id, 'user_id' => $userId, 'skill_code' => 'SKILL-SANDING',  'work_center_id' => $wcSanding->id,   'active' => true]);
        ProductionOperatorSkill::create(['tenant_id' => $tenant->id, 'user_id' => $userId, 'skill_code' => 'SKILL-FINISHING', 'work_center_id' => $wcSprayBooth->id, 'active' => true]);
        ProductionOperatorSkill::create(['tenant_id' => $tenant->id, 'user_id' => $userId, 'skill_code' => 'SKILL-ASSEMBLY', 'work_center_id' => $wcAssembly->id,  'active' => true]);
        ProductionOperatorSkill::create(['tenant_id' => $tenant->id, 'user_id' => $userId, 'skill_code' => 'SKILL-QC',       'work_center_id' => $wcQcFinal->id,   'active' => true]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 7 — QUALITY PLANS
        // ═══════════════════════════════════════════════════════════════════════

        $qPlanChair = ProductionQualityPlan::create(['tenant_id' => $tenant->id, 'name' => 'Dining Chair Final Inspection Plan', 'version' => '1.0.0', 'status' => 'approved', 'type' => 'final', 'product_id' => $diningChair->id, 'work_center_id' => $wcQcFinal->id, 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subDays(30)]);
        $cP1 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanChair->id, 'name' => 'Seat Height Measurement',       'type' => 'numeric',   'min_value' => 430, 'max_value' => 460, 'unit_of_measure' => 'mm', 'is_mandatory' => true]);
        $cP2 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanChair->id, 'name' => 'Structural Wobble Test',        'type' => 'pass_fail', 'is_mandatory' => true]);
        $cP3 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanChair->id, 'name' => 'Finish Surface Quality (Visual)', 'type' => 'pass_fail', 'is_mandatory' => true]);
        $cP4 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanChair->id, 'name' => 'Static Load Test 120kg',         'type' => 'pass_fail', 'is_mandatory' => true]);

        $qPlanTable = ProductionQualityPlan::create(['tenant_id' => $tenant->id, 'name' => 'Dining Table Final Inspection Plan', 'version' => '1.0.0', 'status' => 'approved', 'type' => 'final', 'product_id' => $diningTable->id, 'work_center_id' => $wcQcFinal->id, 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subDays(30)]);
        $tP1 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanTable->id, 'name' => 'Table Top Flatness (Max Bow)',      'type' => 'numeric',   'min_value' => 0, 'max_value' => 2, 'unit_of_measure' => 'mm', 'is_mandatory' => true]);
        $tP2 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanTable->id, 'name' => 'Leg Joint Structural Integrity',    'type' => 'pass_fail', 'is_mandatory' => true]);
        $tP3 = ProductionQualityPlanParameter::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanTable->id, 'name' => 'Surface Finish Stain Coverage',     'type' => 'pass_fail', 'is_mandatory' => true]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 8 — ROUTINGS
        // ═══════════════════════════════════════════════════════════════════════

        // ── Routing: Chair Leg Sub-Assembly ──────────────────────────────────
        $rtChairLegs = Routing::create(['tenant_id' => $tenant->id, 'routing_number' => 'RT-CHAIR-LEGS', 'name' => 'Chair Leg Turning & Shaping',          'product_id' => $chairLegs->id,  'version' => '1.0.0', 'revision' => 0, 'is_default' => true, 'effective_from' => now()->subMonths(3), 'effective_to' => now()->addYears(2), 'status' => 'active', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subMonths(2)]);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChairLegs->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Profile Cutting – Legs',   'operation_type' => 'manufacturing', 'work_center_id' => $wcCnc->id,    'machine_id' => $machCnc->id,    'setup_time_minutes' => 20, 'processing_time_minutes' => 45, 'expected_yield_percentage' => 97, 'instructions' => 'Load 4 teak blanks (80x80x460mm). Run G-code CHAIR-LEG-DC1 at 8000mm/min feed. Chamfer all edges 0.5mm.']);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChairLegs->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Belt Sanding – Legs',           'operation_type' => 'manufacturing', 'work_center_id' => $wcSanding->id, 'machine_id' => $machSander->id, 'setup_time_minutes' => 10, 'processing_time_minutes' => 30, 'expected_yield_percentage' => 99, 'instructions' => 'Sand all leg profiles 120->180->240 grit. Chamfer feet 1mm.']);
        RoutingApproval::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChairLegs->id, 'user_id' => $userId, 'action' => 'Approved', 'comments' => 'Approved for production.']);

        // ── Routing: Dining Chair (5 steps) ──────────────────────────────────
        $rtChair = Routing::create(['tenant_id' => $tenant->id, 'routing_number' => 'RT-CHAIR-DC1', 'name' => 'Dining Chair DC-1 Full Routing',           'product_id' => $diningChair->id, 'version' => '1.0.0', 'revision' => 0, 'is_default' => true, 'effective_from' => now()->subMonths(3), 'effective_to' => now()->addYears(2), 'status' => 'active', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subMonths(2)]);
        $rtopC1 = RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChair->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Cutting & Shaping',         'operation_type' => 'manufacturing', 'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id,    'setup_time_minutes' => 25, 'processing_time_minutes' => 60, 'expected_yield_percentage' => 97, 'labor_cost_rate' => 18, 'machine_cost_rate' => 55, 'instructions' => 'Cut seat panel, back slats, apron rails. G-code CHAIR-DC1-V1.']);
        $rtopC2 = RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChair->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Sanding & Surface Preparation',  'operation_type' => 'manufacturing', 'work_center_id' => $wcSanding->id,   'machine_id' => $machSander->id, 'setup_time_minutes' => 10, 'processing_time_minutes' => 40, 'expected_yield_percentage' => 99, 'labor_cost_rate' => 12, 'machine_cost_rate' => 32, 'instructions' => 'Sand all parts 120->180->240 grit. Remove all CNC tool marks.']);
        $rtopC3 = RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChair->id, 'sequence' => 30, 'operation_number' => 'OP-030', 'name' => 'Staining & Lacquer Coating',     'operation_type' => 'manufacturing', 'work_center_id' => $wcSprayBooth->id, 'machine_id' => $machSpray->id,  'setup_time_minutes' => 15, 'processing_time_minutes' => 50, 'expected_yield_percentage' => 98, 'labor_cost_rate' => 14, 'machine_cost_rate' => 38, 'instructions' => '2 coats walnut stain (30min flash). 2 coats clear lacquer (15min between coats).']);
        $rtopC4 = RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChair->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Structural Assembly & Joinery',  'operation_type' => 'manufacturing', 'work_center_id' => $wcAssembly->id,  'machine_id' => $machPress->id,  'setup_time_minutes' => 20, 'processing_time_minutes' => 55, 'expected_yield_percentage' => 99, 'labor_cost_rate' => 16, 'machine_cost_rate' => 30, 'instructions' => 'Dry-fit joints. Apply PVA glue to mortise-tenon joints. Clamp at 300 PSI. 60min cure. Attach seat pad.']);
        $rtopC5 = RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChair->id, 'sequence' => 50, 'operation_number' => 'OP-050', 'name' => 'Final QC Inspection',             'operation_type' => 'inspection',    'work_center_id' => $wcQcFinal->id,   'machine_id' => null,            'setup_time_minutes' => 5,  'processing_time_minutes' => 20, 'expected_yield_percentage' => 100, 'quality_required' => true, 'instructions' => 'Measure seat height, wobble test, inspect finish, 120kg static load test.']);
        RoutingApproval::create(['tenant_id' => $tenant->id, 'routing_id' => $rtChair->id, 'user_id' => $userId, 'action' => 'Approved', 'comments' => 'Chair DC-1 routing approved for full-scale production.']);

        // ── Routing: Dining Table (5 steps) ──────────────────────────────────
        $rtTable = Routing::create(['tenant_id' => $tenant->id, 'routing_number' => 'RT-TABLE-DT1', 'name' => 'Dining Table DT-1 Full Routing',           'product_id' => $diningTable->id, 'version' => '1.0.0', 'revision' => 0, 'is_default' => true, 'effective_from' => now()->subMonths(3), 'effective_to' => now()->addYears(2), 'status' => 'active', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subMonths(2)]);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtTable->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Table Top Cutting & Profiling', 'operation_type' => 'manufacturing', 'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id,    'setup_time_minutes' => 30, 'processing_time_minutes' => 90, 'expected_yield_percentage' => 96, 'labor_cost_rate' => 18, 'machine_cost_rate' => 55, 'instructions' => 'Cut 1200x700mm top. Edge profile. G-code TABLE-DT1-TOP. Verify dimensions.']);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtTable->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Surface Sanding – Top & Legs',     'operation_type' => 'manufacturing', 'work_center_id' => $wcSanding->id,   'machine_id' => $machSander->id, 'setup_time_minutes' => 15, 'processing_time_minutes' => 60, 'expected_yield_percentage' => 99, 'labor_cost_rate' => 12, 'machine_cost_rate' => 32, 'instructions' => 'Sand top 120->180->240 grit. Hand sand legs 240 grit.']);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtTable->id, 'sequence' => 30, 'operation_number' => 'OP-030', 'name' => 'Staining & Lacquer Coating',       'operation_type' => 'manufacturing', 'work_center_id' => $wcSprayBooth->id, 'machine_id' => $machSpray->id,  'setup_time_minutes' => 20, 'processing_time_minutes' => 70, 'expected_yield_percentage' => 97, 'labor_cost_rate' => 14, 'machine_cost_rate' => 38, 'instructions' => '2 coats walnut stain (45min dry). 3 coats clear lacquer (20min cure between coats).']);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtTable->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Table Base & Leg Assembly',        'operation_type' => 'manufacturing', 'work_center_id' => $wcAssembly->id,  'machine_id' => $machPress->id,  'setup_time_minutes' => 25, 'processing_time_minutes' => 80, 'expected_yield_percentage' => 98, 'labor_cost_rate' => 16, 'machine_cost_rate' => 30, 'instructions' => 'Attach apron rails via pocket screws. Fit and bolt legs. Check diagonal for square.']);
        RoutingOperation::create(['tenant_id' => $tenant->id, 'routing_id' => $rtTable->id, 'sequence' => 50, 'operation_number' => 'OP-050', 'name' => 'Final QC Inspection',              'operation_type' => 'inspection',    'work_center_id' => $wcQcFinal->id,   'machine_id' => null,            'setup_time_minutes' => 5,  'processing_time_minutes' => 25, 'expected_yield_percentage' => 100, 'quality_required' => true, 'instructions' => 'Check flatness (<=2mm bow), leg joint play, stain coverage.']);
        RoutingApproval::create(['tenant_id' => $tenant->id, 'routing_id' => $rtTable->id, 'user_id' => $userId, 'action' => 'Approved', 'comments' => 'Table DT-1 routing approved.']);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 9 — BOMS (Bills of Materials)
        // ═══════════════════════════════════════════════════════════════════════

        // BOM: Chair Leg Set (Sub-Assembly)
        $bomChairLegs = ProductionBom::create(['tenant_id' => $tenant->id, 'bom_number' => 'BOM-CHAIR-LEGS', 'bom_name' => 'Chair Leg Set BOM', 'bom_type' => 'manufacturing', 'usage_context' => 'manufacturing', 'product_id' => $chairLegs->id, 'base_quantity' => 1.0, 'base_uom_id' => $pcs->id, 'version' => '1.0.0', 'revision' => 0, 'routing_id' => $rtChairLegs->id, 'effective_date' => now()->subMonths(3), 'status' => 'approved', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subMonths(2)]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChairLegs->id, 'material_id' => $teakLumber->id, 'quantity' => 4.0, 'uom_id' => $pcs->id, 'sequence' => 10, 'material_scrap_percentage' => 5.0, 'notes' => '4 teak blanks per leg set']);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChairLegs->id, 'material_id' => $sandpaper->id, 'quantity' => 0.5, 'uom_id' => $pcs->id, 'sequence' => 20]);
        ProductionBomApproval::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChairLegs->id, 'user_id' => $userId, 'action' => 'Approved', 'comments' => 'Approved for production.']);

        // BOM: Dining Chair (Full – includes sub-BOM reference for legs)
        $bomChair = ProductionBom::create(['tenant_id' => $tenant->id, 'bom_number' => 'BOM-CHAIR-DC1', 'bom_name' => 'Dining Chair DC-1 BOM', 'bom_type' => 'manufacturing', 'usage_context' => 'manufacturing', 'product_id' => $diningChair->id, 'base_quantity' => 1.0, 'base_uom_id' => $pcs->id, 'version' => '1.0.0', 'revision' => 0, 'routing_id' => $rtChair->id, 'effective_date' => now()->subMonths(3), 'status' => 'approved', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subMonths(2)]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $chairLegs->id,  'child_bom_id' => $bomChairLegs->id, 'quantity' => 1.0,  'uom_id' => $pcs->id, 'sequence' => 10, 'notes' => 'Chair leg set sub-assembly']);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $pineLumber->id, 'quantity' => 2.0,  'uom_id' => $pcs->id, 'sequence' => 20, 'notes' => 'Pine seat rails and back slats']);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $plySheet->id,   'quantity' => 0.25, 'uom_id' => $pcs->id, 'sequence' => 30, 'notes' => 'Quarter plywood sheet for seat panel']);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $woodStain->id,  'quantity' => 0.25, 'uom_id' => $ltr->id, 'sequence' => 40]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $lacquer->id,    'quantity' => 0.30, 'uom_id' => $ltr->id, 'sequence' => 50]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $glue->id,       'quantity' => 0.1,  'uom_id' => $ltr->id, 'sequence' => 60]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $dowels->id,     'quantity' => 0.1,  'uom_id' => $pcs->id, 'sequence' => 70]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $foam->id,       'quantity' => 1.0,  'uom_id' => $pcs->id, 'sequence' => 80]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'material_id' => $fabric->id,     'quantity' => 0.5,  'uom_id' => $m->id,   'sequence' => 90]);
        ProductionBomApproval::create(['tenant_id' => $tenant->id, 'bom_id' => $bomChair->id, 'user_id' => $userId, 'action' => 'Approved', 'comments' => 'Approved for production.']);

        // BOM: Dining Table
        $bomTable = ProductionBom::create(['tenant_id' => $tenant->id, 'bom_number' => 'BOM-TABLE-DT1', 'bom_name' => 'Dining Table DT-1 BOM', 'bom_type' => 'manufacturing', 'usage_context' => 'manufacturing', 'product_id' => $diningTable->id, 'base_quantity' => 1.0, 'base_uom_id' => $pcs->id, 'version' => '1.0.0', 'revision' => 0, 'routing_id' => $rtTable->id, 'effective_date' => now()->subMonths(3), 'status' => 'approved', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subMonths(2)]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $teakLumber->id, 'quantity' => 10.0, 'uom_id' => $pcs->id, 'sequence' => 10, 'material_scrap_percentage' => 8.0, 'notes' => 'Teak for top boards and legs']);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $plySheet->id,   'quantity' => 0.5,  'uom_id' => $pcs->id, 'sequence' => 20]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $woodStain->id,  'quantity' => 0.6,  'uom_id' => $ltr->id, 'sequence' => 30]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $lacquer->id,    'quantity' => 0.8,  'uom_id' => $ltr->id, 'sequence' => 40]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $screws->id,     'quantity' => 0.15, 'uom_id' => $pcs->id, 'sequence' => 50]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $glue->id,       'quantity' => 0.2,  'uom_id' => $ltr->id, 'sequence' => 60]);
        ProductionBomItem::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'material_id' => $sandpaper->id,  'quantity' => 1.0,  'uom_id' => $pcs->id, 'sequence' => 70]);
        ProductionBomApproval::create(['tenant_id' => $tenant->id, 'bom_id' => $bomTable->id, 'user_id' => $userId, 'action' => 'Approved', 'comments' => 'Approved.']);

        // ═══════════════════════════════════════════════════════════════════════
        //  SECTION 10 — PRODUCTION PLAN
        // ═══════════════════════════════════════════════════════════════════════

        $plan = ProductionPlan::create(['tenant_id' => $tenant->id, 'plan_number' => 'PLAN-FUR-2026-Q3', 'name' => 'Q3 2026 Furniture Production Plan', 'product_id' => $diningChair->id, 'bom_id' => $bomChair->id, 'routing_id' => $rtChair->id, 'quantity' => 50.00, 'start_date' => now()->subDays(14), 'end_date' => now()->addDays(30), 'status' => 'released', 'description' => '50 Dining Chairs and 10 Dining Tables for ABC Hotels hospitality contract.', 'created_by' => $userId, 'approved_by' => $userId, 'approved_at' => now()->subDays(15)]);

        ProductionPlanRequirement::create(['tenant_id' => $tenant->id, 'production_plan_id' => $plan->id, 'product_id' => $teakLumber->id, 'bom_level' => 1, 'required_quantity' => 300.0, 'available_quantity' => 280.0, 'shortage_quantity' => 20.0, 'uom_id' => $pcs->id, 'status' => 'shortage']);
        ProductionPlanRequirement::create(['tenant_id' => $tenant->id, 'production_plan_id' => $plan->id, 'product_id' => $woodStain->id,  'bom_level' => 1, 'required_quantity' => 12.5,  'available_quantity' => 10.0,  'shortage_quantity' => 2.5,  'uom_id' => $ltr->id, 'status' => 'shortage']);
        ProductionPlanRequirement::create(['tenant_id' => $tenant->id, 'production_plan_id' => $plan->id, 'product_id' => $lacquer->id,    'bom_level' => 1, 'required_quantity' => 15.0,  'available_quantity' => 15.0,  'shortage_quantity' => 0.0,  'uom_id' => $ltr->id, 'status' => 'available']);

        ProductionPlanOperation::create(['tenant_id' => $tenant->id, 'production_plan_id' => $plan->id, 'work_center_id' => $wcCnc->id,     'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Cutting', 'setup_time_minutes' => 25, 'processing_time_minutes' => 60, 'total_time_minutes' => 85]);
        ProductionPlanOperation::create(['tenant_id' => $tenant->id, 'production_plan_id' => $plan->id, 'work_center_id' => $wcAssembly->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Assembly',    'setup_time_minutes' => 20, 'processing_time_minutes' => 55, 'total_time_minutes' => 75]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SCENARIO A — PERFECT RUN: 10x Dining Chair, CLOSED
        // ═══════════════════════════════════════════════════════════════════════

        $ordA = ProductionOrder::create(['tenant_id' => $tenant->id, 'order_number' => 'ORD-FURN-A001', 'production_plan_id' => $plan->id, 'product_id' => $diningChair->id, 'bom_id' => $bomChair->id, 'routing_id' => $rtChair->id, 'quantity_ordered' => 10.00, 'quantity_produced' => 10.00, 'start_date' => now()->subDays(10), 'end_date' => now()->subDays(6), 'status' => 'closed', 'created_by' => $userId]);

        $opA10 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Cutting & Shaping',        'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id,    'status' => 'completed', 'setup_time_planned' => 25, 'processing_time_planned' => 60,  'total_time_planned' => 85,  'setup_time_actual' => 22, 'processing_time_actual' => 57,  'actual_start_time' => now()->subDays(10)->setTime(8, 0),  'actual_end_time' => now()->subDays(10)->setTime(9, 20),  'quantity_produced' => 10]);
        $opA20 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'previous_operation_id' => $opA10->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Sanding & Surface Preparation', 'work_center_id' => $wcSanding->id,   'machine_id' => $machSander->id, 'status' => 'completed', 'setup_time_planned' => 10, 'processing_time_planned' => 40,  'total_time_planned' => 50,  'setup_time_actual' => 8,  'processing_time_actual' => 38,  'actual_start_time' => now()->subDays(10)->setTime(9, 30), 'actual_end_time' => now()->subDays(10)->setTime(10, 20), 'quantity_produced' => 10]);
        $opA30 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'previous_operation_id' => $opA20->id, 'sequence' => 30, 'operation_number' => 'OP-030', 'name' => 'Staining & Lacquer Coating',    'work_center_id' => $wcSprayBooth->id, 'machine_id' => $machSpray->id,  'status' => 'completed', 'setup_time_planned' => 15, 'processing_time_planned' => 50,  'total_time_planned' => 65,  'setup_time_actual' => 14, 'processing_time_actual' => 48,  'actual_start_time' => now()->subDays(9)->setTime(8, 0),  'actual_end_time' => now()->subDays(9)->setTime(9, 5),   'quantity_produced' => 10]);
        $opA40 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'previous_operation_id' => $opA30->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Structural Assembly & Joinery', 'work_center_id' => $wcAssembly->id,  'machine_id' => $machPress->id,  'status' => 'completed', 'setup_time_planned' => 20, 'processing_time_planned' => 55,  'total_time_planned' => 75,  'setup_time_actual' => 18, 'processing_time_actual' => 53,  'actual_start_time' => now()->subDays(8)->setTime(8, 0),  'actual_end_time' => now()->subDays(8)->setTime(9, 15),  'quantity_produced' => 10]);
        $opA50 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'previous_operation_id' => $opA40->id, 'sequence' => 50, 'operation_number' => 'OP-050', 'name' => 'Final QC Inspection',           'work_center_id' => $wcQcFinal->id,   'machine_id' => null,            'status' => 'completed', 'setup_time_planned' => 5,  'processing_time_planned' => 20,  'total_time_planned' => 25,  'setup_time_actual' => 5,  'processing_time_actual' => 18,  'actual_start_time' => now()->subDays(7)->setTime(10, 0), 'actual_end_time' => now()->subDays(7)->setTime(10, 25), 'quantity_produced' => 10]);

        $resA1 = ProductionOrderReservation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'product_id' => $teakLumber->id, 'quantity_planned' => 60.0, 'quantity_reserved' => 60.0, 'quantity_issued' => 60.0, 'uom_id' => $pcs->id]);
        ProductionOrderReservation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'product_id' => $woodStain->id, 'quantity_planned' => 2.5, 'quantity_reserved' => 2.5, 'quantity_issued' => 2.5, 'uom_id' => $ltr->id]);
        ProductionOrderReservation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'product_id' => $foam->id,      'quantity_planned' => 10.0, 'quantity_reserved' => 10.0, 'quantity_issued' => 10.0, 'uom_id' => $pcs->id]);
        ProductionOrderIssue::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'reservation_id' => $resA1->id, 'product_id' => $teakLumber->id, 'quantity_issued' => 60.0, 'issued_by' => $userId, 'issued_at' => now()->subDays(10)]);

        foreach ([[$opA10, 10, 22, 57], [$opA20, 10, 8, 38], [$opA30, 9, 14, 48], [$opA40, 8, 18, 53]] as [$op, $dAgo, $setup, $run]) {
            ProductionOrderProgressLog::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'operation_id' => $op->id, 'quantity_produced' => 10, 'setup_minutes_logged' => $setup, 'run_minutes_logged' => $run, 'recorded_by' => $userId, 'recorded_at' => now()->subDays($dAgo)->setTime(11, 0), 'start_time' => now()->subDays($dAgo)->setTime(8, 0), 'stop_time' => now()->subDays($dAgo)->setTime(9, 0)]);
        }

        $schedA = ProductionSchedule::create(['tenant_id' => $tenant->id, 'schedule_number' => 'SCHED-FURN-A001', 'production_order_id' => $ordA->id, 'scheduling_type' => 'forward', 'status' => 'completed', 'scheduled_at' => now()->subDays(11), 'completed_at' => now()->subDays(7), 'created_by' => $userId]);
        foreach ([[$opA10, $wcCnc, $machCnc, 10, 10, 8, 0, 9, 20], [$opA20, $wcSanding, $machSander, 10, 10, 9, 30, 10, 20], [$opA30, $wcSprayBooth, $machSpray, 9, 9, 8, 0, 9, 5], [$opA40, $wcAssembly, $machPress, 8, 8, 8, 0, 9, 15], [$opA50, $wcQcFinal, null, 7, 7, 10, 0, 10, 25]] as $i => [$op,$wc,$mac,$dp,$da,$sh,$sm,$eh,$em]) {
            ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedA->id, 'production_order_id' => $ordA->id, 'production_order_operation_id' => $op->id, 'work_center_id' => $wc->id, 'machine_id' => $mac ? $mac->id : null, 'sequence' => ($i + 1) * 10, 'planned_start' => now()->subDays($dp)->setTime($sh, $sm), 'planned_finish' => now()->subDays($dp)->setTime($eh, $em), 'actual_start' => now()->subDays($da)->setTime($sh, $sm), 'actual_finish' => now()->subDays($da)->setTime($eh, $em), 'status' => 'completed', 'shift_code' => 'SHIFT-DAY']);
        }

        ProductionOperatorAssignment::create(['tenant_id' => $tenant->id, 'production_order_operation_id' => $opA10->id, 'user_id' => $userId, 'assigned_by' => $userId, 'assigned_at' => now()->subDays(11), 'status' => 'accepted']);
        ProductionOperatorAssignment::create(['tenant_id' => $tenant->id, 'production_order_operation_id' => $opA40->id, 'user_id' => $userId, 'assigned_by' => $userId, 'assigned_at' => now()->subDays(9),  'status' => 'accepted']);

        $batchA = ProductionBatch::create(['tenant_id' => $tenant->id, 'batch_number' => 'BAT-FURN-A001', 'production_order_id' => $ordA->id, 'product_id' => $diningChair->id, 'planned_quantity' => 10.0, 'actual_quantity' => 10.0, 'status' => 'completed']);
        for ($i = 1; $i <= 10; $i++) {
            ProductionSerialNumber::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'batch_id' => $batchA->id, 'product_id' => $diningChair->id, 'serial_number' => 'DC1-'.str_pad($i, 5, '0', STR_PAD_LEFT), 'status' => 'produced']);
        }
        ProductionScanLog::create(['tenant_id' => $tenant->id, 'entity_type' => 'order', 'entity_id' => $ordA->id, 'scan_type' => 'order', 'scanned_by' => $userId, 'device_identifier' => 'SCANNER-QC-01', 'scanned_at' => now()->subDays(7)]);

        $inspA = ProductionQualityInspection::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanChair->id, 'stage' => 'final', 'status' => 'approved', 'result' => 'passed', 'production_order_id' => $ordA->id, 'batch_id' => $batchA->id, 'audited_by' => $userId, 'audited_at' => now()->subDays(7)->setTime(11, 0), 'esignature' => hash('sha256', 'chair-insp-A-pass')]);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspA->id, 'quality_plan_parameter_id' => $cP1->id, 'recorded_value_numeric' => 445.0, 'result' => 'passed']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspA->id, 'quality_plan_parameter_id' => $cP2->id, 'recorded_value_pass' => true,  'result' => 'passed']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspA->id, 'quality_plan_parameter_id' => $cP3->id, 'recorded_value_pass' => true,  'result' => 'passed']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspA->id, 'quality_plan_parameter_id' => $cP4->id, 'recorded_value_pass' => true,  'result' => 'passed']);

        ProductionOrderReceipt::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'product_id' => $diningChair->id, 'quantity_received' => 10.00, 'quality_status' => 'passed', 'received_by' => $userId, 'received_at' => now()->subDays(6)]);
        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordA->id, 'event_type' => 'Production Closed', 'title' => 'Scenario A – Perfect Chair Run Closed', 'description' => '10 Dining Chair DC-1 produced. All inspections passed. Zero defects. Order closed.', 'severity' => 'success', 'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(6)]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SCENARIO B — CNC MACHINE BREAKDOWN (Downtime + State History)
        // ═══════════════════════════════════════════════════════════════════════

        ProductionMachineStateHistory::create(['tenant_id' => $tenant->id, 'machine_id' => $machCnc->id, 'state' => 'Running',   'reason' => 'Started order cutting run',                          'started_at' => now()->subDays(5)->setTime(8, 0),  'ended_at' => now()->subDays(5)->setTime(10, 15), 'changed_by' => $userId]);
        ProductionMachineStateHistory::create(['tenant_id' => $tenant->id, 'machine_id' => $machCnc->id, 'state' => 'Breakdown', 'reason' => 'CNC spindle motor thermal overload – auto shutdown', 'started_at' => now()->subDays(5)->setTime(10, 15), 'ended_at' => now()->subDays(5)->setTime(14, 30), 'changed_by' => $userId]);
        ProductionMachineStateHistory::create(['tenant_id' => $tenant->id, 'machine_id' => $machCnc->id, 'state' => 'Idle',      'reason' => 'Spindle motor replaced, cleared for operation',      'started_at' => now()->subDays(5)->setTime(14, 30), 'ended_at' => null,                              'changed_by' => $userId]);

        ProductionMachineDowntime::create(['tenant_id' => $tenant->id, 'machine_id' => $machCnc->id, 'work_center_id' => $wcCnc->id, 'category' => 'Breakdown', 'reason' => 'CNC spindle motor thermal overload – auto shutdown', 'start_time' => now()->subDays(5)->setTime(10, 15), 'end_time' => now()->subDays(5)->setTime(14, 30), 'duration_minutes' => 255.0, 'remarks' => 'Replaced spindle motor cooling fan and thermal fuse. Test cut passed before restart.', 'status' => 'closed', 'created_by' => $userId]);

        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'machine_id' => $machCnc->id, 'event_type' => 'Machine Breakdown', 'title' => 'CNC Router Spindle Thermal Breakdown', 'description' => 'Biesse CNC Router C600 thermal shutdown. Spindle motor overheated. 4hr 15min unplanned downtime.', 'severity' => 'critical', 'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(5)->setTime(10, 15)]);
        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'machine_id' => $machCnc->id, 'event_type' => 'Machine Restored', 'title' => 'CNC Router Restored to Idle', 'description' => 'Spindle motor repaired. Machine returned to Idle. Maintenance clearance issued.', 'severity' => 'success', 'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(5)->setTime(14, 30)]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SCENARIO C — QUALITY FAILURE → NCR → REWORK (Table Leg Wobble)
        // ═══════════════════════════════════════════════════════════════════════

        $ordC = ProductionOrder::create(['tenant_id' => $tenant->id, 'order_number' => 'ORD-FURN-C001', 'production_plan_id' => $plan->id, 'product_id' => $diningTable->id, 'bom_id' => $bomTable->id, 'routing_id' => $rtTable->id, 'quantity_ordered' => 5.00, 'quantity_produced' => 5.00, 'start_date' => now()->subDays(8), 'end_date' => now()->subDays(4), 'status' => 'completed', 'created_by' => $userId]);

        $opC10 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Table Top Cutting',      'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id,    'status' => 'completed', 'setup_time_planned' => 30, 'processing_time_planned' => 90, 'total_time_planned' => 120, 'setup_time_actual' => 30, 'processing_time_actual' => 92, 'quantity_produced' => 5]);
        $opC20 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'previous_operation_id' => $opC10->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Surface Sanding',            'work_center_id' => $wcSanding->id,   'machine_id' => $machSander->id, 'status' => 'completed', 'setup_time_planned' => 15, 'processing_time_planned' => 60, 'total_time_planned' => 75,  'setup_time_actual' => 15, 'processing_time_actual' => 62, 'quantity_produced' => 5]);
        $opC30 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'previous_operation_id' => $opC20->id, 'sequence' => 30, 'operation_number' => 'OP-030', 'name' => 'Staining & Lacquer Coating', 'work_center_id' => $wcSprayBooth->id, 'machine_id' => $machSpray->id,  'status' => 'completed', 'setup_time_planned' => 20, 'processing_time_planned' => 70, 'total_time_planned' => 90,  'setup_time_actual' => 18, 'processing_time_actual' => 68, 'quantity_produced' => 5]);
        $opC40 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'previous_operation_id' => $opC30->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Table Base & Leg Assembly',  'work_center_id' => $wcAssembly->id,  'machine_id' => $machPress->id,  'status' => 'completed', 'setup_time_planned' => 25, 'processing_time_planned' => 80, 'total_time_planned' => 105, 'setup_time_actual' => 28, 'processing_time_actual' => 95, 'quantity_produced' => 5, 'quantity_rejected' => 1]);
        $opC50 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'previous_operation_id' => $opC40->id, 'sequence' => 50, 'operation_number' => 'OP-050', 'name' => 'Final QC Inspection',        'work_center_id' => $wcQcFinal->id,   'machine_id' => null,            'status' => 'completed', 'setup_time_planned' => 5,  'processing_time_planned' => 25, 'total_time_planned' => 30,  'setup_time_actual' => 5,  'processing_time_actual' => 30, 'quantity_produced' => 5, 'quantity_rejected' => 1]);

        ProductionOrderReservation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'product_id' => $teakLumber->id, 'quantity_planned' => 50.0, 'quantity_reserved' => 50.0, 'quantity_issued' => 50.0, 'uom_id' => $pcs->id]);

        $schedC = ProductionSchedule::create(['tenant_id' => $tenant->id, 'schedule_number' => 'SCHED-FURN-C001', 'production_order_id' => $ordC->id, 'scheduling_type' => 'forward', 'status' => 'completed', 'scheduled_at' => now()->subDays(9), 'completed_at' => now()->subDays(4), 'created_by' => $userId]);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedC->id, 'production_order_id' => $ordC->id, 'production_order_operation_id' => $opC10->id, 'work_center_id' => $wcCnc->id,      'machine_id' => $machCnc->id,   'sequence' => 10, 'planned_start' => now()->subDays(8)->setTime(8, 0),  'planned_finish' => now()->subDays(8)->setTime(10, 0),  'actual_start' => now()->subDays(8)->setTime(8, 5),  'actual_finish' => now()->subDays(8)->setTime(10, 10), 'status' => 'completed', 'shift_code' => 'SHIFT-DAY']);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedC->id, 'production_order_id' => $ordC->id, 'production_order_operation_id' => $opC40->id, 'work_center_id' => $wcAssembly->id, 'machine_id' => $machPress->id, 'sequence' => 40, 'planned_start' => now()->subDays(6)->setTime(8, 0),  'planned_finish' => now()->subDays(6)->setTime(9, 45), 'actual_start' => now()->subDays(6)->setTime(8, 0),  'actual_finish' => now()->subDays(6)->setTime(10, 5),  'status' => 'completed', 'shift_code' => 'SHIFT-DAY']);

        $batchC = ProductionBatch::create(['tenant_id' => $tenant->id, 'batch_number' => 'BAT-FURN-C001', 'production_order_id' => $ordC->id, 'product_id' => $diningTable->id, 'planned_quantity' => 5.0, 'actual_quantity' => 5.0, 'status' => 'completed']);
        for ($i = 1; $i <= 5; $i++) {
            ProductionSerialNumber::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'batch_id' => $batchC->id, 'product_id' => $diningTable->id, 'serial_number' => 'DT1-'.str_pad($i, 5, '0', STR_PAD_LEFT), 'status' => ($i === 3) ? 'rework' : 'produced']);
        }

        $inspC = ProductionQualityInspection::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanTable->id, 'stage' => 'final', 'status' => 'approved', 'result' => 'failed', 'production_order_id' => $ordC->id, 'batch_id' => $batchC->id, 'audited_by' => $userId, 'audited_at' => now()->subDays(4)->setTime(14, 0)]);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspC->id, 'quality_plan_parameter_id' => $tP1->id, 'recorded_value_numeric' => 1.2,  'result' => 'passed']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspC->id, 'quality_plan_parameter_id' => $tP2->id, 'recorded_value_pass' => false, 'result' => 'failed', 'recorded_value_text' => 'Unit DT1-00003: 3mm wobble on rear-right leg joint. Glue bond inadequate.']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspC->id, 'quality_plan_parameter_id' => $tP3->id, 'recorded_value_pass' => true,  'result' => 'passed']);

        $ncrC = ProductionNcr::create(['tenant_id' => $tenant->id, 'ncr_number' => 'NCR-FURN-C001', 'category' => 'process', 'status' => 'closed', 'disposition_type' => 'rework', 'quality_inspection_id' => $inspC->id, 'production_order_id' => $ordC->id, 'batch_id' => $batchC->id, 'description' => 'Unit DT1-00003: Rear-right leg joint wobble. Glue bond failure due to insufficient clamp pressure. Rework required.', 'closed_at' => now()->subDays(3), 'closed_by' => $userId]);
        $reworkC = ProductionReworkOrder::create(['tenant_id' => $tenant->id, 'rework_number' => 'RWK-FURN-C001', 'ncr_id' => $ncrC->id, 'original_production_order_id' => $ordC->id, 'status' => 'completed', 'cost_estimate' => 85.00, 'actual_cost' => 70.00]);
        ProductionReworkOperation::create(['tenant_id' => $tenant->id, 'rework_order_id' => $reworkC->id, 'sequence' => 10, 'name' => 'Disassemble Rear-Right Leg Joint',                     'work_center_id' => $wcAssembly->id,  'status' => 'completed', 'processing_time_actual' => 30.0]);
        ProductionReworkOperation::create(['tenant_id' => $tenant->id, 'rework_order_id' => $reworkC->id, 'sequence' => 20, 'name' => 'Re-glue & Clamp Leg Joint at Correct 350 PSI Pressure', 'work_center_id' => $wcAssembly->id,  'status' => 'completed', 'processing_time_actual' => 75.0]);
        ProductionReworkOperation::create(['tenant_id' => $tenant->id, 'rework_order_id' => $reworkC->id, 'sequence' => 30, 'name' => 'Re-inspect Leg Joint After Rework',                      'work_center_id' => $wcQcFinal->id,   'status' => 'completed', 'processing_time_actual' => 20.0]);
        ProductionCapa::create(['tenant_id' => $tenant->id, 'capa_number' => 'CAPA-FURN-C001', 'ncr_id' => $ncrC->id, 'status' => 'closed', 'root_cause_category' => 'process', 'corrective_action' => 'Updated assembly SOP: minimum 350 PSI clamp pressure for leg-to-apron joints. Added pressure gauge check step to OP-040 instructions.', 'preventive_action' => 'Scheduled monthly calibration of pneumatic press. Added force-check step to routing for future orders.', 'action_owner_id' => $userId, 'rca_analysis_json' => ['five_whys' => ['Why wobble? Insufficient glue bond.', 'Why? Clamp pressure too low.', 'Why? Operator skipped gauge check.', 'Why? No gauge check step in SOP.', 'Why? SOP not updated after press change.']], 'closed_by' => $userId, 'closed_at' => now()->subDays(2)]);

        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'event_type' => 'NCR Raised',        'title' => 'Quality Failure – Table Leg Joint Wobble', 'description' => 'NCR NCR-FURN-C001 raised for DT1-00003. Leg joint bond failure at QC. Disposition: Rework.',     'severity' => 'warning',  'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(4)->setTime(14, 30)]);
        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordC->id, 'event_type' => 'Rework Completed',  'title' => 'Rework RWK-FURN-C001 Completed',          'description' => 'Table leg joint reworked and re-inspected. DT1-00003 passed. CAPA closed.',                   'severity' => 'success',  'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(2)]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SCENARIO D — QUALITY FAILURE → NCR → SCRAP (Grain Crack – Chair)
        // ═══════════════════════════════════════════════════════════════════════

        $ordD = ProductionOrder::create(['tenant_id' => $tenant->id, 'order_number' => 'ORD-FURN-D001', 'production_plan_id' => $plan->id, 'product_id' => $diningChair->id, 'bom_id' => $bomChair->id, 'routing_id' => $rtChair->id, 'quantity_ordered' => 8.00, 'quantity_produced' => 7.00, 'start_date' => now()->subDays(4), 'end_date' => now()->subDays(1), 'status' => 'completed', 'created_by' => $userId]);
        $opD40 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordD->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Structural Assembly & Joinery', 'work_center_id' => $wcAssembly->id, 'machine_id' => $machPress->id, 'status' => 'completed', 'setup_time_planned' => 20, 'processing_time_planned' => 55, 'total_time_planned' => 75, 'setup_time_actual' => 20, 'processing_time_actual' => 58, 'quantity_produced' => 8, 'quantity_rejected' => 1, 'quantity_scrapped' => 1]);
        $opD50 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordD->id, 'previous_operation_id' => $opD40->id, 'sequence' => 50, 'operation_number' => 'OP-050', 'name' => 'Final QC Inspection', 'work_center_id' => $wcQcFinal->id, 'machine_id' => null, 'status' => 'completed', 'setup_time_planned' => 5, 'processing_time_planned' => 20, 'total_time_planned' => 25, 'setup_time_actual' => 5, 'processing_time_actual' => 22, 'quantity_produced' => 7, 'quantity_rejected' => 1, 'quantity_scrapped' => 1]);

        $batchD = ProductionBatch::create(['tenant_id' => $tenant->id, 'batch_number' => 'BAT-FURN-D001', 'production_order_id' => $ordD->id, 'product_id' => $diningChair->id, 'planned_quantity' => 8.0, 'actual_quantity' => 7.0, 'status' => 'completed']);
        for ($i = 1; $i <= 8; $i++) {
            ProductionSerialNumber::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordD->id, 'batch_id' => $batchD->id, 'product_id' => $diningChair->id, 'serial_number' => 'DC1-D-'.str_pad($i, 4, '0', STR_PAD_LEFT), 'status' => ($i === 6) ? 'scrapped' : 'produced']);
        }

        $inspD = ProductionQualityInspection::create(['tenant_id' => $tenant->id, 'quality_plan_id' => $qPlanChair->id, 'stage' => 'final', 'status' => 'approved', 'result' => 'failed', 'production_order_id' => $ordD->id, 'batch_id' => $batchD->id, 'audited_by' => $userId, 'audited_at' => now()->subDays(1)->setTime(15, 0)]);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspD->id, 'quality_plan_parameter_id' => $cP1->id, 'recorded_value_numeric' => 448.0, 'result' => 'passed']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspD->id, 'quality_plan_parameter_id' => $cP2->id, 'recorded_value_pass' => false, 'result' => 'failed', 'recorded_value_text' => 'Unit DC1-D-0006: severe longitudinal grain crack on front-right teak leg. Structurally unsafe.']);
        ProductionQualityInspectionResult::create(['tenant_id' => $tenant->id, 'quality_inspection_id' => $inspD->id, 'quality_plan_parameter_id' => $cP4->id, 'recorded_value_pass' => false, 'result' => 'failed', 'recorded_value_text' => 'DC1-D-0006 failed 120kg load test. Front-right leg cracked under load.']);

        $ncrD = ProductionNcr::create(['tenant_id' => $tenant->id, 'ncr_number' => 'NCR-FURN-D001', 'category' => 'material', 'status' => 'closed', 'disposition_type' => 'scrap', 'quality_inspection_id' => $inspD->id, 'production_order_id' => $ordD->id, 'batch_id' => $batchD->id, 'description' => 'Unit DC1-D-0006: Severe longitudinal grain crack on front-right leg. Material defect from teak blank. Irreparable.', 'closed_at' => now()->subDays(1)->setTime(16, 0), 'closed_by' => $userId]);
        ProductionScrapDisposal::create(['tenant_id' => $tenant->id, 'ncr_id' => $ncrD->id, 'category' => 'semi_finished', 'reason_code' => 'material_defect', 'quantity' => 1.00, 'cost' => 45.00, 'disposed_by' => $userId, 'disposed_at' => now()->subDay()]);

        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordD->id, 'event_type' => 'NCR Raised', 'title' => 'Material Defect – Grain Crack on Chair Leg', 'description' => 'NCR NCR-FURN-D001 raised for DC1-D-0006. Severe teak grain crack detected. Disposition: Scrap. 1 unit written off at cost £45.', 'severity' => 'critical', 'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(1)->setTime(15, 30)]);

        // ═══════════════════════════════════════════════════════════════════════
        //  SCENARIO E — ACTIVE ORDER IN PROGRESS: 10x Dining Table, OP-030 RUNNING
        // ═══════════════════════════════════════════════════════════════════════

        $ordE = ProductionOrder::create(['tenant_id' => $tenant->id, 'order_number' => 'ORD-FURN-E001', 'production_plan_id' => $plan->id, 'product_id' => $diningTable->id, 'bom_id' => $bomTable->id, 'routing_id' => $rtTable->id, 'quantity_ordered' => 10.00, 'quantity_produced' => 0.00, 'start_date' => now()->subDays(2), 'end_date' => now()->addDays(5), 'status' => 'in_progress', 'created_by' => $userId]);

        $opE10 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'CNC Table Top Cutting',      'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id,    'status' => 'completed', 'setup_time_planned' => 30, 'processing_time_planned' => 90,  'total_time_planned' => 120, 'setup_time_actual' => 32, 'processing_time_actual' => 95, 'actual_start_time' => now()->subDays(2)->setTime(8, 0),  'actual_end_time' => now()->subDays(2)->setTime(10, 10), 'quantity_produced' => 10]);
        $opE20 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'previous_operation_id' => $opE10->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Surface Sanding',            'work_center_id' => $wcSanding->id,   'machine_id' => $machSander->id, 'status' => 'completed', 'setup_time_planned' => 15, 'processing_time_planned' => 60,  'total_time_planned' => 75,  'setup_time_actual' => 14, 'processing_time_actual' => 62, 'actual_start_time' => now()->subDays(2)->setTime(10, 30), 'actual_end_time' => now()->subDays(2)->setTime(12, 0), 'quantity_produced' => 10]);
        $opE30 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'previous_operation_id' => $opE20->id, 'sequence' => 30, 'operation_number' => 'OP-030', 'name' => 'Staining & Lacquer Coating', 'work_center_id' => $wcSprayBooth->id, 'machine_id' => $machSpray->id,  'status' => 'running',   'setup_time_planned' => 20, 'processing_time_planned' => 70,  'total_time_planned' => 90,  'setup_time_actual' => 20,                               'actual_start_time' => now()->subHours(3)]);
        $opE40 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'previous_operation_id' => $opE30->id, 'sequence' => 40, 'operation_number' => 'OP-040', 'name' => 'Table Base & Leg Assembly',  'work_center_id' => $wcAssembly->id,  'machine_id' => $machPress->id,  'status' => 'waiting',   'setup_time_planned' => 25, 'processing_time_planned' => 80,  'total_time_planned' => 105]);
        $opE50 = ProductionOrderOperation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'previous_operation_id' => $opE40->id, 'sequence' => 50, 'operation_number' => 'OP-050', 'name' => 'Final QC Inspection',        'work_center_id' => $wcQcFinal->id,   'machine_id' => null,            'status' => 'waiting',   'setup_time_planned' => 5,  'processing_time_planned' => 25,  'total_time_planned' => 30]);

        ProductionOrderReservation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'product_id' => $teakLumber->id, 'quantity_planned' => 100.0, 'quantity_reserved' => 100.0, 'quantity_issued' => 20.0, 'uom_id' => $pcs->id]);
        ProductionOrderReservation::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'product_id' => $woodStain->id, 'quantity_planned' => 6.0,  'quantity_reserved' => 6.0,  'quantity_issued' => 6.0, 'uom_id' => $ltr->id]);

        $schedE = ProductionSchedule::create(['tenant_id' => $tenant->id, 'schedule_number' => 'SCHED-FURN-E001', 'production_order_id' => $ordE->id, 'scheduling_type' => 'forward', 'status' => 'in_progress', 'scheduled_at' => now()->subDays(3), 'completed_at' => null, 'created_by' => $userId]);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedE->id, 'production_order_id' => $ordE->id, 'production_order_operation_id' => $opE10->id, 'work_center_id' => $wcCnc->id,       'machine_id' => $machCnc->id,   'sequence' => 10, 'planned_start' => now()->subDays(2)->setTime(8, 0),  'planned_finish' => now()->subDays(2)->setTime(10, 0),  'actual_start' => now()->subDays(2)->setTime(8, 0),  'actual_finish' => now()->subDays(2)->setTime(10, 10), 'status' => 'completed', 'shift_code' => 'SHIFT-DAY']);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedE->id, 'production_order_id' => $ordE->id, 'production_order_operation_id' => $opE20->id, 'work_center_id' => $wcSanding->id,   'machine_id' => $machSander->id, 'sequence' => 20, 'planned_start' => now()->subDays(2)->setTime(10, 30), 'planned_finish' => now()->subDays(2)->setTime(12, 0), 'actual_start' => now()->subDays(2)->setTime(10, 30), 'actual_finish' => now()->subDays(2)->setTime(12, 0), 'status' => 'completed', 'shift_code' => 'SHIFT-DAY']);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedE->id, 'production_order_id' => $ordE->id, 'production_order_operation_id' => $opE30->id, 'work_center_id' => $wcSprayBooth->id, 'machine_id' => $machSpray->id, 'sequence' => 30, 'planned_start' => now()->setTime(8, 0),               'planned_finish' => now()->setTime(9, 30),              'actual_start' => now()->subHours(3),               'actual_finish' => null,                              'status' => 'running',   'shift_code' => 'SHIFT-DAY']);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedE->id, 'production_order_id' => $ordE->id, 'production_order_operation_id' => $opE40->id, 'work_center_id' => $wcAssembly->id,  'machine_id' => $machPress->id, 'sequence' => 40, 'planned_start' => now()->addHours(2),                'planned_finish' => now()->addHours(4),                'actual_start' => null,                             'actual_finish' => null,                              'status' => 'ready',     'shift_code' => 'SHIFT-DAY']);
        ProductionScheduleOperation::create(['tenant_id' => $tenant->id, 'production_schedule_id' => $schedE->id, 'production_order_id' => $ordE->id, 'production_order_operation_id' => $opE50->id, 'work_center_id' => $wcQcFinal->id,   'machine_id' => null,           'sequence' => 50, 'planned_start' => now()->addHours(5),                'planned_finish' => now()->addHours(6),                'actual_start' => null,                             'actual_finish' => null,                              'status' => 'waiting',   'shift_code' => 'SHIFT-DAY']);

        ProductionOperatorAssignment::create(['tenant_id' => $tenant->id, 'production_order_operation_id' => $opE30->id, 'user_id' => $userId, 'assigned_by' => $userId, 'assigned_at' => now()->subHours(4), 'status' => 'accepted']);

        ProductionBatch::create(['tenant_id' => $tenant->id, 'batch_number' => 'BAT-FURN-E001', 'production_order_id' => $ordE->id, 'product_id' => $diningTable->id, 'planned_quantity' => 10.0, 'actual_quantity' => 0.0, 'status' => 'in_progress']);

        ProductionMachineStateHistory::create(['tenant_id' => $tenant->id, 'machine_id' => $machSpray->id, 'state' => 'Running', 'reason' => 'Started staining operation for ORD-FURN-E001', 'started_at' => now()->subHours(3), 'ended_at' => null, 'changed_by' => $userId]);

        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'machine_id' => $machCnc->id,  'operator_id' => $userId, 'event_type' => 'Operation Started',    'title' => 'OP-010 CNC Cutting Started',              'description' => '10x Table DT-1 top cutting started on Biesse CNC Router.',                       'severity' => 'info',    'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(2)->setTime(8, 0)]);
        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'machine_id' => $machCnc->id,  'operator_id' => $userId, 'event_type' => 'Operation Completed',  'title' => 'OP-010 CNC Cutting Completed',            'description' => 'CNC cutting done for all 10 tabletops. No defects. Passed to sanding.',         'severity' => 'success', 'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subDays(2)->setTime(10, 10)]);
        ProductionEventTimeline::create(['tenant_id' => $tenant->id, 'production_order_id' => $ordE->id, 'machine_id' => $machSpray->id, 'operator_id' => $userId, 'event_type' => 'Operation Started',    'title' => 'OP-030 Staining & Lacquer – In Progress', 'description' => 'Walnut stain application started on Spray System W-400. Est. 1hr 10min remaining.', 'severity' => 'info',    'event_source' => 'Seeder', 'triggered_by' => $userId, 'event_time' => now()->subHours(3)]);

        // Dashboard preference
        ProductionDashboardPreference::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $userId, 'dashboard_type' => 'oee'],
            ['widgets' => ['oee_widget', 'yield_widget', 'downtime_widget', 'quality_widget'], 'default_filters' => ['period' => 'today'], 'layout' => 'grid']
        );
    }
}
