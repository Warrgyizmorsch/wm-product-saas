<?php

namespace Database\Seeders;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\RoutingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProductionRoutingBomDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first() ?? new Tenant(['id' => 1]);
        app(TenantContext::class)->set($tenant);
        $tenantId = $tenant->id;

        $user = User::first();
        if ($user) {
            auth()->login($user);
        }
        $userId = $user ? $user->id : 1;

        $uomPcs = Uom::where('code', 'Pcs')->first() ?? Uom::find(1);
        $uomMtr = Uom::where('code', 'Mtr')->first() ?? Uom::find(2);
        $uomKg  = Uom::where('code', 'Kg')->first()  ?? Uom::find(4);

        $vendorAcme = Vendor::where('code', 'VEND-ACME')->first() ?? Vendor::first();

        $wcFab = WorkCenter::where('code', 'WC-FAB-01')->first();
        $wcSht = WorkCenter::where('code', 'WC-SHT-02')->first();
        $wcWld = WorkCenter::where('code', 'WC-WLD-03')->first();

        $secMachDrill = WorkCenterSection::where('code', 'SEC-MACH-01')->first();
        $secManualDeburr = WorkCenterSection::where('code', 'SEC-FIN-02')->first();
        $secLaserCut = WorkCenterSection::where('code', 'SEC-CUT-01')->first();
        $secPressBend = WorkCenterSection::where('code', 'SEC-BND-02')->first();
        $secRoboticWeld = WorkCenterSection::where('code', 'SEC-WLD-01')->first();
        $secPowderCoat = WorkCenterSection::where('code', 'SEC-PDR-02')->first();

        $machMillingHaas = Machine::where('name', 'like', '%Haas%')->first();
        $machPressBrakeAmada = Machine::where('name', 'like', '%Amada%')->first();
        $machPlasmaHypertherm = Machine::where('name', 'like', '%Hypertherm%')->first();
        $machRoboticLincoln = Machine::where('name', 'like', '%Lincoln%')->first();
        $machHeaterThermax = Machine::where('name', 'like', '%Thermax%')->first();
        $machDrillAtlas = Machine::where('name', 'like', '%Atlas%')->first();

        $batchDrilling = SectionManualProduction::where('batch_name', 'Batch-Drilling-Cell')->first();
        $batchDeburrPack = SectionManualProduction::where('batch_name', 'Batch-Manual-Deburr-Pack')->first();
        $batchRoboticWeld = SectionManualProduction::where('batch_name', 'Batch-Robotic-Weld-Morn')->first();

        // 1. Ensure Products Exist
        $productsToEnsure = [
            [
                'sku' => 'RM-PNT-BLK',
                'name' => 'Industrial Matte Black Powder Coating',
                'type' => 'raw_material',
                'item_type' => 'Goods',
                'supplier_method' => 'trade',
                'planning_type' => 'purchase',
                'uom_id' => $uomKg->id,
                'unit_cost' => 450.00,
                'cost_price' => 450.00,
                'selling_price' => 600.00,
                'description' => 'Industrial electrostatically applicable thermoset epoxy-polyester black powder coating.'
            ],
            [
                'sku' => 'RM-WOOD-OIL',
                'name' => 'Polyurethane Teak Finish Oil & Sealant',
                'type' => 'raw_material',
                'item_type' => 'Goods',
                'supplier_method' => 'trade',
                'planning_type' => 'purchase',
                'uom_id' => $uomKg->id,
                'unit_cost' => 750.00,
                'cost_price' => 750.00,
                'selling_price' => 950.00,
                'description' => 'High-penetration weather-resistant wood sealant for engineered oak and teak tops.'
            ],
            [
                'sku' => 'RM-RUB-PAD',
                'name' => 'Heavy-Duty Floor Protector Rubber Pads',
                'type' => 'raw_material',
                'item_type' => 'Goods',
                'supplier_method' => 'trade',
                'planning_type' => 'purchase',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 25.00,
                'cost_price' => 25.00,
                'selling_price' => 50.00,
                'description' => 'Vulcanized anti-vibration non-slip rubber glides for table leg bases.'
            ],
            [
                'sku' => 'RM-SS-FASTENER',
                'name' => 'Grade 304 Stainless Steel Fastener Set',
                'type' => 'raw_material',
                'item_type' => 'Goods',
                'supplier_method' => 'trade',
                'planning_type' => 'purchase',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 480.00,
                'cost_price' => 480.00,
                'selling_price' => 700.00,
                'description' => 'Premium corrosion-proof A2-70 / SS304 hex bolt, spring washer, and flange nut assembly pack.'
            ],
            [
                'sku' => 'RM-CORR-BOX',
                'name' => 'Heavy Double-Wall Corrugated Packaging Box',
                'type' => 'raw_material',
                'item_type' => 'Goods',
                'supplier_method' => 'trade',
                'planning_type' => 'purchase',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 180.00,
                'cost_price' => 180.00,
                'selling_price' => 260.00,
                'description' => '5-ply export grade heavy-duty shipping carton for furniture packaging.'
            ],
            [
                'sku' => 'SFG-PHANTOM-HW',
                'name' => 'Pre-Packed Hardware Installation Sub-Pack',
                'type' => 'semi_finished',
                'item_type' => 'Goods',
                'supplier_method' => 'manufacture',
                'planning_type' => 'manufacture',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 350.00,
                'cost_price' => 350.00,
                'selling_price' => 550.00,
                'description' => 'Blow-through phantom assembly kit containing fasteners, glides, and hex key.'
            ],
            [
                'sku' => 'SFG-COAT-LEG',
                'name' => 'Powder-Coated Steel Table Leg',
                'type' => 'semi_finished',
                'item_type' => 'Goods',
                'supplier_method' => 'manufacture',
                'planning_type' => 'manufacture',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 850.00,
                'cost_price' => 850.00,
                'selling_price' => 1200.00,
                'description' => 'Formed and externally powder-coated heavy steel table leg ready for assembly.'
            ],
            [
                'sku' => 'FG-TBL-KIT',
                'name' => 'DIY Flat-Pack Industrial Dining Table Kit',
                'type' => 'finished_good',
                'item_type' => 'Goods',
                'supplier_method' => 'manufacture',
                'planning_type' => 'manufacture',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 7800.00,
                'cost_price' => 7800.00,
                'selling_price' => 14500.00,
                'description' => 'Unassembled flat-packed dining table kit with pre-drilled top, 4 legs, rails, and assembly hardware.'
            ],
            [
                'sku' => 'FG-TBL-PROTO',
                'name' => 'Ergonomic Height-Adjustable Prototype Table',
                'type' => 'finished_good',
                'item_type' => 'Goods',
                'supplier_method' => 'manufacture',
                'planning_type' => 'manufacture',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 12500.00,
                'cost_price' => 12500.00,
                'selling_price' => 22000.00,
                'description' => 'Experimental R&D prototype dining table with motorized linear actuator frame.'
            ],
            [
                'sku' => 'SRV-POWDER-COAT',
                'name' => 'Outsourced Electrostatic Powder Coating Service',
                'type' => 'service',
                'item_type' => 'Service',
                'supplier_method' => 'trade',
                'planning_type' => 'purchase',
                'uom_id' => $uomPcs->id,
                'unit_cost' => 150.00,
                'cost_price' => 150.00,
                'selling_price' => 200.00,
                'description' => 'Subcontracted third-party surface treatment, grit blasting, and electrostatic powder coat.'
            ]
        ];

        $productMap = [];
        foreach ($productsToEnsure as $pData) {
            $prod = Product::firstOrCreate(
                ['sku' => $pData['sku']],
                array_merge($pData, [
                    'tenant_id' => $tenantId,
                    'company_id' => 1,
                    'status' => 'active',
                    'variation_type' => 'Single',
                    'gst_rate' => 18.00,
                ])
            );
            $productMap[$prod->sku] = $prod;
        }

        foreach (Product::whereIn('id', [81, 82, 83, 84, 85, 86, 87, 88])->get() as $p) {
            $productMap[$p->sku] = $p;
        }

        // Clean circular dependency in BOM 1 Item #6 if present
        $circularItem = ProductionBomItem::where('bom_id', 1)->where('material_id', 88)->first();
        if ($circularItem) {
            $circularItem->update(['material_id' => 84]);
        }

        $routingService = app(RoutingService::class);
        $bomService = app(ProductionBomService::class);
        $today = Carbon::today()->toDateString();

        // 2. Routing 2: Assembly & Packaging (Active)
        $r2Number = 'RTG-2026-000002';
        $routing2 = Routing::where('routing_number', $r2Number)->first();
        if (!$routing2) {
            $dtoR2 = RoutingDTO::fromArray([
                'routing_number' => $r2Number,
                'name' => 'Dining Table Final Assembly & Packaging Routing',
                'version' => '1.0.0',
                'effective_from' => $today,
                'is_default' => true,
                'description' => 'Multi-step mechanical assembly, quality inspection, and packaging routing for finished tables.',
                'operations' => [
                    [
                        'sequence' => 10,
                        'operation_number' => 'OP-010',
                        'name' => 'Mechanical Frame & Top Fastening',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secMachDrill?->id,
                        'machine_id' => $machDrillAtlas?->id,
                        'setup_time_minutes' => 10.0,
                        'processing_time_minutes' => 25.0,
                        'wait_time_minutes' => 5.0,
                        'expected_yield_percentage' => 99.5,
                        'labor_cost_rate' => 250.0,
                        'machine_cost_rate' => 120.0,
                        'instructions' => 'Align table top upside down on felt jig. Fasten steel frame securely using pneumatic torque driver at 35 Nm.',
                    ],
                    [
                        'sequence' => 20,
                        'operation_number' => 'OP-020',
                        'name' => 'Buffing, Corner Deburring & Glide Fitting',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secManualDeburr?->id,
                        'section_manual_production_id' => $batchDeburrPack?->id,
                        'setup_time_minutes' => 5.0,
                        'processing_time_minutes' => 15.0,
                        'wait_time_minutes' => 0.0,
                        'expected_yield_percentage' => 100.0,
                        'labor_cost_rate' => 200.0,
                        'machine_cost_rate' => 0.0,
                        'instructions' => 'Inspect all corners, remove edge burrs with hand rasp, attach 4 rubber floor glides to leg bases.',
                    ],
                    [
                        'sequence' => 30,
                        'operation_number' => 'OP-030',
                        'name' => 'Final Quality Inspection & Structural Stability Gate',
                        'operation_type' => 'inspection',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secManualDeburr?->id,
                        'section_manual_production_id' => $batchDeburrPack?->id,
                        'quality_required' => true,
                        'setup_time_minutes' => 0.0,
                        'processing_time_minutes' => 10.0,
                        'wait_time_minutes' => 0.0,
                        'expected_yield_percentage' => 100.0,
                        'labor_cost_rate' => 220.0,
                        'machine_cost_rate' => 0.0,
                        'instructions' => 'Verify dimensional squareness (diagonals within 2mm), wobble-free leveling, and zero surface scratch compliance.',
                    ],
                    [
                        'sequence' => 40,
                        'operation_number' => 'OP-040',
                        'name' => 'Protective Packaging & Export Boxing',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secManualDeburr?->id,
                        'section_manual_production_id' => $batchDeburrPack?->id,
                        'setup_time_minutes' => 5.0,
                        'processing_time_minutes' => 12.0,
                        'wait_time_minutes' => 0.0,
                        'expected_yield_percentage' => 100.0,
                        'labor_cost_rate' => 180.0,
                        'machine_cost_rate' => 0.0,
                        'instructions' => 'Wrap top with protective foam corner guards, box in heavy corrugated carton, and print barcode shipping label.',
                    ],
                ],
            ]);
            $routing2 = $routingService->create($dtoR2, $tenantId, $userId);
            $routingService->submitApproval($routing2->id, $userId);
            $routing2 = $routingService->approve($routing2->id, $userId);
        }

        // 3. Routing 3: Wood Top Profiling & Curing (Active)
        $r3Number = 'RTG-2026-000003';
        $routing3 = Routing::where('routing_number', $r3Number)->first();
        if (!$routing3) {
            $dtoR3 = RoutingDTO::fromArray([
                'routing_number' => $r3Number,
                'name' => 'Engineered Wood Top Profiling & High-Gloss Curing',
                'version' => '1.0.0',
                'effective_from' => $today,
                'is_default' => true,
                'description' => 'Precision wood milling, manual sanding, and thermal fluid cured sealant for table tops.',
                'operations' => [
                    [
                        'sequence' => 10,
                        'operation_number' => 'OP-010',
                        'name' => '5-Axis CNC Perimeter Profiling & Edge Chamfering',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcSht?->id,
                        'section_id' => $secLaserCut?->id,
                        'machine_id' => $machMillingHaas?->id,
                        'setup_time_minutes' => 15.0,
                        'processing_time_minutes' => 20.0,
                        'wait_time_minutes' => 5.0,
                        'expected_yield_percentage' => 98.0,
                        'labor_cost_rate' => 280.0,
                        'machine_cost_rate' => 350.0,
                    ],
                    [
                        'sequence' => 20,
                        'operation_number' => 'OP-020',
                        'name' => 'Precision Wood Sanding & Pre-Seal Buffing',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secManualDeburr?->id,
                        'section_manual_production_id' => $batchDeburrPack?->id,
                        'setup_time_minutes' => 5.0,
                        'processing_time_minutes' => 15.0,
                        'wait_time_minutes' => 0.0,
                        'expected_yield_percentage' => 100.0,
                        'labor_cost_rate' => 190.0,
                        'machine_cost_rate' => 0.0,
                    ],
                    [
                        'sequence' => 30,
                        'operation_number' => 'OP-030',
                        'name' => 'Polyurethane Protective Topcoat & Thermal Curing',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcWld?->id,
                        'section_id' => $secPowderCoat?->id,
                        'machine_id' => $machHeaterThermax?->id,
                        'setup_time_minutes' => 15.0,
                        'processing_time_minutes' => 30.0,
                        'wait_time_minutes' => 15.0,
                        'expected_yield_percentage' => 97.0,
                        'labor_cost_rate' => 220.0,
                        'machine_cost_rate' => 280.0,
                    ],
                ],
            ]);
            $routing3 = $routingService->create($dtoR3, $tenantId, $userId);
            $routingService->submitApproval($routing3->id, $userId);
            $routing3 = $routingService->approve($routing3->id, $userId);
        }

        // 4. Routing 4: Subcontracted Powder Coating (Active)
        $r4Number = 'RTG-2026-000004';
        $routing4 = Routing::where('routing_number', $r4Number)->first();
        if (!$routing4) {
            $dtoR4 = RoutingDTO::fromArray([
                'routing_number' => $r4Number,
                'name' => 'Steel Table Leg Heavy Forming & Subcontracted Powder Coating',
                'version' => '1.0.0',
                'effective_from' => $today,
                'is_default' => true,
                'description' => 'Plasma cutting, hydraulic bending, external subcontract coating, and receiving inspection routing.',
                'operations' => [
                    [
                        'sequence' => 10,
                        'operation_number' => 'OP-010',
                        'name' => 'Plasma Cut to Exact Length & Mitre Notching',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcSht?->id,
                        'section_id' => $secLaserCut?->id,
                        'machine_id' => $machPlasmaHypertherm?->id,
                        'setup_time_minutes' => 10.0,
                        'processing_time_minutes' => 8.0,
                        'labor_cost_rate' => 220.0,
                        'machine_cost_rate' => 310.0,
                    ],
                    [
                        'sequence' => 20,
                        'operation_number' => 'OP-020',
                        'name' => '130-Ton Press Brake Offset Bending',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcSht?->id,
                        'section_id' => $secPressBend?->id,
                        'machine_id' => $machPressBrakeAmada?->id,
                        'setup_time_minutes' => 15.0,
                        'processing_time_minutes' => 12.0,
                        'labor_cost_rate' => 260.0,
                        'machine_cost_rate' => 420.0,
                    ],
                    [
                        'sequence' => 30,
                        'operation_number' => 'OP-030',
                        'name' => 'Subcontracted Electrostatic Powder Coating',
                        'operation_type' => 'outsourcing',
                        'is_external' => true,
                        'vendor_id' => $vendorAcme?->id,
                        'subcontract_cost_per_unit' => 150.0,
                        'subcontract_lead_time_days' => 3,
                        'subcontract_service_product_id' => $productMap['SRV-POWDER-COAT']->id,
                        'material_supply_type' => 'company_supplied',
                        'subcontract_input_type' => 'previous_operation_wip',
                        'dispatch_buffer_days' => 1,
                        'return_buffer_days' => 1,
                    ],
                    [
                        'sequence' => 40,
                        'operation_number' => 'OP-040',
                        'name' => 'Receiving Coating Thickness & Adhesion Inspection',
                        'operation_type' => 'inspection',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secManualDeburr?->id,
                        'quality_required' => true,
                        'setup_time_minutes' => 0.0,
                        'processing_time_minutes' => 5.0,
                        'labor_cost_rate' => 210.0,
                    ],
                ],
            ]);
            $routing4 = $routingService->create($dtoR4, $tenantId, $userId);
            $routingService->submitApproval($routing4->id, $userId);
            $routing4 = $routingService->approve($routing4->id, $userId);
        }

        // 5. Routing 5: High-Volume Robotic MIG (Active)
        $r5Number = 'RTG-2026-000005';
        $routing5 = Routing::where('routing_number', $r5Number)->first();
        if (!$routing5) {
            $dtoR5 = RoutingDTO::fromArray([
                'routing_number' => $r5Number,
                'name' => 'High-Volume Frame Robotic MIG Welding & Thermal Treatment',
                'version' => '1.0.0',
                'effective_from' => $today,
                'is_default' => true,
                'description' => 'Automated robotic welding with transfer batching overlap and manual TIG backup alternative operation.',
                'operations' => [
                    [
                        'sequence' => 10,
                        'operation_number' => 'OP-010',
                        'name' => 'Jig Clamping & Precision Tack Welding',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcWld?->id,
                        'section_id' => $secRoboticWeld?->id,
                        'section_manual_production_id' => $batchRoboticWeld?->id,
                        'setup_time_minutes' => 12.0,
                        'processing_time_minutes' => 15.0,
                        'labor_cost_rate' => 240.0,
                    ],
                    [
                        'sequence' => 20,
                        'operation_number' => 'OP-020',
                        'name' => 'Robotic Continuous MIG Seam Welding (Primary)',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcWld?->id,
                        'section_id' => $secRoboticWeld?->id,
                        'machine_id' => $machRoboticLincoln?->id,
                        'overlap_enabled' => true,
                        'transfer_batch_quantity' => 5.0,
                        'transfer_lag_minutes' => 10,
                        'row_uid' => 'op-robotic-weld',
                        'setup_time_minutes' => 20.0,
                        'processing_time_minutes' => 18.0,
                        'labor_cost_rate' => 150.0,
                        'machine_cost_rate' => 450.0,
                    ],
                    [
                        'sequence' => 25,
                        'operation_number' => 'OP-025',
                        'name' => 'Manual TIG Backup Seam Welding (Alternative to Op 20)',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcWld?->id,
                        'section_id' => $secRoboticWeld?->id,
                        'section_manual_production_id' => $batchRoboticWeld?->id,
                        'is_alternative' => true,
                        'replaces_row_uid' => 'op-robotic-weld',
                        'priority' => 2,
                        'setup_time_minutes' => 10.0,
                        'processing_time_minutes' => 35.0,
                        'labor_cost_rate' => 300.0,
                    ],
                    [
                        'sequence' => 30,
                        'operation_number' => 'OP-030',
                        'name' => 'Weld Penetration Dye Penetrant Inspection',
                        'operation_type' => 'inspection',
                        'work_center_id' => $wcWld?->id,
                        'section_id' => $secRoboticWeld?->id,
                        'quality_required' => true,
                        'setup_time_minutes' => 5.0,
                        'processing_time_minutes' => 8.0,
                        'labor_cost_rate' => 230.0,
                    ],
                ],
            ]);
            $routing5 = $routingService->create($dtoR5, $tenantId, $userId);
            $routingService->submitApproval($routing5->id, $userId);
            $routing5 = $routingService->approve($routing5->id, $userId);
        }

        // 6. Routing 6: Draft Prototype Routing
        $r6Number = 'RTG-2026-000006';
        $routing6 = Routing::where('routing_number', $r6Number)->first();
        if (!$routing6) {
            $dtoR6 = RoutingDTO::fromArray([
                'routing_number' => $r6Number,
                'name' => 'Ergonomic Height-Adjustable Prototype Routing',
                'version' => '1.0.0',
                'effective_from' => $today,
                'is_default' => false,
                'description' => 'Experimental R&D prototype routing for motorized ergonomic sit-stand dining table in draft state.',
                'operations' => [
                    [
                        'sequence' => 10,
                        'operation_number' => 'OP-010',
                        'name' => 'Experimental CNC Laser Pattern Milling',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcSht?->id,
                        'section_id' => $secLaserCut?->id,
                        'machine_id' => $machMillingHaas?->id,
                        'setup_time_minutes' => 30.0,
                        'processing_time_minutes' => 45.0,
                        'labor_cost_rate' => 300.0,
                        'machine_cost_rate' => 350.0,
                    ],
                    [
                        'sequence' => 20,
                        'operation_number' => 'OP-020',
                        'name' => 'R&D Prototype Actuator Manual Assembly',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcFab?->id,
                        'section_id' => $secMachDrill?->id,
                        'section_manual_production_id' => $batchDrilling?->id,
                        'setup_time_minutes' => 20.0,
                        'processing_time_minutes' => 40.0,
                        'labor_cost_rate' => 260.0,
                    ],
                ],
            ]);
            $routing6 = $routingService->create($dtoR6, $tenantId, $userId);
        }

        // 7. Routing 7: Pending Approval
        $r7Number = 'RTG-2026-000007';
        $routing7 = Routing::where('routing_number', $r7Number)->first();
        if (!$routing7) {
            $dtoR7 = RoutingDTO::fromArray([
                'routing_number' => $r7Number,
                'name' => 'Automated Sheet Metal Forming Routing v2',
                'version' => '2.0.0',
                'effective_from' => $today,
                'is_default' => false,
                'description' => 'Version 2 high-throughput automated sheet metal routing submitted for plant manager approval.',
                'operations' => [
                    [
                        'sequence' => 10,
                        'operation_number' => 'OP-010',
                        'name' => 'High-Speed Automated Plasma Sheet Slicing',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcSht?->id,
                        'section_id' => $secLaserCut?->id,
                        'machine_id' => $machPlasmaHypertherm?->id,
                        'setup_time_minutes' => 15.0,
                        'processing_time_minutes' => 10.0,
                        'labor_cost_rate' => 200.0,
                        'machine_cost_rate' => 300.0,
                    ],
                    [
                        'sequence' => 20,
                        'operation_number' => 'OP-020',
                        'name' => 'Multi-Stage CNC Forming & Edge Hemming',
                        'operation_type' => 'manufacturing',
                        'work_center_id' => $wcSht?->id,
                        'section_id' => $secPressBend?->id,
                        'machine_id' => $machPressBrakeAmada?->id,
                        'setup_time_minutes' => 15.0,
                        'processing_time_minutes' => 15.0,
                        'labor_cost_rate' => 240.0,
                        'machine_cost_rate' => 400.0,
                    ],
                ],
            ]);
            $routing7 = $routingService->create($dtoR7, $tenantId, $userId);
            $routingService->submitApproval($routing7->id, $userId);
        }

        // Helper op maps
        $r1Ops = RoutingOperation::where('routing_id', 1)->get()->keyBy('sequence');
        $r2Ops = RoutingOperation::where('routing_id', $routing2->id)->get()->keyBy('sequence');
        $r3Ops = RoutingOperation::where('routing_id', $routing3->id)->get()->keyBy('sequence');
        $r4Ops = RoutingOperation::where('routing_id', $routing4->id)->get()->keyBy('sequence');
        $r6Ops = RoutingOperation::where('routing_id', $routing6->id)->get()->keyBy('sequence');

        // BOM 2: Wood Table Top Sub-BOM
        $bom2Number = 'BOM-SFG-TBL-TOP';
        $bom2 = ProductionBom::where('bom_number', $bom2Number)->first();
        if (!$bom2) {
            $dtoBom2 = ProductionBomDTO::fromArray([
                'bom_number' => $bom2Number,
                'bom_name' => 'Engineered Wood Table Top Standard Sub-BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $productMap['SFG-TBL-TOP']->id,
                'routing_id' => $routing3->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '1.0.0',
                'effective_date' => $today,
                'notes' => 'Sub-assembly bill of materials for finished wood table top including formula-driven oil sealant.',
                'items' => [
                    [
                        'material_id' => $productMap['RM-TBL-TOP-BOARD']->id,
                        'routing_operation_id' => $r3Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                        'material_scrap_percentage' => 4.0,
                    ],
                    [
                        'material_id' => $productMap['RM-WOOD-OIL']->id,
                        'routing_operation_id' => $r3Ops[30]->id,
                        'quantity' => 0.25,
                        'quantity_type' => 'formula',
                        'formula' => 'base_qty * 0.25',
                        'uom_id' => $uomKg->id,
                        'material_scrap_percentage' => 5.0,
                    ],
                ],
            ]);
            $bom2 = $bomService->create($dtoBom2, $userId);
            $bomService->submitApproval($bom2->id);
            $bom2 = $bomService->approve($bom2->id, $userId);
        }

        // BOM 3: Steel Table Leg Sub-BOM
        $bom3Number = 'BOM-SFG-TBL-LEG';
        $bom3 = ProductionBom::where('bom_number', $bom3Number)->first();
        if (!$bom3) {
            $dtoBom3 = ProductionBomDTO::fromArray([
                'bom_number' => $bom3Number,
                'bom_name' => 'Fabricated Steel Table Leg Set (4 Legs) Sub-BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $productMap['SFG-TBL-LEG']->id,
                'routing_id' => $routing4->id,
                'base_quantity' => 4.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '1.0.0',
                'effective_date' => $today,
                'items' => [
                    [
                        'material_id' => $productMap['RM-TBL-PIPE']->id,
                        'routing_operation_id' => $r4Ops[10]->id,
                        'quantity' => 3.2,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomMtr->id,
                        'material_scrap_percentage' => 3.5,
                    ],
                    [
                        'material_id' => $productMap['RM-PNT-BLK']->id,
                        'routing_operation_id' => $r4Ops[30]->id,
                        'quantity' => 0.6,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomKg->id,
                        'material_scrap_percentage' => 5.0,
                    ],
                ],
            ]);
            $bom3 = $bomService->create($dtoBom3, $userId);
            $bomService->submitApproval($bom3->id);
            $bom3 = $bomService->approve($bom3->id, $userId);
        }

        // BOM 4: Master Multi-Level Finished Good BOM
        $bom4Number = 'BOM-FG-TBL-001';
        $bom4 = ProductionBom::where('bom_number', $bom4Number)->first();
        if (!$bom4) {
            $dtoBom4 = ProductionBomDTO::fromArray([
                'bom_number' => $bom4Number,
                'bom_name' => 'Industrial Dining Table (6-Seater) Multi-Level Master BOM',
                'bom_type' => 'manufacturing',
                'product_id' => $productMap['FG-TBL-001']->id,
                'routing_id' => $routing2->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '1.0.0',
                'effective_date' => $today,
                'notes' => 'Comprehensive multi-level master production BOM exploding into frame assembly, wood top, and alternative stainless fasteners.',
                'items' => [
                    [
                        'material_id' => $productMap['SFG-TBL-FRAME']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                        'child_bom_id' => 1,
                    ],
                    [
                        'material_id' => $productMap['SFG-TBL-TOP']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                        'child_bom_id' => $bom2->id,
                    ],
                    [
                        'material_id' => $productMap['RM-TBL-FASTENER']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                        'row_uid' => 'item-primary-fastener',
                    ],
                    [
                        'material_id' => $productMap['RM-SS-FASTENER']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                        'is_alternative' => true,
                        'replaces_row_uid' => 'item-primary-fastener',
                        'priority' => 1,
                    ],
                    [
                        'material_id' => $productMap['RM-RUB-PAD']->id,
                        'routing_operation_id' => $r2Ops[20]->id,
                        'quantity' => 4.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                        'material_scrap_percentage' => 2.0,
                    ],
                    [
                        'material_id' => $productMap['RM-CORR-BOX']->id,
                        'routing_operation_id' => $r2Ops[40]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                ],
            ]);
            $bom4 = $bomService->create($dtoBom4, $userId);
            $bomService->submitApproval($bom4->id);
            $bom4 = $bomService->approve($bom4->id, $userId);
        }

        // BOM 5: Sales Kit BOM
        $bom5Number = 'BOM-FG-TBL-KIT';
        $bom5 = ProductionBom::where('bom_number', $bom5Number)->first();
        if (!$bom5) {
            $dtoBom5 = ProductionBomDTO::fromArray([
                'bom_number' => $bom5Number,
                'bom_name' => 'DIY Flat-Pack Dining Table Kit Sales/Kit BOM',
                'bom_type' => 'sales',
                'usage_context' => 'manufacturing',
                'product_id' => $productMap['FG-TBL-KIT']->id,
                'routing_id' => $routing2->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '1.0.0',
                'effective_date' => $today,
                'items' => [
                    [
                        'material_id' => $productMap['SFG-TBL-TOP']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['SFG-TBL-LEG']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 4.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['SFG-TBL-SUPPORT']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 2.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['RM-TBL-FASTENER']->id,
                        'routing_operation_id' => $r2Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['RM-CORR-BOX']->id,
                        'routing_operation_id' => $r2Ops[40]->id,
                        'quantity' => 2.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                ],
            ]);
            $bom5 = $bomService->create($dtoBom5, $userId);
            $bomService->submitApproval($bom5->id);
            $bom5 = $bomService->approve($bom5->id, $userId);
        }

        // BOM 6: Phantom BOM
        $bom6Number = 'BOM-SFG-PHANTOM-HW';
        $bom6 = ProductionBom::where('bom_number', $bom6Number)->first();
        if (!$bom6) {
            $dtoBom6 = ProductionBomDTO::fromArray([
                'bom_number' => $bom6Number,
                'bom_name' => 'Hardware Installation Blow-Through Phantom BOM',
                'bom_type' => 'phantom',
                'product_id' => $productMap['SFG-PHANTOM-HW']->id,
                'routing_id' => 1,
                'base_quantity' => 1.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '1.0.0',
                'effective_date' => $today,
                'items' => [
                    [
                        'material_id' => $productMap['RM-TBL-FASTENER']->id,
                        'routing_operation_id' => $r1Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['RM-RUB-PAD']->id,
                        'routing_operation_id' => $r1Ops[20]->id,
                        'quantity' => 4.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                ],
            ]);
            $bom6 = $bomService->create($dtoBom6, $userId);
            $bomService->submitApproval($bom6->id);
            $bom6 = $bomService->approve($bom6->id, $userId);
        }

        // BOM 7: Engineering Draft BOM
        $bom7Number = 'BOM-FG-TBL-PROTO';
        $bom7 = ProductionBom::where('bom_number', $bom7Number)->first();
        if (!$bom7) {
            $dtoBom7 = ProductionBomDTO::fromArray([
                'bom_number' => $bom7Number,
                'bom_name' => 'Ergonomic Height-Adjustable Prototype R&D BOM',
                'bom_type' => 'engineering',
                'usage_context' => 'prototype',
                'product_id' => $productMap['FG-TBL-PROTO']->id,
                'routing_id' => $routing6->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '0.1.0',
                'effective_date' => $today,
                'items' => [
                    [
                        'material_id' => $productMap['RM-TBL-PIPE']->id,
                        'routing_operation_id' => $r6Ops[10]->id,
                        'quantity' => 12.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomMtr->id,
                        'material_scrap_percentage' => 5.0,
                    ],
                    [
                        'material_id' => $productMap['RM-TBL-TOP-BOARD']->id,
                        'routing_operation_id' => $r6Ops[20]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['RM-SS-FASTENER']->id,
                        'routing_operation_id' => $r6Ops[20]->id,
                        'quantity' => 2.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                ],
            ]);
            $bom7 = $bomService->create($dtoBom7, $userId);
        }

        // BOM 8: Subcontracting BOM (Pending Approval)
        $bom8Number = 'BOM-SFG-COAT-LEG';
        $bom8 = ProductionBom::where('bom_number', $bom8Number)->first();
        if (!$bom8) {
            $dtoBom8 = ProductionBomDTO::fromArray([
                'bom_number' => $bom8Number,
                'bom_name' => 'Subcontracted Coated Table Leg BOM',
                'bom_type' => 'subcontracting',
                'product_id' => $productMap['SFG-COAT-LEG']->id,
                'routing_id' => $routing4->id,
                'base_quantity' => 1.0,
                'base_uom_id' => $uomPcs->id,
                'version' => '1.0.0',
                'effective_date' => $today,
                'items' => [
                    [
                        'material_id' => $productMap['SFG-TBL-LEG']->id,
                        'routing_operation_id' => $r4Ops[10]->id,
                        'quantity' => 1.0,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomPcs->id,
                    ],
                    [
                        'material_id' => $productMap['RM-PNT-BLK']->id,
                        'routing_operation_id' => $r4Ops[30]->id,
                        'quantity' => 0.15,
                        'quantity_type' => 'fixed',
                        'uom_id' => $uomKg->id,
                        'material_scrap_percentage' => 5.0,
                    ],
                ],
            ]);
            $bom8 = $bomService->create($dtoBom8, $userId);
            $bomService->submitApproval($bom8->id);
        }
    }
}

