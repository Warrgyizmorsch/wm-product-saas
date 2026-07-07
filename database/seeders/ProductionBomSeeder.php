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
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ProductionBomSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Truncate all target tables to remove old/stale data cleanly
        Schema::disableForeignKeyConstraints();
        DB::table('production_bom_items')->truncate();
        DB::table('production_bom_approvals')->truncate();
        DB::table('production_boms')->truncate();
        DB::table('production_routing_operation_materials')->truncate();
        DB::table('production_routing_approvals')->truncate();
        DB::table('production_routing_operations')->truncate();
        DB::table('routings')->truncate();
        DB::table('production_machines')->truncate();
        DB::table('production_work_centers')->truncate();
        
        // Truncate inventory tracking & stocks tables to prevent orphan rows due to ForeignKey constraints disablement
        DB::table('product_warehouse_stocks')->truncate();
        DB::table('stock_transactions')->truncate();
        DB::table('serial_numbers')->truncate();
        DB::table('batches')->truncate();
        DB::table('stock_reservations')->truncate();
        
        DB::table('products')->truncate();
        Schema::enableForeignKeyConstraints();

        // 2. Loop through all tenants to seed the E-Bike scenario
        $tenants = Tenant::all();
        if ($tenants->isEmpty()) {
            // Ensure at least demo tenant exists
            $demoTenant = Tenant::create([
                'name' => 'Demo Tenant',
                'slug' => 'demo',
                'status' => Tenant::STATUS_ACTIVE,
                'plan' => Tenant::PLAN_ENTERPRISE,
                'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE,
                'max_users' => 100,
                'max_storage_mb' => 10240,
                'plan_started_at' => now(),
            ]);
            $tenants = collect([$demoTenant]);
        }

        foreach ($tenants as $tenant) {
            // Get or create admin user for this tenant to mark creator/approver actions
            $user = User::where('tenant_id', $tenant->id)->first();
            if (!$user) {
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Demo Admin',
                    'email' => 'admin@example.com',
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                ]);
            }
            $userId = $user->id;

            // Seed UOMs
            $pcs = Uom::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'PCS'],
                ['name' => 'Pieces']
            );

            $m = Uom::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'M'],
                ['name' => 'Meters']
            );

            // Seed Raw Materials (RMs)
            $alumTube = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'RM-ALUM-TUBE',
                'name' => 'Aluminum Tubing (Grade 6061)',
                'type' => 'raw_material',
                'status' => 'active',
                'unit_cost' => 15.0000,
            ]);

            $motor = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'RM-MOTOR-250',
                'name' => 'Electric Hub Motor (250W)',
                'type' => 'raw_material',
                'status' => 'active',
                'unit_cost' => 120.0000,
            ]);

            $cell = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'RM-CELL-18650',
                'name' => 'Lithium-Ion Cell (18650 3.2V)',
                'type' => 'raw_material',
                'status' => 'active',
                'unit_cost' => 2.5000,
            ]);

            $bms = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'RM-BMS-48V',
                'name' => 'Battery Management System (48V 13S)',
                'type' => 'raw_material',
                'status' => 'active',
                'unit_cost' => 35.0000,
            ]);

            $seat = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'RM-SEAT-LTHR',
                'name' => 'Leather Seat Cushion',
                'type' => 'raw_material',
                'status' => 'active',
                'unit_cost' => 20.0000,
            ]);

            $bars = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'RM-HAND-ERGO',
                'name' => 'Ergonomic Handlebars',
                'type' => 'raw_material',
                'status' => 'active',
                'unit_cost' => 15.0000,
            ]);

            // Seed Semi-Finished Goods (SFGs)
            $frameAssy = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'SF-FRAME-ASSY',
                'name' => 'E-Bike Frame Assembly',
                'type' => 'semi_finished',
                'status' => 'active',
                'unit_cost' => 0.0000,
            ]);

            $battPack = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'SF-BATT-48V',
                'name' => 'Lithium Battery Pack (48V 15Ah)',
                'type' => 'semi_finished',
                'status' => 'active',
                'unit_cost' => 0.0000,
            ]);

            // Seed Finished Good (FG)
            $ebike = Product::create([
                'tenant_id' => $tenant->id,
                'sku' => 'FG-EBIKE-X1',
                'name' => 'Electric Bicycle (E-Bike) X-1',
                'type' => 'finished_good',
                'status' => 'active',
                'unit_cost' => 0.0000,
            ]);

            // Seed Work Center Hierarchies

            // -- 1. Frame Fabrication Dept Hierarchy
            $deptFab = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Frame Fabrication Dept',
                'code' => 'DEPT-FAB',
                'type' => 'department',
                'work_center_type' => 'machining',
                'description' => 'Raw metal cutting, bending and structural department.',
                'department_name' => 'Fabrication',
                'location' => 'Building A - North',
                'capacity_per_hour' => 10.00,
                'efficiency_percentage' => 95.00,
                'cost_per_hour' => 40.0000,
                'overhead_rate' => 20.0000,
                'status' => 'active',
                'parent_id' => null,
            ]);

            $secWelding = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Welding Section',
                'code' => 'SEC-WELD',
                'type' => 'section',
                'work_center_type' => 'assembly',
                'description' => 'Precision manual and automated welding section.',
                'department_name' => 'Fabrication',
                'location' => 'Building A - Bay 2',
                'capacity_per_hour' => 8.00,
                'efficiency_percentage' => 92.00,
                'cost_per_hour' => 45.0000,
                'overhead_rate' => 25.0000,
                'status' => 'active',
                'parent_id' => $deptFab->id,
            ]);

            $wcTig = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'TIG Welding Work Center',
                'code' => 'WC-TIG-WELD',
                'type' => 'work_center',
                'work_center_type' => 'assembly',
                'description' => 'Argon gas shielded TIG welding station for high-grade alloy frames.',
                'department_name' => 'Fabrication',
                'location' => 'Building A - Station 4',
                'capacity_per_hour' => 5.00,
                'efficiency_percentage' => 90.00,
                'cost_per_hour' => 50.0000,
                'overhead_rate' => 30.0000,
                'status' => 'active',
                'parent_id' => $secWelding->id,
            ]);

            // -- 2. Electronics Dept Hierarchy
            $deptElec = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Electronics Assembly Dept',
                'code' => 'DEPT-ELEC',
                'type' => 'department',
                'work_center_type' => 'machining',
                'description' => 'Circuit boards, wiring harnesses and power storage manufacturing.',
                'department_name' => 'Electronics',
                'location' => 'Building B - Clean Room',
                'capacity_per_hour' => 15.00,
                'efficiency_percentage' => 98.00,
                'cost_per_hour' => 35.0000,
                'overhead_rate' => 15.0000,
                'status' => 'active',
                'parent_id' => null,
            ]);

            $secBatt = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Battery Assembly Section',
                'code' => 'SEC-BATT',
                'type' => 'section',
                'work_center_type' => 'assembly',
                'description' => 'Lithium cell sorting, packing and module assembly.',
                'department_name' => 'Electronics',
                'location' => 'Building B - Zone 1',
                'capacity_per_hour' => 12.00,
                'efficiency_percentage' => 96.00,
                'cost_per_hour' => 40.0000,
                'overhead_rate' => 20.0000,
                'status' => 'active',
                'parent_id' => $deptElec->id,
            ]);

            $wcCellWeld = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Battery Cell Welding Station',
                'code' => 'WC-CELL-WELD',
                'type' => 'work_center',
                'work_center_type' => 'assembly',
                'description' => 'Pneumatic resistance welding of nickel strips onto lithium cell grids.',
                'department_name' => 'Electronics',
                'location' => 'Building B - Station 2',
                'capacity_per_hour' => 8.00,
                'efficiency_percentage' => 95.00,
                'cost_per_hour' => 45.0000,
                'overhead_rate' => 25.0000,
                'status' => 'active',
                'parent_id' => $secBatt->id,
            ]);

            // -- 3. Final Assembly Dept Hierarchy
            $deptAssy = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Final Assembly Dept',
                'code' => 'DEPT-ASSY',
                'type' => 'department',
                'work_center_type' => 'assembly',
                'description' => 'General assembly, quality testing and dispatch boxing.',
                'department_name' => 'Assembly',
                'location' => 'Building C - South',
                'capacity_per_hour' => 20.00,
                'efficiency_percentage' => 97.00,
                'cost_per_hour' => 30.0000,
                'overhead_rate' => 10.0000,
                'status' => 'active',
                'parent_id' => null,
            ]);

            $secIntegration = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'E-Bike Integration Section',
                'code' => 'SEC-INT',
                'type' => 'section',
                'work_center_type' => 'assembly',
                'description' => 'Electrical integration and structural joinery line.',
                'department_name' => 'Assembly',
                'location' => 'Building C - Bay 1',
                'capacity_per_hour' => 10.00,
                'efficiency_percentage' => 95.00,
                'cost_per_hour' => 35.0000,
                'overhead_rate' => 15.0000,
                'status' => 'active',
                'parent_id' => $deptAssy->id,
            ]);

            $wcMainLine = WorkCenter::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Assembly Line 1',
                'code' => 'WC-MAIN-LINE',
                'type' => 'work_center',
                'work_center_type' => 'assembly',
                'description' => 'Conveyor-based physical and electrical integration assembly line.',
                'department_name' => 'Assembly',
                'location' => 'Building C - Line 1',
                'capacity_per_hour' => 6.00,
                'efficiency_percentage' => 94.00,
                'cost_per_hour' => 40.0000,
                'overhead_rate' => 20.0000,
                'status' => 'active',
                'parent_id' => $secIntegration->id,
            ]);

            // Seed Machines
            $machTig = Machine::create([
                'tenant_id' => $tenant->id,
                'work_center_id' => $wcTig->id,
                'name' => 'Miller TIG Welder T-200',
                'code' => 'MCH-TIG-200',
                'status' => 'active',
            ]);

            $machSpot = Machine::create([
                'tenant_id' => $tenant->id,
                'work_center_id' => $wcCellWeld->id,
                'name' => 'Precision Spot Welder S-500',
                'code' => 'MCH-SPOT-500',
                'status' => 'active',
            ]);

            $machLift = Machine::create([
                'tenant_id' => $tenant->id,
                'work_center_id' => $wcMainLine->id,
                'name' => 'Assembly Lift & Pneumatic Rig',
                'code' => 'MCH-LIFT-100',
                'status' => 'active',
            ]);

            // Seed Routings
            $rtFrame = Routing::create([
                'tenant_id' => $tenant->id,
                'routing_number' => 'RT-FRAME-001',
                'name' => 'E-Bike Frame Assembly Routing',
                'product_id' => $frameAssy->id,
                'version' => '1.0.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => Carbon::now()->subMonths(1),
                'effective_to' => Carbon::now()->addYears(2),
                'description' => 'Operations to weld raw aluminum tubes into a completed E-Bike frame.',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
                'status' => 'active',
            ]);

            $rtBatt = Routing::create([
                'tenant_id' => $tenant->id,
                'routing_number' => 'RT-BATT-001',
                'name' => '48V Battery Pack Fabrication Routing',
                'product_id' => $battPack->id,
                'version' => '1.0.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => Carbon::now()->subMonths(1),
                'effective_to' => Carbon::now()->addYears(2),
                'description' => 'Battery pack fabrication operations including spot cell welding, wire harness integration, and battery casing closing.',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
                'status' => 'active',
            ]);

            $rtEbike = Routing::create([
                'tenant_id' => $tenant->id,
                'routing_number' => 'RT-EBIKE-001',
                'name' => 'E-Bike Final Integration Routing',
                'product_id' => $ebike->id,
                'version' => '1.0.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => Carbon::now()->subMonths(1),
                'effective_to' => Carbon::now()->addYears(2),
                'description' => 'Final road-ready e-bike integration incorporating frame, motor, battery pack, handlebars, and seat.',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
                'status' => 'active',
            ]);

            // Seed Routing Operations
            // -- Frame Operations
            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtFrame->id,
                'sequence' => 10,
                'operation_number' => 'OP-010',
                'name' => 'Frame Tube Cutting',
                'description' => 'Cutting aluminum tubes to size based on framing blueprints.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcTig->id,
                'machine_id' => null,
                'setup_time_minutes' => 15.00,
                'processing_time_minutes' => 30.00,
                'wait_time_minutes' => 5.00,
                'expected_yield_percentage' => 98.00,
                'labor_cost_rate' => $wcTig->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcTig->overhead_rate / 60.0,
                'quality_required' => false,
            ]);

            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtFrame->id,
                'sequence' => 20,
                'operation_number' => 'OP-020',
                'name' => 'Frame Tube TIG Welding',
                'description' => 'Welding tubes together in alignment jigs using high precision TIG welder.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcTig->id,
                'machine_id' => $machTig->id,
                'setup_time_minutes' => 20.00,
                'processing_time_minutes' => 45.00,
                'wait_time_minutes' => 10.00,
                'expected_yield_percentage' => 95.00,
                'labor_cost_rate' => $wcTig->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcTig->overhead_rate / 60.0,
                'quality_required' => true,
            ]);

            // -- Battery Operations
            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtBatt->id,
                'sequence' => 10,
                'operation_number' => 'OP-010',
                'name' => 'Battery Cell Spot Welding',
                'description' => 'Arranging 18650 lithium cells in 13S5P grids and welding connector nickel strips.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcCellWeld->id,
                'machine_id' => $machSpot->id,
                'setup_time_minutes' => 30.00,
                'processing_time_minutes' => 60.00,
                'wait_time_minutes' => 0.00,
                'expected_yield_percentage' => 99.00,
                'labor_cost_rate' => $wcCellWeld->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcCellWeld->overhead_rate / 60.0,
                'quality_required' => true,
            ]);

            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtBatt->id,
                'sequence' => 20,
                'operation_number' => 'OP-020',
                'name' => 'BMS Assembly & Wiring',
                'description' => 'Soldering the BMS protective board to the cell grids and verifying balancing voltages.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcCellWeld->id,
                'machine_id' => null,
                'setup_time_minutes' => 15.00,
                'processing_time_minutes' => 25.00,
                'wait_time_minutes' => 5.00,
                'expected_yield_percentage' => 98.00,
                'labor_cost_rate' => $wcCellWeld->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcCellWeld->overhead_rate / 60.0,
                'quality_required' => true,
            ]);

            // -- E-Bike Final Operations
            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtEbike->id,
                'sequence' => 10,
                'operation_number' => 'OP-010',
                'name' => 'Chassis & Motor Assembly',
                'description' => 'Chassis alignment and rear hub wheel motor fitting.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcMainLine->id,
                'machine_id' => $machLift->id,
                'setup_time_minutes' => 30.00,
                'processing_time_minutes' => 50.00,
                'wait_time_minutes' => 0.00,
                'expected_yield_percentage' => 100.00,
                'labor_cost_rate' => $wcMainLine->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcMainLine->overhead_rate / 60.0,
                'quality_required' => false,
            ]);

            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtEbike->id,
                'sequence' => 20,
                'operation_number' => 'OP-020',
                'name' => 'Battery and Electronics Integration',
                'description' => 'Battery pack mounting and cable management connection routing.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcMainLine->id,
                'machine_id' => null,
                'setup_time_minutes' => 15.00,
                'processing_time_minutes' => 30.00,
                'wait_time_minutes' => 0.00,
                'expected_yield_percentage' => 99.00,
                'labor_cost_rate' => $wcMainLine->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcMainLine->overhead_rate / 60.0,
                'quality_required' => true,
            ]);

            RoutingOperation::create([
                'tenant_id' => $tenant->id,
                'routing_id' => $rtEbike->id,
                'sequence' => 30,
                'operation_number' => 'OP-030',
                'name' => 'Final Cosmetics and Road Test',
                'description' => 'Mounting seat, handlebar fixtures and final road readiness audit.',
                'operation_type' => 'manufacturing',
                'work_center_id' => $wcMainLine->id,
                'machine_id' => null,
                'setup_time_minutes' => 10.00,
                'processing_time_minutes' => 20.00,
                'wait_time_minutes' => 0.00,
                'expected_yield_percentage' => 100.00,
                'labor_cost_rate' => $wcMainLine->cost_per_hour / 60.0,
                'machine_cost_rate' => $wcMainLine->overhead_rate / 60.0,
                'quality_required' => true,
            ]);


            // Seed BOMs
            // -- 1. Frame Assembly BOM
            $bomFrame = ProductionBom::create([
                'tenant_id' => $tenant->id,
                'bom_number' => 'BOM-FRAME-001',
                'bom_name' => 'E-Bike Frame Assembly Standard BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $frameAssy->id,
                'base_quantity' => 1.0000,
                'base_uom_id' => $pcs->id,
                'version' => '1.0.0',
                'revision' => 0,
                'revision_reason' => 'Initial release.',
                'routing_id' => $rtFrame->id,
                'effective_date' => Carbon::now()->subMonths(1)->toDateString(),
                'expiry_date' => Carbon::now()->addYears(2)->toDateString(),
                'status' => 'approved',
                'notes' => 'Chassis structure recipe using Grade 6061 heavy alloy.',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
            ]);

            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomFrame->id,
                'material_id' => $alumTube->id,
                'quantity' => 3.5000,
                'uom_id' => $m->id,
                'material_scrap_percentage' => 5.00,
                'is_alternative' => false,
                'sequence' => 10,
                'notes' => 'Aluminum hollow tubes.',
            ]);

            ProductionBomApproval::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomFrame->id,
                'action' => 'Created',
                'user_id' => $userId,
                'comments' => 'Initial seed release.',
            ]);
            ProductionBomApproval::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomFrame->id,
                'action' => 'Approved',
                'user_id' => $userId,
                'comments' => 'Approved frame BOM.',
            ]);

            // -- 2. Battery Pack BOM
            $bomBatt = ProductionBom::create([
                'tenant_id' => $tenant->id,
                'bom_number' => 'BOM-BATT-001',
                'bom_name' => '48V 15Ah Battery Pack BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $battPack->id,
                'base_quantity' => 1.0000,
                'base_uom_id' => $pcs->id,
                'version' => '1.0.0',
                'revision' => 0,
                'revision_reason' => 'Initial release.',
                'routing_id' => $rtBatt->id,
                'effective_date' => Carbon::now()->subMonths(1)->toDateString(),
                'expiry_date' => Carbon::now()->addYears(2)->toDateString(),
                'status' => 'approved',
                'notes' => 'Internal battery recipe using selected 18650 power cells.',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
            ]);

            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomBatt->id,
                'material_id' => $cell->id,
                'quantity' => 65.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 2.00,
                'is_alternative' => false,
                'sequence' => 10,
                'notes' => 'Lithium ion 18650 battery cells.',
            ]);

            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomBatt->id,
                'material_id' => $bms->id,
                'quantity' => 1.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 0.00,
                'is_alternative' => false,
                'sequence' => 20,
                'notes' => 'BMS 13S circuit protective panel.',
            ]);

            ProductionBomApproval::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomBatt->id,
                'action' => 'Created',
                'user_id' => $userId,
                'comments' => 'Initial seed release.',
            ]);
            ProductionBomApproval::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomBatt->id,
                'action' => 'Approved',
                'user_id' => $userId,
                'comments' => 'Approved battery BOM.',
            ]);


            // -- 3. E-Bike Final Integration BOM (Parent BOM)
            $bomEbike = ProductionBom::create([
                'tenant_id' => $tenant->id,
                'bom_number' => 'BOM-EBIKE-001',
                'bom_name' => 'Electric Bicycle E-Bike X-1 Master BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $ebike->id,
                'base_quantity' => 1.0000,
                'base_uom_id' => $pcs->id,
                'version' => '1.0.0',
                'revision' => 0,
                'revision_reason' => 'Initial release.',
                'routing_id' => $rtEbike->id,
                'effective_date' => Carbon::now()->subMonths(1)->toDateString(),
                'expiry_date' => Carbon::now()->addYears(2)->toDateString(),
                'status' => 'approved',
                'notes' => 'Top-level master manufacturing configuration recipe for E-Bike Model X-1.',
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => Carbon::now(),
            ]);

            // Frame Assembly component (SFG with child BOM linked)
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'material_id' => $frameAssy->id,
                'quantity' => 1.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 0.00,
                'is_alternative' => false,
                'sequence' => 10,
                'child_bom_id' => $bomFrame->id,
                'notes' => 'Pre-fabricated alloy structure.',
            ]);

            // Battery Pack component (SFG with child BOM linked)
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'material_id' => $battPack->id,
                'quantity' => 1.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 0.00,
                'is_alternative' => false,
                'sequence' => 20,
                'child_bom_id' => $bomBatt->id,
                'notes' => 'Lithium storage unit.',
            ]);

            // Motor component
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'material_id' => $motor->id,
                'quantity' => 1.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 0.00,
                'is_alternative' => false,
                'sequence' => 30,
                'notes' => 'Direct rear hub electrical driver.',
            ]);

            // Seat component
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'material_id' => $seat->id,
                'quantity' => 1.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 0.00,
                'is_alternative' => false,
                'sequence' => 40,
                'notes' => 'Genuine leather saddle.',
            ]);

            // Handlebars component
            ProductionBomItem::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'material_id' => $bars->id,
                'quantity' => 1.0000,
                'uom_id' => $pcs->id,
                'material_scrap_percentage' => 0.00,
                'is_alternative' => false,
                'sequence' => 50,
                'notes' => 'Ergonomic ride-control unit.',
            ]);

            ProductionBomApproval::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'action' => 'Created',
                'user_id' => $userId,
                'comments' => 'Initial master release.',
            ]);
            ProductionBomApproval::create([
                'tenant_id' => $tenant->id,
                'bom_id' => $bomEbike->id,
                'action' => 'Approved',
                'user_id' => $userId,
                'comments' => 'Approved master E-Bike BOM.',
            ]);
        }
    }
}
