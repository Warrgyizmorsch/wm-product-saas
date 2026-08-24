<?php

namespace Database\Seeders;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionCalendar;
use App\Domains\Production\Models\ProductionCalendarHoliday;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionOrderScrap;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityInspectionResult;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
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

class FurnitureProductionSeeder extends Seeder
{
    /**
     * Run the database seeds for Wooden & Upholstered Furniture manufacturing demo production flow.
     */
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
            $this->command->warn('No tenant found for Furniture Production seeding.');
            return;
        }

        $adminUser = User::where('tenant_id', $tenant->id)->where('email', 'admin@example.com')->first() 
            ?? User::where('tenant_id', $tenant->id)->first() 
            ?? User::first();
        $userId = $adminUser ? $adminUser->id : 1;

        // Resolve UOMs
        $pcs = Uom::where('tenant_id', $tenant->id)->where('code', 'Pcs')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Pcs'], ['name' => 'Pieces']);
        $kg  = Uom::where('tenant_id', $tenant->id)->where('code', 'Kg')->first()  ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Kg'], ['name' => 'Kilograms']);
        $mtr = Uom::where('tenant_id', $tenant->id)->where('code', 'Mtr')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Mtr'], ['name' => 'Meters']);
        $ltr = Uom::where('tenant_id', $tenant->id)->where('code', 'Ltr')->first() ?? Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'Ltr'], ['name' => 'Liters']);

        // Resolve Products
        $rmTimber   = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-TIMBER-01')->first();
        $rmVeneer   = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-VENEER-01')->first();
        $rmHardware = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-HARDWARE-01')->first();
        $rmLacquer  = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-LACQUER-01')->first();
        $rmFoam     = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-FOAM-01')->first();
        $rmFabric   = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-FABRIC-01')->first();
        $rmGlue     = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-RM-GLUE-01')->first();

        $subLegFrame    = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-SUB-LEG-FRAME')->first();
        $subCushionSeat = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-SUB-CUSHION-SEAT')->first();

        $fgChair = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-FG-CHAIR-EXEC')->first();
        $fgTable = Product::where('tenant_id', $tenant->id)->where('sku', 'FURN-FG-TABLE-LUX')->first();

        if (!$fgChair || !$fgTable || !$subLegFrame || !$rmTimber) {
            $this->command->error('Furniture products missing! Please run FurnitureProductSeeder first.');
            return;
        }

        // ─── Step 2: Seed Shifts & Calendars ─────────────────────────────────
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

        $calendar = ProductionCalendar::create([
            'tenant_id' => $tenant->id,
            'name' => 'Standard 6-Day Furniture Plant Calendar',
            'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'is_default' => true,
        ]);

        ProductionCalendarHoliday::create([
            'tenant_id' => $tenant->id,
            'production_calendar_id' => $calendar->id,
            'name' => 'Plant Maintenance Day',
            'holiday_date' => Carbon::now()->addDays(15)->toDateString(),
            'holiday_type' => 'company',
            'description' => 'Scheduled CNC Machine & Spray Booth Maintenance Day',
            'is_full_day' => true,
            'active' => true,
        ]);

        // ─── Step 3: Seed Work Centers & Machines ─────────────────────────────
        $deptPlant = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Furniture Manufacturing & Assembly Plant',
            'code' => 'DEPT-FURN-PLANT',
            'type' => 'department',
            'work_center_type' => 'assembly',
            'department_name' => 'Furniture Manufacturing',
            'location' => 'Furniture Park - Main Factory',
            'capacity_per_hour' => 20.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 150.00,
            'overhead_rate' => 50.00,
            'status' => 'active',
            'parent_id' => null,
        ]);

        $wcCutting = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wood Cutting & Sizing Work Center',
            'code' => 'WC-CUT-SIZE',
            'type' => 'work_center',
            'work_center_type' => 'machining',
            'department_name' => 'Furniture Manufacturing',
            'location' => 'Main Factory - Bay A',
            'capacity_per_hour' => 12.00,
            'efficiency_percentage' => 95.00,
            'cost_per_hour' => 450.00,
            'overhead_rate' => 60.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $mcCutting = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcCutting->id,
            'name' => '5-Axis CNC Wood Router & Sizing Saw',
            'code' => 'MC-CNC-ROUTER-01',
            'machine_type' => 'CNC Wood Router',
            'manufacturer' => 'Homag Woodworking Machinery Ltd.',
            'model_number' => 'CENTATEQ P-110',
            'capacity' => 12.00,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $wcSanding = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Wood Shaping & Sanding Work Center',
            'code' => 'WC-SHAPE-SAND',
            'type' => 'work_center',
            'work_center_type' => 'machining',
            'department_name' => 'Furniture Manufacturing',
            'location' => 'Main Factory - Bay B',
            'capacity_per_hour' => 15.00,
            'efficiency_percentage' => 92.00,
            'cost_per_hour' => 350.00,
            'overhead_rate' => 40.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $mcSanding = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcSanding->id,
            'name' => 'Automated Triple-Head Belt Sander Machine',
            'code' => 'MC-BELT-SANDER-01',
            'machine_type' => 'Belt Sander',
            'manufacturer' => 'Timesavers Wood Equipment',
            'model_number' => 'SERIES-3300',
            'capacity' => 15.00,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $wcFinishing = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Surface Coating & Spray Painting Work Center',
            'code' => 'WC-COAT-FINISH',
            'type' => 'work_center',
            'work_center_type' => 'finishing',
            'department_name' => 'Furniture Manufacturing',
            'location' => 'Main Factory - Spray Wing',
            'capacity_per_hour' => 8.00,
            'efficiency_percentage' => 90.00,
            'cost_per_hour' => 550.00,
            'overhead_rate' => 75.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $mcFinishing = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcFinishing->id,
            'name' => 'Cleanroom Spray Painting Booth & IR Oven',
            'code' => 'MC-SPRAY-BOOTH-01',
            'machine_type' => 'Spray Booth',
            'manufacturer' => 'Cefla Finishing Systems',
            'model_number' => 'EASY-SPRAY-PRO',
            'capacity' => 8.00,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        $wcAssembly = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name' => 'Furniture Assembly & Upholstery Work Center',
            'code' => 'WC-FURN-ASSY',
            'type' => 'work_center',
            'work_center_type' => 'assembly',
            'department_name' => 'Furniture Manufacturing',
            'location' => 'Main Factory - Bay C',
            'capacity_per_hour' => 10.00,
            'efficiency_percentage' => 98.00,
            'cost_per_hour' => 300.00,
            'overhead_rate' => 35.00,
            'status' => 'active',
            'parent_id' => $deptPlant->id,
        ]);

        $mcAssembly = Machine::create([
            'tenant_id' => $tenant->id,
            'work_center_id' => $wcAssembly->id,
            'name' => 'Hydraulic Furniture Assembly Clamp Press',
            'code' => 'MC-ASSY-PRESS-01',
            'machine_type' => 'Assembly Press',
            'manufacturer' => 'Steton Furniture Press Co.',
            'model_number' => 'FRAME-CLAMP-200',
            'capacity' => 10.00,
            'status' => Machine::STATUS_ACTIVE,
            'current_state' => 'Idle',
        ]);

        // Attach shifts & skills
        $allWorkCenters = [$wcCutting, $wcSanding, $wcFinishing, $wcAssembly];
        $allMachines = [$mcCutting, $mcSanding, $mcFinishing, $mcAssembly];

        foreach ($allWorkCenters as $wcItem) {
            foreach ([$shiftDay, $shiftEve] as $sfItem) {
                DB::table('production_work_center_shifts')->insertOrIgnore([
                    'tenant_id' => $tenant->id,
                    'work_center_id' => $wcItem->id,
                    'shift_id' => $sfItem->id,
                ]);
            }
            ProductionOperatorSkill::create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'skill_code' => 'SKILL-WC-' . $wcItem->code,
                'work_center_id' => $wcItem->id,
                'machine_id' => null,
                'active' => true,
            ]);
        }

        foreach ($allMachines as $mcItem) {
            ProductionOperatorSkill::create([
                'tenant_id' => $tenant->id,
                'user_id' => $userId,
                'skill_code' => 'SKILL-MC-' . $mcItem->code,
                'work_center_id' => $mcItem->work_center_id,
                'machine_id' => $mcItem->id,
                'active' => true,
            ]);
        }

        // ─── Step 4: Seed BOMs ────────────────────────────────────────────────
        // 4A. Sub-Assembly BOM: Chair Leg Frame
        $bomLeg = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-SUB-LEG-FRAME',
            'bom_name' => 'BOM - Chair Leg Frame Sub-Assembly',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $subLegFrame->id,
            'base_quantity' => 1.00,
            'base_uom_id' => $pcs->id,
            'version' => '1.0',
            'revision' => 0,
            'revision_reason' => 'Standard Production Release',
            'effective_date' => now(),
            'status' => 'approved',
            'notes' => 'Mortise-and-tenon teak wood leg structure with PVA adhesive jointing.',
            'created_by' => $userId,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomLeg->id,
            'material_id' => $rmTimber->id,
            'quantity' => 1.80,
            'uom_id' => $mtr->id,
            'material_scrap_percentage' => 5.00,
            'sequence' => 1,
            'notes' => 'Teak timber planks for 4 curved legs & connecting rails.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomLeg->id,
            'material_id' => $rmHardware->id,
            'quantity' => 1.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 2,
            'notes' => 'Concealed SS dowels & connector fasteners.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomLeg->id,
            'material_id' => $rmGlue->id,
            'quantity' => 0.25,
            'uom_id' => $kg->id,
            'material_scrap_percentage' => 2.00,
            'sequence' => 3,
            'notes' => 'D3 PVA wood adhesive resin.',
        ]);

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomLeg->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Standard leg frame sub-BOM verified.',
        ]);

        // 4B. Finished Good BOM: Executive Ergonomic Chair
        $bomChair = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-FG-CHAIR-EXEC',
            'bom_name' => 'BOM - Executive Ergonomic Wooden Armchair (FE-CHR-900)',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $fgChair->id,
            'base_quantity' => 1.00,
            'base_uom_id' => $pcs->id,
            'version' => '1.0',
            'revision' => 0,
            'revision_reason' => 'Standard Production Release',
            'effective_date' => now(),
            'status' => 'approved',
            'notes' => 'Master Bill of Materials for Executive Armchair assembly.',
            'created_by' => $userId,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomChair->id,
            'material_id' => $subLegFrame->id,
            'child_bom_id' => $bomLeg->id,
            'quantity' => 1.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 1,
            'notes' => 'Machined & sanded teak leg frame sub-assembly.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomChair->id,
            'material_id' => $rmFoam->id,
            'quantity' => 1.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 2,
            'notes' => 'HR polyurethane contoured foam cushion.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomChair->id,
            'material_id' => $rmFabric->id,
            'quantity' => 1.50,
            'uom_id' => $mtr->id,
            'material_scrap_percentage' => 5.00,
            'sequence' => 3,
            'notes' => 'Velvet upholstery fabric for seat & backrest wrapping.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomChair->id,
            'material_id' => $rmLacquer->id,
            'quantity' => 0.50,
            'uom_id' => $ltr->id,
            'material_scrap_percentage' => 10.00,
            'sequence' => 4,
            'notes' => '2-Pack PU matte lacquer finish.',
        ]);

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomChair->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Executive Chair master BOM approved.',
        ]);

        // 4C. Finished Good BOM: Luxury Dining Table
        $bomTable = ProductionBom::create([
            'tenant_id' => $tenant->id,
            'bom_number' => 'BOM-FG-TABLE-LUX',
            'bom_name' => 'BOM - Luxury 6-Seater Dining Table (FE-TBL-1800)',
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $fgTable->id,
            'base_quantity' => 1.00,
            'base_uom_id' => $pcs->id,
            'version' => '1.0',
            'revision' => 0,
            'revision_reason' => 'Standard Production Release',
            'effective_date' => now(),
            'status' => 'approved',
            'notes' => 'Master Bill of Materials for 6-seater solid teak & oak dining table.',
            'created_by' => $userId,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomTable->id,
            'material_id' => $rmTimber->id,
            'quantity' => 8.00,
            'uom_id' => $mtr->id,
            'material_scrap_percentage' => 5.00,
            'sequence' => 1,
            'notes' => 'Teak timber planks for table legs & aprons.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomTable->id,
            'material_id' => $rmVeneer->id,
            'quantity' => 2.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 2.00,
            'sequence' => 2,
            'notes' => 'Crown-cut American Oak veneer top panel.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomTable->id,
            'material_id' => $rmHardware->id,
            'quantity' => 2.00,
            'uom_id' => $pcs->id,
            'material_scrap_percentage' => 0.00,
            'sequence' => 3,
            'notes' => 'Heavy duty SS 304 corner bracket & bolt set.',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomTable->id,
            'material_id' => $rmLacquer->id,
            'quantity' => 1.50,
            'uom_id' => $ltr->id,
            'material_scrap_percentage' => 8.00,
            'sequence' => 4,
            'notes' => 'Scratch resistant PU spray finish.',
        ]);

        ProductionBomApproval::create([
            'tenant_id' => $tenant->id,
            'bom_id' => $bomTable->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Luxury Dining Table master BOM approved.',
        ]);

        // ─── Step 5: Seed Routings & Operations ───────────────────────────────
        // 5A. Sub-Assembly Routing: Chair Leg Frame
        $routingLeg = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'ROUT-SUB-LEG-FRAME',
            'name' => 'Routing - Chair Leg Frame CNC Machining & Sanding Line',
            'product_id' => $subLegFrame->id,
            'version' => '1.0',
            'status' => Routing::STATUS_ACTIVE,
            'description' => '5-step sequential routing for chair leg frame fabrication.',
            'created_by' => $userId,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingLeg->id,
            'work_center_id' => $wcCutting->id,
            'machine_id' => $mcCutting->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Teak Timber Cutting & Sizing',
            'description' => 'Precision beam saw timber plank cutting to leg lengths.',
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 20.0,
            'machine_cost_rate' => 380.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingLeg->id,
            'work_center_id' => $wcCutting->id,
            'machine_id' => $mcCutting->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => '5-Axis CNC Router Leg Profiling & Mortising',
            'description' => '3D curved leg contouring, mortise & tenon joint machining.',
            'setup_time_minutes' => 20.0,
            'processing_time_minutes' => 25.0,
            'machine_cost_rate' => 380.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingLeg->id,
            'work_center_id' => $wcSanding->id,
            'machine_id' => $mcSanding->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Spindle Sanding & Dowel Joint Assembly',
            'description' => 'Wide-belt sanding & PVA glue clamped frame jointing.',
            'setup_time_minutes' => 15.0,
            'processing_time_minutes' => 30.0,
            'machine_cost_rate' => 280.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 5.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingLeg->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Sub-assembly leg frame routing approved.',
        ]);

        // 5B. Finished Good Routing: Executive Chair
        $routingChair = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'ROUT-FG-CHAIR-EXEC',
            'name' => 'Routing - Executive Armchair Assembly & Spray Polish Line',
            'product_id' => $fgChair->id,
            'version' => '1.0',
            'status' => Routing::STATUS_ACTIVE,
            'description' => '5-step sequential routing for executive chair assembly & finishing.',
            'created_by' => $userId,
        ]);

        $opChair10 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingChair->id,
            'work_center_id' => $wcAssembly->id,
            'machine_id' => $mcAssembly->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Frame Assembly & Structural Clamping',
            'description' => 'Joining leg frame with armrests & backrest frame on press.',
            'setup_time_minutes' => 20.0,
            'processing_time_minutes' => 25.0,
            'machine_cost_rate' => 220.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 5,
        ]);

        $opChair20 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingChair->id,
            'work_center_id' => $wcAssembly->id,
            'machine_id' => null,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Foam Cushioning & Velvet Upholstery Tack',
            'description' => 'Stretching velvet fabric over HR foam cushion base.',
            'setup_time_minutes' => 15.0,
            'processing_time_minutes' => 30.0,
            'machine_cost_rate' => 150.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 5,
        ]);

        $opChair30 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingChair->id,
            'work_center_id' => $wcFinishing->id,
            'machine_id' => $mcFinishing->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => '2-Pack PU Clear Spray Polish & IR Drying',
            'description' => 'Automated spray booth matte finish lacquer & IR tunnel curing.',
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 35.0,
            'machine_cost_rate' => 420.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 10,
        ]);

        $opChair40 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingChair->id,
            'work_center_id' => $wcFinishing->id,
            'machine_id' => null,
            'sequence' => 40,
            'operation_number' => 'OP-40',
            'name' => 'Final Ergonomic & Finish Quality Audit',
            'description' => 'Surface sheen check, upholstery seam inspection & stability test.',
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 15.0,
            'machine_cost_rate' => 100.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 5,
        ]);

        $opChair50 = RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingChair->id,
            'work_center_id' => $wcAssembly->id,
            'machine_id' => null,
            'sequence' => 50,
            'operation_number' => 'OP-50',
            'name' => 'Protective Bubble Packaging & FG Warehouse Receipt',
            'description' => 'Carton packing & transfer to Finished Goods Warehouse.',
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 10.0,
            'machine_cost_rate' => 80.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 5,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingChair->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Executive Chair master routing approved.',
        ]);

        // 5C. Finished Good Routing: Luxury 6-Seater Dining Table
        $routingTable = Routing::create([
            'tenant_id' => $tenant->id,
            'routing_number' => 'ROUT-FG-TABLE-LUX',
            'name' => 'Routing - Luxury 6-Seater Dining Table Production Line',
            'product_id' => $fgTable->id,
            'version' => '1.0',
            'status' => Routing::STATUS_ACTIVE,
            'description' => '5-step sequential manufacturing routing for 6-seater solid teak & oak dining table.',
            'created_by' => $userId,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingTable->id,
            'work_center_id' => $wcCutting->id,
            'machine_id' => $mcCutting->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Teak Timber & Oak Veneer Panel Sizing',
            'description' => 'Sizing solid teak planks & Oak veneer sheets on 5-Axis CNC beam saw.',
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 25.0,
            'machine_cost_rate' => 380.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingTable->id,
            'work_center_id' => $wcCutting->id,
            'machine_id' => $mcCutting->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Table Top Lamination & Edge Banding',
            'description' => 'Crown-cut Oak veneer hot press lamination & solid teak edge banding.',
            'setup_time_minutes' => 20.0,
            'processing_time_minutes' => 30.0,
            'machine_cost_rate' => 380.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingTable->id,
            'work_center_id' => $wcAssembly->id,
            'machine_id' => $mcAssembly->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Leg Frame & Top Structural Assembly',
            'description' => 'Mortise-and-tenon apron joining & SS corner bolt bracket assembly.',
            'setup_time_minutes' => 25.0,
            'processing_time_minutes' => 35.0,
            'machine_cost_rate' => 220.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 10,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingTable->id,
            'work_center_id' => $wcFinishing->id,
            'machine_id' => $mcFinishing->id,
            'sequence' => 40,
            'operation_number' => 'OP-40',
            'name' => 'Scratch-Resistant PU Spray Polish & IR Oven Drying',
            'description' => 'Cleanroom automated spray booth matte finish lacquer & IR oven curing.',
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 40.0,
            'machine_cost_rate' => 420.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 15,
        ]);

        RoutingOperation::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingTable->id,
            'work_center_id' => $wcAssembly->id,
            'machine_id' => null,
            'sequence' => 50,
            'operation_number' => 'OP-50',
            'name' => 'Wooden Crate Packaging & FG Warehouse Receipt',
            'description' => 'Wooden crate protective packing & transfer to Finished Goods Warehouse.',
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 15.0,
            'machine_cost_rate' => 80.0,
            'expected_yield_percentage' => 100.0,
            'overlap_enabled' => true,
            'transfer_batch_quantity' => 2.0,
            'transfer_lag_minutes' => 5,
        ]);

        RoutingApproval::create([
            'tenant_id' => $tenant->id,
            'routing_id' => $routingTable->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => 'Luxury Dining Table master routing approved.',
        ]);

        $this->command->info('Furniture Manufacturing Products & Production data seeded successfully!');
    }
}
