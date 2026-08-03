<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrusherMachineProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Step 0: Clean all previous production planning & scenario data ───────
        Schema::disableForeignKeyConstraints();

        DB::table('production_wip_transactions')->truncate();
        DB::table('production_wips')->truncate();
        DB::table('production_cost_adjustments')->truncate();
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
        DB::table('production_calendar_holidays')->truncate();

        // 4 Core Setup Modules Tables
        DB::table('production_routing_operation_alternate_machines')->truncate();
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

        Schema::enableForeignKeyConstraints();

        // ─── Step 1: Resolve Tenant & User ──────────────────────────────────────
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();
        if (!$tenant) {
            $this->command->warn('No tenant found for Crusher Machine Production seeding.');
            return;
        }

        $adminUser = User::where('tenant_id', $tenant->id)->first();
        $userId = $adminUser ? $adminUser->id : 1;

        // ─── Step 2: Resolve UOMs & Products from CrusherMachineInventorySeeder ──
        $pcs  = Uom::where('tenant_id', $tenant->id)->where('code', 'PCS')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'PCS'], ['name' => 'Pieces']);
        $kgs  = Uom::where('tenant_id', $tenant->id)->where('code', 'KGS')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'KGS'], ['name' => 'Kilograms']);
        $sets = Uom::where('tenant_id', $tenant->id)->where('code', 'SET')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'SET'], ['name' => 'Sets']);

        // Fetch products created by CrusherMachineInventorySeeder
        $rmSteel40  = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-STEEL-PLT-40')->first();
        $rmSteel25  = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-STEEL-PLT-25')->first();
        $rmChnl300  = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-STEEL-CHNL-300')->first();
        $rmJawFixed = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-JAW-FIXED-MN')->first();
        $rmJawMov   = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-JAW-MOVING-MN')->first();
        $rmShaft    = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-SHFT-ECC-42CR')->first();
        $rmBearing  = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-BRG-SKF-22330')->first();
        $rmMotor    = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-MTR-75HP-3PH')->first();

        $sfFrame    = Product::where('tenant_id', $tenant->id)->where('sku', 'SF-CRUSHER-FRAME')->first();
        $sfPitman   = Product::where('tenant_id', $tenant->id)->where('sku', 'SF-PITMAN-BEARING-ASSY')->first();
        $sfToggle   = Product::where('tenant_id', $tenant->id)->where('sku', 'SF-TOGGLE-SAFETY-SET')->first();
        $fgCrusher  = Product::where('tenant_id', $tenant->id)->where('sku', 'FG-CRUSHER-JAW-3020')->first();

        if (!$fgCrusher || !$sfFrame || !$sfPitman || !$sfToggle) {
            $this->command->error('Crusher Machine inventory products missing! Run CrusherMachineInventorySeeder first.');
            return;
        }

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 1: WORK CENTERS
        // ═════════════════════════════════════════════════════════════════════════

        // Dept 1: Heavy Fabrication & Structural Dept
        $deptFab = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Heavy Fabrication & Structural Dept',
            'code' => 'DEPT-HEAVY-FAB',
            'type' => 'department',
            'work_center_type' => 'machining',
            'department_name' => 'Fabrication',
            'location' => 'Plant 1 - Heavy Bay West',
            'capacity_per_hour' => 15.00,
            'efficiency_percentage' => 92.00,
            'cost_per_hour' => 85.00,
            'overhead_rate' => 35.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        $secCutting = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'CNC Flame & Plasma Cutting Section',
            'code' => 'SEC-CUTTING',
            'type' => 'section',
            'work_center_type' => 'machining',
            'department_name' => 'Fabrication',
            'location' => 'Plant 1 - Bay 1',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 90.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $deptFab->id,
        ]);

        $wcCncCut = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Heavy CNC Oxy-Fuel & Plasma Work Center',
            'code' => 'WC-CNC-CUT',
            'type' => 'work_center',
            'work_center_type' => 'machining',
            'department_name' => 'Fabrication',
            'location' => 'Plant 1 - Station C1',
            'capacity_per_hour' => 5.00,
            'efficiency_percentage' => 94.00,
            'cost_per_hour' => 110.00,
            'overhead_rate' => 50.00,
            'status' => 'active',
            'parent_id' => $secCutting->id,
        ]);

        $secWelding = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Heavy Welding & Structural Section',
            'code' => 'SEC-WELDING',
            'type' => 'section',
            'work_center_type' => 'assembly',
            'department_name' => 'Fabrication',
            'location' => 'Plant 1 - Bay 2',
            'capacity_per_hour' => 8.00,
            'efficiency_percentage' => 90.00,
            'cost_per_hour' => 95.00,
            'overhead_rate' => 45.00,
            'status' => 'active',
            'parent_id' => $deptFab->id,
        ]);

        $wcHeavyWeld = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Submerged Arc & Heavy MIG Welding Center',
            'code' => 'WC-HEAVY-WELD',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Fabrication',
            'location' => 'Plant 1 - Station W2',
            'capacity_per_hour' => 4.00,
            'efficiency_percentage' => 90.00,
            'cost_per_hour' => 120.00,
            'overhead_rate' => 55.00,
            'status' => 'active',
            'parent_id' => $secWelding->id,
        ]);

        // Dept 2: Precision Mechanical Machining Dept
        $deptMach = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Precision Mechanical Machining Dept',
            'code' => 'DEPT-MACHINING',
            'type' => 'department',
            'work_center_type' => 'machining',
            'department_name' => 'Machining',
            'location' => 'Plant 2 - Machine Shop',
            'capacity_per_hour' => 12.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 100.00,
            'overhead_rate' => 50.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        $secBoring = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Heavy Boring & Turning Section',
            'code' => 'SEC-BORING',
            'type' => 'section',
            'work_center_type' => 'machining',
            'department_name' => 'Machining',
            'location' => 'Plant 2 - Bay 1',
            'capacity_per_hour' => 6.00,
            'efficiency_percentage' => 94.00,
            'cost_per_hour' => 115.00,
            'overhead_rate' => 60.00,
            'status' => 'active',
            'parent_id' => $deptMach->id,
        ]);

        $wcHorizBoring = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Horizontal Boring & Machining Center',
            'code' => 'WC-HORIZ-BORING',
            'type' => 'work_center',
            'work_center_type' => 'machining',
            'department_name' => 'Machining',
            'location' => 'Plant 2 - Station M1',
            'capacity_per_hour' => 3.00,
            'efficiency_percentage' => 92.00,
            'cost_per_hour' => 140.00,
            'overhead_rate' => 70.00,
            'status' => 'active',
            'parent_id' => $secBoring->id,
        ]);

        // Dept 3: Heavy Equipment Assembly Dept
        $deptAssy = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Heavy Equipment Assembly Dept',
            'code' => 'DEPT-CRUSHER-ASSY',
            'type' => 'department',
            'work_center_type' => 'assembly',
            'department_name' => 'Assembly',
            'location' => 'Plant 3 - Erection Yard',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 96.00,
            'cost_per_hour' => 90.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        $secSubAssy = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sub-Assembly & Pitman Fitting Section',
            'code' => 'SEC-SUB-ASSY',
            'type' => 'section',
            'work_center_type' => 'assembly',
            'department_name' => 'Assembly',
            'location' => 'Plant 3 - Bay 1',
            'capacity_per_hour' => 5.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 95.00,
            'overhead_rate' => 45.00,
            'status' => 'active',
            'parent_id' => $deptAssy->id,
        ]);

        $wcPitmanBay = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pitman & Bearing Press Bay',
            'code' => 'WC-PITMAN-BAY',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Assembly',
            'location' => 'Plant 3 - Station A1',
            'capacity_per_hour' => 3.00,
            'efficiency_percentage' => 94.00,
            'cost_per_hour' => 110.00,
            'overhead_rate' => 50.00,
            'status' => 'active',
            'parent_id' => $secSubAssy->id,
        ]);

        $secMainLine = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Crusher Integration Section',
            'code' => 'SEC-MAIN-LINE',
            'type' => 'section',
            'work_center_type' => 'assembly',
            'department_name' => 'Assembly',
            'location' => 'Plant 3 - Bay 2',
            'capacity_per_hour' => 4.00,
            'efficiency_percentage' => 96.00,
            'cost_per_hour' => 100.00,
            'overhead_rate' => 50.00,
            'status' => 'active',
            'parent_id' => $deptAssy->id,
        ]);

        $wcCrusherLine = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Crusher Main Assembly & Erection Line',
            'code' => 'WC-CRUSHER-LINE',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Assembly',
            'location' => 'Plant 3 - Line 1',
            'capacity_per_hour' => 2.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 130.00,
            'overhead_rate' => 60.00,
            'status' => 'active',
            'parent_id' => $secMainLine->id,
        ]);

        // Dept 4: Protective Coating Dept
        $deptCoating = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Protective Coating Dept',
            'code' => 'DEPT-COATING',
            'type' => 'department',
            'work_center_type' => 'assembly',
            'department_name' => 'Coating',
            'location' => 'Plant 4 - Paint Yard',
            'capacity_per_hour' => 8.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 75.00,
            'overhead_rate' => 30.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        $secPaint = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Shot Blasting & Industrial Painting Section',
            'code' => 'SEC-PAINT',
            'type' => 'section',
            'work_center_type' => 'assembly',
            'department_name' => 'Coating',
            'location' => 'Plant 4 - Bay 1',
            'capacity_per_hour' => 4.00,
            'efficiency_percentage' => 94.00,
            'cost_per_hour' => 85.00,
            'overhead_rate' => 35.00,
            'status' => 'active',
            'parent_id' => $deptCoating->id,
        ]);

        $wcBlastSpray = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Shot Blast & Airless Coating Chamber',
            'code' => 'WC-BLAST-SPRAY',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Coating',
            'location' => 'Plant 4 - Chamber 1',
            'capacity_per_hour' => 2.00,
            'efficiency_percentage' => 92.00,
            'cost_per_hour' => 95.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $secPaint->id,
        ]);

        // Dept 5: Quality Assurance & Testing Dept
        $deptQa = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Quality Assurance & Testing Dept',
            'code' => 'DEPT-QA',
            'type' => 'department',
            'work_center_type' => 'inspection',
            'department_name' => 'Quality',
            'location' => 'Plant 3 - Testing Dock',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 98.00,
            'cost_per_hour' => 80.00,
            'overhead_rate' => 30.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        $secTesting = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Performance Audit & Testing Section',
            'code' => 'SEC-TESTING',
            'type' => 'section',
            'work_center_type' => 'inspection',
            'department_name' => 'Quality',
            'location' => 'Plant 3 - Rig Bay',
            'capacity_per_hour' => 5.00,
            'efficiency_percentage' => 98.00,
            'cost_per_hour' => 90.00,
            'overhead_rate' => 35.00,
            'status' => 'active',
            'parent_id' => $deptQa->id,
        ]);

        $wcQaTesting = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Crusher No-Load & Dynamic Testing Rig',
            'code' => 'WC-QA-TESTING',
            'type' => 'work_center',
            'work_center_type' => 'inspection',
            'department_name' => 'Quality',
            'location' => 'Plant 3 - QA Dock 1',
            'capacity_per_hour' => 2.00,
            'efficiency_percentage' => 98.00,
            'cost_per_hour' => 100.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $secTesting->id,
        ]);

        // Shift & Calendar Setup
        $shiftDay = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'name' => 'Day Shift',
            'code' => 'SHIFT-DAY',
            'start_time' => '08:00:00',
            'end_time' => '16:30:00',
            'break_minutes' => 30,
            'overtime_allowed' => true,
            'active' => true,
        ]);

        foreach ([$wcCncCut, $wcHeavyWeld, $wcHorizBoring, $wcPitmanBay, $wcCrusherLine, $wcBlastSpray, $wcQaTesting] as $wcItem) {
            DB::table('production_work_center_shifts')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'work_center_id' => $wcItem->id,
                'shift_id' => $shiftDay->id,
            ]);
        }

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 2: MACHINES
        // ═════════════════════════════════════════════════════════════════════════

        $machCncOxy = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcCncCut->id,
            'name' => 'Messer Heavy CNC Oxy-Fuel & Plasma Cutter',
            'code' => 'MCH-CNC-OXY1',
            'machine_type' => 'CNC Plasma/Oxy Cutter',
            'manufacturer' => 'Messer Cutting Systems',
            'model_number' => 'OmniMat 6000',
            'capacity' => 5.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machCncAlt = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcCncCut->id,
            'name' => 'ESAB Heavy CNC Gantry Cutting Machine',
            'code' => 'MCH-CNC-ALT1',
            'machine_type' => 'CNC Plasma Cutter',
            'manufacturer' => 'ESAB',
            'model_number' => 'SupraRex HD',
            'capacity' => 4.5,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machWeldArc = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcHeavyWeld->id,
            'name' => 'Lincoln Electric Submerged Arc Welding Gantry',
            'code' => 'MCH-WELD-ARC1',
            'machine_type' => 'Submerged Arc Welder',
            'manufacturer' => 'Lincoln Electric',
            'model_number' => 'Power Wave AC/DC 1000',
            'capacity' => 4.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machBoring = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcHorizBoring->id,
            'name' => 'Toshiba Heavy Horizontal Boring Machine',
            'code' => 'MCH-BORING-H160',
            'machine_type' => 'Horizontal Boring Lathe',
            'manufacturer' => 'Toshiba Machine',
            'model_number' => 'BTH-130.R24',
            'capacity' => 3.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machPress = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcPitmanBay->id,
            'name' => '500-Ton Hydraulic Bearing Press Rig',
            'code' => 'MCH-PRESS-HYD500',
            'machine_type' => 'Hydraulic Assembly Press',
            'manufacturer' => 'Dake Presses',
            'model_number' => 'Elec-Draulic 500T',
            'capacity' => 3.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machCrane = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcCrusherLine->id,
            'name' => '30-Ton Heavy Double Girder Overhead Crane',
            'code' => 'MCH-CRANE-OVERHEAD',
            'machine_type' => 'Erection Overhead Crane',
            'manufacturer' => 'Konecranes',
            'model_number' => 'CXT 30T-DG',
            'capacity' => 2.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machBlastSpray = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcBlastSpray->id,
            'name' => 'Industrial Shot Blasting & Airless Spray Unit',
            'code' => 'MCH-BLAST-SPRAY1',
            'machine_type' => 'Shot Blast & Paint Rig',
            'manufacturer' => 'Wheelabrator',
            'model_number' => 'RB-2000 Heavy',
            'capacity' => 2.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $machQaRig = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcQaTesting->id,
            'name' => 'Dynamic Vibration & Power Performance Testing Rig',
            'code' => 'MCH-QA-DYN1',
            'machine_type' => 'QA Dynamic Test Station',
            'manufacturer' => 'Bently Nevada / SKF',
            'model_number' => 'VibroTest 8000',
            'capacity' => 2.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 3: ROUTINGS (Includes Overlap Feature & Sub-Assemblies)
        // ═════════════════════════════════════════════════════════════════════════

        // ── 3.1 Routing: Crusher Frame Chassis Sub-Assembly ──────────────────────
        $rtFrame = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'RT-CRUSHER-FRAME',
            'name' => 'Heavy Fabricated Frame Chassis Routing',
            'product_id' => $sfFrame->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => Carbon::now()->subMonths(3),
            'effective_to' => Carbon::now()->addYears(3),
            'status' => Routing::STATUS_ACTIVE,
            'description' => 'Fabrication routing for 40mm steel plate cutting and submerged arc welding of heavy jaw crusher frame.',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
        ]);

        $opFrame10 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtFrame->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Flame Cutting Steel Side Plates',
            'description' => 'CNC oxy-fuel flame cutting of 40mm IS2062 side plates and 25mm stiffeners.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcCncCut->id,
            'machine_id' => $machCncOxy->id,
            'setup_time_minutes' => 30.00,
            'processing_time_minutes' => 120.00,
            'wait_time_minutes' => 15.00,
            'expected_yield_percentage' => 97.00,
            'labor_cost_rate' => 35.00,
            'machine_cost_rate' => 60.00,
            'instructions' => 'Load 40mm steel plate on cutting bed. Run program CNC-FRAME-SIDE-40. Verify diagonal tolerance +/- 1.5mm.',
            'quality_required' => false,
            'overlap_enabled' => false,
        ]);

        $opFrame20 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtFrame->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Submerged Arc Welding & Frame Box Joining',
            'description' => 'Heavy structural MIG and Submerged Arc welding of frame side plates and channel cross members.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcHeavyWeld->id,
            'machine_id' => $machWeldArc->id,
            'setup_time_minutes' => 45.00,
            'processing_time_minutes' => 180.00,
            'wait_time_minutes' => 30.00,
            'expected_yield_percentage' => 98.00,
            'labor_cost_rate' => 40.00,
            'machine_cost_rate' => 70.00,
            'instructions' => 'Preheat joint lines to 150C. Execute multi-pass SAW weld on main frame seams. Perform ultrasonic test on welds.',
            'quality_required' => true,
            // OVERLAP FEATURE:
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 1.0,
            'transfer_lag_minutes' => 30,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtFrame->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Crusher frame routing approved for production.',
        ]);

        // ── 3.2 Routing: Pitman & Bearing Sub-Assembly ───────────────────────────
        $rtPitman = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'RT-PITMAN-BEARING-ASSY',
            'name' => 'Pitman & Shaft Sub-Assembly Routing',
            'product_id' => $sfPitman->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => Carbon::now()->subMonths(3),
            'effective_to' => Carbon::now()->addYears(3),
            'status' => Routing::STATUS_ACTIVE,
            'description' => 'Precision machining of pitman housing and hydraulic press fitting of SKF 22330 bearings onto forged eccentric shaft.',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
        ]);

        $opPit10 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtPitman->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Boring Shaft Bearing Housings',
            'description' => 'Precision horizontal boring of eccentric shaft bearing journals.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcHorizBoring->id,
            'machine_id' => $machBoring->id,
            'setup_time_minutes' => 60.00,
            'processing_time_minutes' => 150.00,
            'wait_time_minutes' => 20.00,
            'expected_yield_percentage' => 99.00,
            'labor_cost_rate' => 45.00,
            'machine_cost_rate' => 85.00,
            'instructions' => 'Set up horizontal boring machine. Bore journal to H7 tolerance (+0.030mm). Check concentricity.',
            'quality_required' => true,
            'overlap_enabled' => false,
        ]);

        $opPit20 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtPitman->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Hydraulic Press Fit SKF Bearings',
            'description' => 'Induction heating of SKF 22330 bearings and 500-ton hydraulic press fit onto 42CrMo4 shaft.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcPitmanBay->id,
            'machine_id' => $machPress->id,
            'setup_time_minutes' => 30.00,
            'processing_time_minutes' => 90.00,
            'wait_time_minutes' => 15.00,
            'expected_yield_percentage' => 99.50,
            'labor_cost_rate' => 38.00,
            'machine_cost_rate' => 65.00,
            'instructions' => 'Heat SKF spherical roller bearing to 110C in induction heater. Press fit onto eccentric shaft at 350 bar. Pack with lithium grease.',
            'quality_required' => true,
            // OVERLAP FEATURE:
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 1.0,
            'transfer_lag_minutes' => 20,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtPitman->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Pitman sub-assembly routing approved.',
        ]);

        // ── 3.3 Routing: Toggle Safety Set ───────────────────────────────────────
        $rtToggle = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'RT-TOGGLE-SAFETY-SET',
            'name' => 'Toggle Plate & Tension Assembly Routing',
            'product_id' => $sfToggle->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => Carbon::now()->subMonths(3),
            'effective_to' => Carbon::now()->addYears(3),
            'status' => Routing::STATUS_ACTIVE,
            'description' => 'Fabrication and machining of cast steel toggle plate and tension spring safety block.',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtToggle->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Cutting & Grooving Toggle Plate',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcCncCut->id,
            'machine_id' => $machCncOxy->id,
            'setup_time_minutes' => 20.00,
            'processing_time_minutes' => 60.00,
            'wait_time_minutes' => 10.00,
            'expected_yield_percentage' => 99.00,
            'labor_cost_rate' => 35.00,
            'machine_cost_rate' => 60.00,
            'instructions' => 'Cut toggle plate profile and machine seating grooves for toggle seats.',
            'quality_required' => false,
            'overlap_enabled' => false,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtToggle->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Toggle plate routing approved.',
        ]);

        // ── 3.4 Routing: Master Finished Good Jaw Crusher 30"x20" ─────────────────
        $rtCrusher = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'RT-CRUSHER-JAW-3020',
            'name' => 'Industrial Stone Jaw Crusher (30"x20") Master Routing',
            'product_id' => $fgCrusher->id,
            'version' => '1.0.0',
            'revision' => 0,
            'is_default' => true,
            'effective_from' => Carbon::now()->subMonths(3),
            'effective_to' => Carbon::now()->addYears(3),
            'status' => Routing::STATUS_ACTIVE,
            'description' => 'Complete manufacturing and erection routing for 30"x20" Heavy Stone Jaw Crusher including frame cutting, machining, pitman mounting, painting, and QA test.',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
        ]);

        // Operation 10: CNC Cutting & Beveling
        $opC10 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Heavy Steel Plate Flame Cutting & Beveling',
            'description' => 'CNC oxy-fuel profile cutting and weld edge prep for 40mm outer frame plates.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcCncCut->id,
            'machine_id' => $machCncOxy->id,
            'setup_time_minutes' => 45.00,
            'processing_time_minutes' => 180.00,
            'wait_time_minutes' => 20.00,
            'expected_yield_percentage' => 97.00,
            'labor_cost_rate' => 35.00,
            'machine_cost_rate' => 60.00,
            'instructions' => 'Clean steel plate surface. Torch bevel V-groove at 45 degrees. Stencil heat code on cut parts.',
            'quality_required' => true,
            // OVERLAP FEATURE ENABLED
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 1.0,
            'transfer_lag_minutes' => 30,
        ]);

        // Attach Operation Materials to OP-010
        if ($rmSteel40 && $rmSteel25) {
            RoutingOperationMaterial::create([
                'tenant_id' => $tenant->id,
                'routing_operation_id' => $opC10->id,
                'material_id' => $rmSteel40->id,
                'quantity' => 1200.00,
                'uom_id' => $kgs->id,
                'consumption_type' => 'proportional',
                'sequence' => 10,
            ]);
            RoutingOperationMaterial::create([
                'tenant_id' => $tenant->id,
                'routing_operation_id' => $opC10->id,
                'material_id' => $rmSteel25->id,
                'quantity' => 800.00,
                'uom_id' => $kgs->id,
                'consumption_type' => 'proportional',
                'sequence' => 20,
            ]);
        }

        // Attach Alternate Machine to OP-010
        RoutingOperationAlternateMachine::create([
            'tenant_id' => $tenant->id,
            'routing_operation_id' => $opC10->id,
            'machine_id' => $machCncAlt->id,
            'priority' => 1,
        ]);

        // Operation 20: Submerged Arc Welding & Frame Structural Assembly
        $opC20 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Submerged Arc Welding & Frame Structural Assembly',
            'description' => 'Box frame alignment, tacking, and high-strength submerged arc seam welding.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcHeavyWeld->id,
            'machine_id' => $machWeldArc->id,
            'setup_time_minutes' => 60.00,
            'processing_time_minutes' => 240.00,
            'wait_time_minutes' => 30.00,
            'expected_yield_percentage' => 98.00,
            'labor_cost_rate' => 40.00,
            'machine_cost_rate' => 70.00,
            'instructions' => 'Mount side plates on welding jig. Multi-pass SAW welding. Perform magnetic particle inspection.',
            'quality_required' => true,
            // OVERLAP FEATURE ENABLED
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 1.0,
            'transfer_lag_minutes' => 45,
        ]);

        // Operation 30: Precision Horizontal Boring of Bearing Housings
        $opC30 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 30,
            'operation_number' => 'OP-030',
            'name' => 'Precision Boring & Facing Frame Bearing Seats',
            'description' => 'Horizontal boring machine setup for line boring of main eccentric bearing housings.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcHorizBoring->id,
            'machine_id' => $machBoring->id,
            'setup_time_minutes' => 90.00,
            'processing_time_minutes' => 210.00,
            'wait_time_minutes' => 20.00,
            'expected_yield_percentage' => 99.00,
            'labor_cost_rate' => 45.00,
            'machine_cost_rate' => 85.00,
            'instructions' => 'Align frame on boring table. Line bore both main bearing journals simultaneously. Check bore size with micrometers.',
            'quality_required' => true,
            // OVERLAP FEATURE ENABLED
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 1.0,
            'transfer_lag_minutes' => 30,
        ]);

        // Operation 40: Pitman & Bearing Press Fit Assembly
        $opC40 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 40,
            'operation_number' => 'OP-040',
            'name' => 'Pitman & Shaft Assembly Hydraulic Press Fitting',
            'description' => 'Pressing SKF roller bearings and mounting swing jaw pitman onto eccentric shaft.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcPitmanBay->id,
            'machine_id' => $machPress->id,
            'setup_time_minutes' => 45.00,
            'processing_time_minutes' => 150.00,
            'wait_time_minutes' => 15.00,
            'expected_yield_percentage' => 99.50,
            'labor_cost_rate' => 38.00,
            'machine_cost_rate' => 65.00,
            'instructions' => 'Hydraulic press SKF bearings onto eccentric shaft. Torque bearing cap bolts to 650 Nm.',
            'quality_required' => true,
            'overlap_enabled' => false,
        ]);

        // Operation 50: Crusher Main Frame Erection & Motor Integration
        $opC50 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 50,
            'operation_number' => 'OP-050',
            'name' => 'Crusher Main Erection, Pitman, Jaws & Motor Integration',
            'description' => 'Overhead crane lift of pitman assembly into frame, fitting Mn18Cr2 fixed & swing jaw plates, toggle system, and 75 HP motor.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcCrusherLine->id,
            'machine_id' => $machCrane->id,
            'setup_time_minutes' => 60.00,
            'processing_time_minutes' => 300.00,
            'wait_time_minutes' => 30.00,
            'expected_yield_percentage' => 99.50,
            'labor_cost_rate' => 42.00,
            'machine_cost_rate' => 75.00,
            'instructions' => 'Lower pitman assembly into frame using 30-ton crane. Bolt fixed jaw plate and swing jaw plate with wedge blocks. Mount 75 HP motor and V-belts. Set CSS (Closed Side Setting) to 50mm.',
            'quality_required' => true,
            // OVERLAP FEATURE ENABLED
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 1.0,
            'transfer_lag_minutes' => 60,
        ]);

        // Operation 60: Industrial Shot Blasting & Polyurethane Painting
        $opC60 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 60,
            'operation_number' => 'OP-060',
            'name' => 'Shot Blasting & Industrial Protective Coating',
            'description' => 'Surface shot blasting to SA 2.5 finish and application of high-durability epoxy primer and yellow polyurethane topcoat.',
            'operation_type' => RoutingOperation::TYPE_MANUFACTURING,
            'work_center_id' => $wcBlastSpray->id,
            'machine_id' => $machBlastSpray->id,
            'setup_time_minutes' => 30.00,
            'processing_time_minutes' => 120.00,
            'wait_time_minutes' => 60.00,
            'expected_yield_percentage' => 99.00,
            'labor_cost_rate' => 32.00,
            'machine_cost_rate' => 50.00,
            'instructions' => 'Shot blast frame exterior to SA 2.5 standard. Mask machined journal surfaces. Apply 80 microns epoxy primer + 100 microns industrial polyurethane finish.',
            'quality_required' => false,
            'overlap_enabled' => false,
        ]);

        // Operation 70: Dynamic Vibration & No-Load Performance Testing
        $opC70 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'sequence' => 70,
            'operation_number' => 'OP-070',
            'name' => 'Dynamic Vibration & No-Load Performance QA Test',
            'description' => '2-hour no-load mechanical run-in, bearing temperature monitoring, vibration analysis, and final inspection.',
            'operation_type' => RoutingOperation::TYPE_INSPECTION,
            'work_center_id' => $wcQaTesting->id,
            'machine_id' => $machQaRig->id,
            'setup_time_minutes' => 20.00,
            'processing_time_minutes' => 90.00,
            'wait_time_minutes' => 10.00,
            'expected_yield_percentage' => 100.00,
            'labor_cost_rate' => 45.00,
            'machine_cost_rate' => 60.00,
            'instructions' => 'Connect 75 HP motor to test dock power. Run crusher continuously for 120 minutes. Verify bearing temp remains under 65C. Check vibration amplitude (<2.5 mm/s). Sign off quality certificate.',
            'quality_required' => true,
            'overlap_enabled' => false,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $rtCrusher->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Master Jaw Crusher (30"x20") routing approved with overlap features.',
        ]);

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 4: BILLS OF MATERIALS - BOMS (Multi-Level Hierarchy)
        // ═════════════════════════════════════════════════════════════════════════

        // ── 4.1 Sub-BOM 1: Heavy Fabricated Steel Crusher Frame Chassis ─────────
        $bomFrame = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-CRUSHER-FRAME',
            'bom_name' => 'Heavy Crusher Frame Chassis Sub-BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $sfFrame->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $pcs->id,
            'version' => '1.0.0',
            'revision' => 0,
            'routing_id' => $rtFrame->id,
            'effective_date' => Carbon::now()->subMonths(3),
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
            'notes' => 'Structural steel frame sub-assembly for 30"x20" Jaw Crusher.',
        ]);

        if ($rmSteel40) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomFrame->id,
                'material_id' => $rmSteel40->id,
                'quantity' => 1200.00,
                'uom_id' => $kgs->id,
                'sequence' => 10,
                'material_scrap_percentage' => 4.0,
                'notes' => 'Heavy 40mm side plates cut to profile',
            ]);
        }
        if ($rmSteel25) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomFrame->id,
                'material_id' => $rmSteel25->id,
                'quantity' => 800.00,
                'uom_id' => $kgs->id,
                'sequence' => 20,
                'material_scrap_percentage' => 3.0,
                'notes' => '25mm structural MS internal ribbing',
            ]);
        }
        if ($rmChnl300) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomFrame->id,
                'material_id' => $rmChnl300->id,
                'quantity' => 450.00,
                'uom_id' => $kgs->id,
                'sequence' => 30,
                'material_scrap_percentage' => 2.5,
                'notes' => 'ISMC 300 channel cross braces',
            ]);
        }

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomFrame->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Crusher frame chassis BOM approved.',
        ]);

        // ── 4.2 Sub-BOM 2: Pitman & Eccentric Shaft Bearing Sub-Assembly ────────
        $bomPitman = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-PITMAN-BEARING-ASSY',
            'bom_name' => 'Pitman & Shaft Bearing Sub-Assembly BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $sfPitman->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $pcs->id,
            'version' => '1.0.0',
            'revision' => 0,
            'routing_id' => $rtPitman->id,
            'effective_date' => Carbon::now()->subMonths(3),
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
            'notes' => 'Precision Pitman assembly with SKF heavy bearings and 42CrMo4 shaft.',
        ]);

        if ($rmShaft) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomPitman->id,
                'material_id' => $rmShaft->id,
                'quantity' => 1.0,
                'uom_id' => $pcs->id,
                'sequence' => 10,
                'notes' => '42CrMo4 forged steel eccentric shaft',
            ]);
        }
        if ($rmBearing) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomPitman->id,
                'material_id' => $rmBearing->id,
                'quantity' => 2.0,
                'uom_id' => $pcs->id,
                'sequence' => 20,
                'notes' => 'SKF 22330 spherical heavy roller bearings',
            ]);
        }
        if ($rmSteel25) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomPitman->id,
                'material_id' => $rmSteel25->id,
                'quantity' => 350.00,
                'uom_id' => $kgs->id,
                'sequence' => 30,
                'material_scrap_percentage' => 3.0,
                'notes' => 'Cast steel Pitman body housing material',
            ]);
        }

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomPitman->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Pitman sub-assembly BOM approved.',
        ]);

        // ── 4.3 Sub-BOM 3: Toggle Plate & Tension Spring Safety Assembly ────────
        $bomToggle = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-TOGGLE-SAFETY-SET',
            'bom_name' => 'Toggle Plate & Tension Safety Assembly BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $sfToggle->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $sets->id,
            'version' => '1.0.0',
            'revision' => 0,
            'routing_id' => $rtToggle->id,
            'effective_date' => Carbon::now()->subMonths(3),
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
            'notes' => 'Toggle plate and mechanical overload release assembly.',
        ]);

        if ($rmSteel25) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomToggle->id,
                'material_id' => $rmSteel25->id,
                'quantity' => 150.00,
                'uom_id' => $kgs->id,
                'sequence' => 10,
                'notes' => 'High-strength toggle plate blank',
            ]);
        }
        if ($rmChnl300) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomToggle->id,
                'material_id' => $rmChnl300->id,
                'quantity' => 100.00,
                'uom_id' => $kgs->id,
                'sequence' => 20,
                'notes' => 'Tension rod spring guide channel',
            ]);
        }

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomToggle->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Toggle safety set BOM approved.',
        ]);

        // ── 4.4 Master Parent BOM: Heavy Duty Stone Jaw Crusher (30"x20") ─────────
        $bomCrusher = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-CRUSHER-JAW-3020',
            'bom_name' => 'Industrial Stone Jaw Crusher (30" x 20") Master BOM',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $fgCrusher->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $pcs->id,
            'version' => '1.0.0',
            'revision' => 0,
            'routing_id' => $rtCrusher->id,
            'effective_date' => Carbon::now()->subMonths(3),
            'status' => 'approved',
            'created_by' => $userId,
            'approved_by' => $userId,
            'approved_at' => Carbon::now()->subMonths(2),
            'notes' => 'Top-level master manufacturing configuration for 30"x20" Heavy Stone Jaw Crusher.',
        ]);

        // Multi-level component 1: Crusher Frame Chassis (linked to Sub-BOM)
        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomCrusher->id,
            'material_id' => $sfFrame->id,
            'child_bom_id' => $bomFrame->id,
            'quantity' => 1.0,
            'uom_id' => $pcs->id,
            'sequence' => 10,
            'notes' => 'Heavy Fabricated Steel Crusher Frame Chassis Sub-Assembly',
        ]);

        // Multi-level component 2: Pitman & Bearing Sub-Assembly (linked to Sub-BOM)
        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomCrusher->id,
            'material_id' => $sfPitman->id,
            'child_bom_id' => $bomPitman->id,
            'quantity' => 1.0,
            'uom_id' => $pcs->id,
            'sequence' => 20,
            'notes' => 'Pitman & Eccentric Shaft Bearing Sub-Assembly',
        ]);

        // Multi-level component 3: Toggle Safety Assembly (linked to Sub-BOM)
        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomCrusher->id,
            'material_id' => $sfToggle->id,
            'child_bom_id' => $bomToggle->id,
            'quantity' => 1.0,
            'uom_id' => $sets->id,
            'sequence' => 30,
            'notes' => 'Toggle Plate & Tension Spring Safety Assembly',
        ]);

        // Component 4: High Manganese Fixed Jaw Plate
        if ($rmJawFixed) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomCrusher->id,
                'material_id' => $rmJawFixed->id,
                'quantity' => 1.0,
                'uom_id' => $pcs->id,
                'sequence' => 40,
                'notes' => 'Mn18Cr2 High Manganese Fixed Jaw Liner Plate',
            ]);
        }

        // Component 5: High Manganese Swing Jaw Plate
        if ($rmJawMov) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomCrusher->id,
                'material_id' => $rmJawMov->id,
                'quantity' => 1.0,
                'uom_id' => $pcs->id,
                'sequence' => 50,
                'notes' => 'Mn18Cr2 High Manganese Swing Jaw Liner Plate',
            ]);
        }

        // Component 6: 75 HP Electric Motor
        if ($rmMotor) {
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomCrusher->id,
                'material_id' => $rmMotor->id,
                'quantity' => 1.0,
                'uom_id' => $pcs->id,
                'sequence' => 60,
                'notes' => '75 HP 3-Phase IE3 Heavy Duty Induction Drive Motor',
            ]);
        }

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomCrusher->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Master Jaw Crusher BOM approved.',
        ]);

        $this->command->info('Crusher Machine Production Planning data (Work Centers, Machines, BOMs, Routings with Overlap) seeded successfully!');
    }
}
