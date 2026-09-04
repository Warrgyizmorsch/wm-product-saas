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
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PoonaRadiatorsProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Step 0: Clean previous production scenario data ──────────────────
        Schema::disableForeignKeyConstraints();

        DB::table('production_wip_transactions')->truncate();
        DB::table('production_wips')->truncate();
        DB::table('production_cost_adjustments')->truncate();
        DB::table('production_event_timelines')->truncate();
        DB::table('production_scrap_disposals')->truncate();
        DB::table('production_order_scraps')->truncate();
        DB::table('production_rework_operations')->truncate();
        DB::table('production_rework_orders')->truncate();
        DB::table('production_order_reworks')->truncate();
        DB::table('production_capas')->truncate();
        DB::table('production_ncrs')->truncate();
        DB::table('production_deviations')->truncate();
        DB::table('production_quality_inspection_results')->truncate();
        DB::table('production_quality_inspections')->truncate();
        DB::table('production_quality_plan_parameters')->truncate();
        DB::table('production_quality_plans')->truncate();
        DB::table('production_scan_logs')->truncate();
        DB::table('production_serial_numbers')->truncate();
        DB::table('production_lot_traces')->truncate();
        DB::table('production_batch_genealogies')->truncate();
        DB::table('production_batches')->truncate();
        DB::table('production_operator_assignment_logs')->truncate();
        DB::table('production_operator_assignments')->truncate();
        DB::table('production_operator_skills')->truncate();
        DB::table('production_schedule_operations')->truncate();
        DB::table('production_schedules')->truncate();
        DB::table('production_requisition_slip_items')->truncate();
        DB::table('production_requisition_slips')->truncate();
        DB::table('purchase_requisition_items')->truncate();
        DB::table('purchase_requisitions')->truncate();
        DB::table('production_order_requests')->truncate();
        DB::table('production_order_issue_batches')->truncate();
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

        // ─── Step 1: Resolve Tenant & Admin User ──────────────────────────────
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();
        if (!$tenant) {
            $this->command->warn('No tenant found for Poona Radiators Production seeding.');
            return;
        }

        $adminUser = User::where('tenant_id', $tenant->id)->where('email', 'admin@example.com')->first() 
            ?? User::where('tenant_id', $tenant->id)->first() 
            ?? User::first();
        $userId = $adminUser ? $adminUser->id : 1;

        // ─── Step 2: Resolve UOMs & Products from PoonaRadiatorsProductSeeder ─
        $pcs = Uom::where('tenant_id', $tenant->id)->where('code', 'Pcs')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Pcs'], ['name' => 'Pieces']);
        $kg  = Uom::where('tenant_id', $tenant->id)->where('code', 'Kg')->first()  ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Kg'], ['name' => 'Kilograms']);
        $mtr = Uom::where('tenant_id', $tenant->id)->where('code', 'Mtr')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Mtr'], ['name' => 'Meters']);

        $rmFin    = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-RM-ALU-FIN-01')->first();
        $rmTube   = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-RM-ALU-TUBE-F')->first();
        $rmPlate  = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-RM-TANK-PLATE')->first();
        $rmNozzle = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-RM-NOZZLE-45')->first();
        $rmFlux   = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-RM-BRAZE-FLUX')->first();

        $subCore  = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-SUB-CORE-750')->first();
        $fgRad    = Product::where('tenant_id', $tenant->id)->where('sku', 'PR-FG-RAD-750')->first();

        if (!$fgRad || !$subCore || !$rmFin || !$rmTube) {
            $this->command->error('Poona Radiators products missing! Ensure PoonaRadiatorsProductSeeder runs first.');
            return;
        }

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 1: SHIFTS & CALENDARS
        // ═════════════════════════════════════════════════════════════════════════
        $shiftDay = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'name' => 'Day Shift (Morning)',
            'code' => 'SHIFT-DAY',
            'start_time' => '08:00:00',
            'end_time' => '16:30:00',
            'break_minutes' => 30,
            'overtime_allowed' => true,
            'active' => true,
        ]);

        $shiftEve = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'name' => 'Evening Shift',
            'code' => 'SHIFT-EVE',
            'start_time' => '16:30:00',
            'end_time' => '01:00:00',
            'break_minutes' => 30,
            'overtime_allowed' => true,
            'active' => true,
        ]);

        $shiftNight = ProductionShift::create([
            'tenant_id' => $tenant->id,
            'name' => 'Night Shift',
            'code' => 'SHIFT-NIGHT',
            'start_time' => '01:00:00',
            'end_time' => '08:00:00',
            'break_minutes' => 30,
            'overtime_allowed' => true,
            'active' => true,
        ]);

        $calendar = ProductionCalendar::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard 6-Day Radiator Plant Calendar',
            'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'is_default' => true,
        ]);

        ProductionCalendarHoliday::create([
            'tenant_id' => $tenant->id,
            'production_calendar_id' => $calendar->id,
            'name' => 'Plant Maintenance Day',
            'holiday_date' => Carbon::now()->addDays(15)->toDateString(),
            'holiday_type' => 'company',
            'description' => 'Scheduled CAB Furnace Maintenance Day',
            'is_full_day' => true,
            'active' => true,
        ]);

        ProductionCalendarHoliday::create([
            'tenant_id' => $tenant->id,
            'production_calendar_id' => $calendar->id,
            'name' => 'National Holiday',
            'holiday_date' => Carbon::now()->addMonth()->toDateString(),
            'holiday_type' => 'national',
            'description' => 'National Festival Holiday',
            'is_full_day' => true,
            'active' => true,
        ]);

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 2: WORK CENTERS & SECTIONS
        // ═════════════════════════════════════════════════════════════════════════
        // Department: Heat Exchanger & Radiator Plant
        $deptPlant = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Heat Exchanger & Radiator Manufacturing Plant',
            'code' => 'DEPT-HEAT-EXCHANGER',
            'type' => 'department',
            'work_center_type' => 'machining',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Main Hall',
            'capacity_per_hour' => 25.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 120.00,
            'overhead_rate' => 45.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        // Section 1: Tube & Fin Matrix Section
        $secFinTube = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Fin Corrugation & Tube Forming Section',
            'code' => 'SEC-FIN-TUBE',
            'type' => 'section',
            'work_center_type' => 'machining',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Bay A',
            'capacity_per_hour' => 15.00,
            'efficiency_percentage' => 94.00,
            'cost_per_hour' => 95.00,
            'overhead_rate' => 35.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $wcFinTube = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'CNC Fin Corrugation & Tube Mill Center',
            'code' => 'WC-FIN-TUBE',
            'type' => 'work_center',
            'work_center_type' => 'machining',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Station A1',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 110.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $secFinTube->id,
        ]);

        // Section 2: Core Stacking & CAB Brazing Section
        $secCoreCab = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Core Matrix Assembly & CAB Brazing Section',
            'code' => 'SEC-CORE-CAB',
            'type' => 'section',
            'work_center_type' => 'assembly',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Bay B',
            'capacity_per_hour' => 12.00,
            'efficiency_percentage' => 96.00,
            'cost_per_hour' => 140.00,
            'overhead_rate' => 50.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $wcCoreCab = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Core Stacking & Nocolok CAB Furnace Center',
            'code' => 'WC-CORE-CAB',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Station B1',
            'capacity_per_hour' => 8.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 160.00,
            'overhead_rate' => 60.00,
            'status' => 'active',
            'parent_id' => $secCoreCab->id,
        ]);

        // Section 3: Header Tank Fitting & Welding Section
        $secTankCrimp = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Header Tank Fitting & Welding Section',
            'code' => 'SEC-TANK-CRIMP',
            'type' => 'section',
            'work_center_type' => 'assembly',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Bay C',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 93.00,
            'cost_per_hour' => 100.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $wcTankCrimp = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Header Tank Crimping & TIG Welding Center',
            'code' => 'WC-TANK-CRIMP',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Station C1',
            'capacity_per_hour' => 6.00,
            'efficiency_percentage' => 94.00,
            'cost_per_hour' => 115.00,
            'overhead_rate' => 45.00,
            'status' => 'active',
            'parent_id' => $secTankCrimp->id,
        ]);

        // Section 4: Testing & Coating Section
        $secTestCoat = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Testing & Protective Coating Section',
            'code' => 'SEC-TEST-COAT',
            'type' => 'section',
            'work_center_type' => 'inspection',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Bay D',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 98.00,
            'cost_per_hour' => 90.00,
            'overhead_rate' => 35.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $wcTestCoat = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Hydrostatic Leak Test & Powder Coating Center',
            'code' => 'WC-TEST-COAT',
            'type' => 'work_center',
            'work_center_type' => 'inspection',
            'department_name' => 'Radiator Manufacturing',
            'location' => 'Chakan Plant - Station D1',
            'capacity_per_hour' => 5.00,
            'efficiency_percentage' => 98.00,
            'cost_per_hour' => 105.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $secTestCoat->id,
        ]);

        // Attach shifts to work centers
        foreach ([$wcFinTube, $wcCoreCab, $wcTankCrimp, $wcTestCoat] as $wcItem) {
            foreach ([$shiftDay, $shiftEve] as $sfItem) {
                DB::table('production_work_center_shifts')->insertOrIgnore([
                    'tenant_id' => $tenant->id,
                    'work_center_id' => $wcItem->id,
                    'shift_id' => $sfItem->id,
                ]);
            }
        }

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 3: MACHINES
        // ═════════════════════════════════════════════════════════════════════════
        $mcFinRoll = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcFinTube->id,
            'name' => 'High-Speed CNC Fin Roller Machine',
            'code' => 'MC-FIN-ROLL-01',
            'machine_type' => 'Fin Corrugation Roller',
            'manufacturer' => 'Schoeler Systems GmbH',
            'model_number' => 'FR-800-PRO',
            'capacity' => 10.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcTubeMill = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcFinTube->id,
            'name' => 'Automated Aluminum Tube Mill & Seam Welder',
            'code' => 'MC-TUBE-MILL-01',
            'machine_type' => 'Tube Mill',
            'manufacturer' => 'Schöler GmbH',
            'model_number' => 'TM-250-ALU',
            'capacity' => 12.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcCoreStack = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcCoreCab->id,
            'name' => 'Servo-Driven Core Matrix Stacking Machine',
            'code' => 'MC-CORE-STACK-01',
            'machine_type' => 'Core Builder Stacker',
            'manufacturer' => 'Livernois Engineering',
            'model_number' => 'CB-750-AUTO',
            'capacity' => 8.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcCabFurnace = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcCoreCab->id,
            'name' => 'Continuous Nocolok Controlled Atmosphere Brazing Furnace',
            'code' => 'MC-CAB-FURNACE-01',
            'machine_type' => 'CAB Furnace',
            'manufacturer' => 'Seco/Warwick',
            'model_number' => 'Active-CAB-1200',
            'capacity' => 6.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcCrimpPress = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcTankCrimp->id,
            'name' => 'Pneumatic Radiator Header Crimping Press',
            'code' => 'MC-CRIMP-PRESS-01',
            'machine_type' => 'Crimping Press',
            'manufacturer' => 'Poona Automation Tech',
            'model_number' => 'CP-500-HD',
            'capacity' => 10.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcTigWeld = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcTankCrimp->id,
            'name' => 'Multi-Axis TIG Welding Station for Brass Nozzles',
            'code' => 'MC-TIG-WELD-01',
            'machine_type' => 'TIG Welder',
            'manufacturer' => 'Fronius',
            'model_number' => 'MagicWave 3000',
            'capacity' => 8.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcLeakTest = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcTestCoat->id,
            'name' => 'Automated Water Submersion Pressure Testing Rig',
            'code' => 'MC-LEAK-TEST-01',
            'machine_type' => 'Hydrostatic Leak Tester',
            'manufacturer' => 'Atek Hydro Tech',
            'model_number' => 'HT-60-BAR',
            'capacity' => 6.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $mcPowderCoat = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcTestCoat->id,
            'name' => 'Electrostatic Powder Coating Oven',
            'code' => 'MC-POWDER-COAT-01',
            'machine_type' => 'Powder Coating Line',
            'manufacturer' => 'Nordson',
            'model_number' => 'Encore-HD-200',
            'capacity' => 8.0,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 4: OPERATOR SKILLS FOR DEMO ADMIN (UNRESTRICTED ACCESS)
        // ═════════════════════════════════════════════════════════════════════════
        $allWorkCenters = [$wcFinTube, $wcCoreCab, $wcTankCrimp, $wcTestCoat];
        $allMachines    = [$mcFinRoll, $mcTubeMill, $mcCoreStack, $mcCabFurnace, $mcCrimpPress, $mcTigWeld, $mcLeakTest, $mcPowderCoat];

        foreach ($allWorkCenters as $wc) {
            ProductionOperatorSkill::create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'skill_code' => 'SKILL-WC-' . $wc->code,
                'work_center_id' => $wc->id,
                'machine_id' => null,
                'active' => true,
            ]);
        }

        foreach ($allMachines as $mc) {
            ProductionOperatorSkill::create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'skill_code' => 'SKILL-MC-' . $mc->code,
                'work_center_id' => $mc->work_center_id,
                'machine_id' => $mc->id,
                'active' => true,
            ]);
        }

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 5: BILL OF MATERIALS (BOM)
        // ═════════════════════════════════════════════════════════════════════════
        // ── LEVEL 1 BOM: Radiator Core Sub-Assembly ──
        $coreBom = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-SUB-CORE-01',
            'bom_name' => 'BOM - Radiator Aluminum Core Sub-Assembly',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $subCore->id,
            'base_quantity' => 1.00,
            'base_uom_id' => $pcs->id,
            'version' => '1.0',
            'revision' => 0,
            'revision_reason' => 'Standard OEM Production Release',
            'effective_date' => now(),
            'status' => 'approved',
            'notes' => 'Primary furnace brazing BOM for aluminum cooling core matrix.',
            'created_by' => $userId,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $coreBom->id,
            'material_id' => $rmFin->id,
            'quantity' => 8.50,
            'uom_id' => $kg->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 1,
            'notes' => 'Corrugated cooling fin matrix requirement.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $coreBom->id,
            'material_id' => $rmTube->id,
            'quantity' => 32.00,
            'uom_id' => $mtr->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 2,
            'notes' => 'Flat oval coolant tube extrusions.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $coreBom->id,
            'material_id' => $rmFlux->id,
            'quantity' => 1.20,
            'uom_id' => $kg->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 3,
            'notes' => 'Nocolok flux powder for furnace brazing.',
        ]);

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $coreBom->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Approved for mass production.',
        ]);

        // ── LEVEL 2 BOM: Heavy Duty Finished Radiator Assembly ──
        $fgBom = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-FG-RAD-750',
            'bom_name' => 'BOM - Heavy Duty Aluminum Radiator Assembly (PR-RAD-750)',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $fgRad->id,
            'base_quantity' => 1.00,
            'base_uom_id' => $pcs->id,
            'version' => '1.0',
            'revision' => 0,
            'revision_reason' => 'Final Finished Good Assembly BOM for 500 kVA Genset/Commercial OEM',
            'effective_date' => now(),
            'status' => 'approved',
            'notes' => 'Final assembly BOM including core, header plates, and brass inlet/outlet nozzles.',
            'created_by' => $userId,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $subCore->id,
            'child_bom_id' => $coreBom->id,
            'quantity' => 1.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 1,
            'notes' => 'Brazed core sub-assembly block.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $rmPlate->id,
            'quantity' => 2.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 2,
            'notes' => 'Top and bottom aluminum header tank plates.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $rmNozzle->id,
            'quantity' => 2.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 3,
            'notes' => 'Brass inlet/outlet hose nozzles.',
        ]);

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $fgBom->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Approved for OEM assembly line.',
        ]);

        // ═════════════════════════════════════════════════════════════════════════
        //  MODULE 6: ROUTINGS & ROUTING OPERATIONS
        // ═════════════════════════════════════════════════════════════════════════
        // Routing 1: Core Sub-Assembly Routing
        $routCore = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'ROUT-SUB-CORE-750',
            'name' => 'Routing - Radiator Core Sub-Assembly Furnace Brazing',
            'product_id' => $subCore->id,
            'version' => '1.0',
            'status' => Routing::STATUS_ACTIVE,
            'description' => 'Continuous fin-tube processing and CAB furnace brazing line routing.',
            'created_by' => $userId,
        ]);

        $opCore10 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routCore->id,
            'work_center_id' => $wcFinTube->id,
            'machine_id' => $mcFinRoll->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Fin Corrugation & Tube Milling',
            'description' => 'High speed roll corrugation of aluminum fin foil & precision tube seam welding.',
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 12.0,
            'labor_cost_rate' => round($wcFinTube->cost_per_hour / 60, 4),
            'machine_cost_rate' => 1.8333,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 10,
        ]);

        $opCore20 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routCore->id,
            'work_center_id' => $wcCoreCab->id,
            'machine_id' => $mcCoreStack->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Core Matrix Stacking & Nocolok Fluxing',
            'description' => 'Interleaving tubes and fins into core matrix frame and electrostatic flux slurry spraying.',
            'setup_time_minutes' => 20.0,
            'processing_time_minutes' => 15.0,
            'labor_cost_rate' => round($wcCoreCab->cost_per_hour / 60, 4),
            'machine_cost_rate' => 2.6667,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 15,
        ]);

        $opCore30 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routCore->id,
            'work_center_id' => $wcCoreCab->id,
            'machine_id' => $mcCabFurnace->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'CAB Furnace Continuous Brazing',
            'description' => 'Controlled atmosphere nitrogen furnace brazing at 600°C for leak-proof metallic bond.',
            'setup_time_minutes' => 45.0,
            'processing_time_minutes' => 25.0,
            'labor_cost_rate' => round($wcCoreCab->cost_per_hour / 60, 4),
            'machine_cost_rate' => 3.0000,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 20,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routCore->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Core routing verified for shop floor execution.',
        ]);

        // Link core routing to core BOM
        $coreBom->update(['routing_id' => $routCore->id]);

        // Routing 2: Finished Radiator Assembly Routing
        $routFg = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'ROUT-FG-RAD-750',
            'name' => 'Routing - Heavy Duty Radiator Assembly & Testing Line',
            'product_id' => $fgRad->id,
            'version' => '1.0',
            'status' => Routing::STATUS_ACTIVE,
            'description' => 'Tank crimping, TIG nozzle welding, pressure leak testing, and powder coating routing.',
            'created_by' => $userId,
        ]);

        $opFg10 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routFg->id,
            'work_center_id' => $wcTankCrimp->id,
            'machine_id' => $mcCrimpPress->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Header Plate Slot Fitting & Tank Placement',
            'description' => 'Fitting aluminum header plates onto brazed core ends with high precision rubber gaskets.',
            'setup_time_minutes' => 15.0,
            'processing_time_minutes' => 10.0,
            'labor_cost_rate' => round($wcTankCrimp->cost_per_hour / 60, 4),
            'machine_cost_rate' => 1.9167,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 5,
        ]);

        RoutingOperationMaterial::create([
            'tenant_id' => $tenant->id,
            'routing_operation_id' => $opFg10->id,
            'material_id' => $subCore->id,
            'quantity' => 1.00,
            'uom_id' => $pcs->id,
        ]);

        $opFg20 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routFg->id,
            'work_center_id' => $wcTankCrimp->id,
            'machine_id' => $mcTigWeld->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Tank Header Crimping & TIG Nozzle Welding',
            'description' => 'Pneumatic mechanical crimping of header tank tabs & TIG welding brass coolant hose nipples.',
            'setup_time_minutes' => 20.0,
            'processing_time_minutes' => 18.0,
            'labor_cost_rate' => round($wcTankCrimp->cost_per_hour / 60, 4),
            'machine_cost_rate' => 2.0833,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 10,
        ]);

        $opFg30 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routFg->id,
            'work_center_id' => $wcTestCoat->id,
            'machine_id' => $mcLeakTest->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Hydrostatic Pressure Leak Test & Powder Coating',
            'description' => '60-Bar underwater pneumatic leak testing followed by electrostatic powder coating cure.',
            'setup_time_minutes' => 25.0,
            'processing_time_minutes' => 15.0,
            'labor_cost_rate' => round($wcTestCoat->cost_per_hour / 60, 4),
            'machine_cost_rate' => 1.7500,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routFg->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Finished good assembly routing approved for OEM orders.',
        ]);

        // Link finished good routing to FG BOM
        $fgBom->update(['routing_id' => $routFg->id]);
    }
}
