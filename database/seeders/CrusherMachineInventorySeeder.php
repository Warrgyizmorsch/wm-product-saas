<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrusherMachineInventorySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();

        if (!$tenant) {
            $this->command->warn('No tenant found to seed Crusher Machine inventory.');
            return;
        }

        // 1. Resolve or Create Warehouses
        $mainWh = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH-MAIN'],
            ['name' => 'Main Manufacturing Plant', 'address' => 'Plot 42, Heavy Industrial Area', 'is_default' => true, 'status' => 'active']
        );

        $fgWh = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH-FG'],
            ['name' => 'Finished Machinery Yard', 'address' => 'Plot 45, Heavy Industrial Area', 'is_default' => false, 'status' => 'active']
        );

        // Remove extra FG if exists so only 1 main Finished Good remains
        Product::where('tenant_id', $tenant->id)->where('sku', 'FG-CRUSHER-JAW-3624')->delete();

        // 2. Resolve or Create UOMs
        $pcs = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'PCS'], ['name' => 'Pieces']);
        $kgs = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'KGS'], ['name' => 'Kilograms']);
        $sets = Uom::firstOrCreate(['tenant_id' => $tenant->id, 'code' => 'SET'], ['name' => 'Sets']);

        // 3. Define Crusher Machine Products (1 Finished Good + Sub-assemblies + Raw Materials)
        $items = [
            // ── RAW MATERIALS (Bought-Out / Raw Steel) ──────────────────────
            [
                'sku' => 'RM-STEEL-PLT-40',
                'name' => 'Heavy 40mm IS2062 Steel Plate',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $kgs->id,
                'cost_price' => 65.00,
                'selling_price' => 0.00,
                'opening_stock' => 5000.00,
                'reorder_point' => 1000.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-STEEL-PLT-25',
                'name' => '25mm IS2062 Structural MS Plate',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $kgs->id,
                'cost_price' => 62.00,
                'selling_price' => 0.00,
                'opening_stock' => 3500.00,
                'reorder_point' => 800.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-STEEL-CHNL-300',
                'name' => 'ISMC 300 Heavy Channel Section',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $kgs->id,
                'cost_price' => 68.00,
                'selling_price' => 0.00,
                'opening_stock' => 2000.00,
                'reorder_point' => 500.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-JAW-FIXED-MN',
                'name' => 'Mn18Cr2 High Manganese Fixed Jaw Plate',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $pcs->id,
                'cost_price' => 45000.00,
                'selling_price' => 0.00,
                'opening_stock' => 8.00,
                'reorder_point' => 3.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-JAW-MOVING-MN',
                'name' => 'Mn18Cr2 High Manganese Swing Jaw Plate',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $pcs->id,
                'cost_price' => 48000.00,
                'selling_price' => 0.00,
                'opening_stock' => 8.00,
                'reorder_point' => 3.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-SHFT-ECC-42CR',
                'name' => '42CrMo4 Forged Steel Eccentric Shaft',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $pcs->id,
                'cost_price' => 85000.00,
                'selling_price' => 0.00,
                'opening_stock' => 4.00,
                'reorder_point' => 2.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-BRG-SKF-22330',
                'name' => 'SKF 22330 Spherical Heavy Duty Roller Bearing',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $pcs->id,
                'cost_price' => 28000.00,
                'selling_price' => 0.00,
                'opening_stock' => 16.00,
                'reorder_point' => 6.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'RM-MTR-75HP-3PH',
                'name' => '75 HP 3-Phase IE3 Induction Motor (1440 RPM)',
                'type' => 'raw_material',
                'supplier_method' => 'buy',
                'uom_id' => $pcs->id,
                'cost_price' => 125000.00,
                'selling_price' => 0.00,
                'opening_stock' => 3.00,
                'reorder_point' => 2.00,
                'warehouse_id' => $mainWh->id,
            ],

            // ── SEMI-FINISHED GOODS (Sub-Assemblies / Manufactured) ─────────
            [
                'sku' => 'SF-CRUSHER-FRAME',
                'name' => 'Heavy Fabricated Steel Crusher Frame Chassis',
                'type' => 'semi_finished',
                'supplier_method' => 'manufacture',
                'uom_id' => $pcs->id,
                'cost_price' => 245000.00,
                'selling_price' => 0.00,
                'opening_stock' => 2.00,
                'reorder_point' => 1.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'SF-PITMAN-BEARING-ASSY',
                'name' => 'Pitman & Eccentric Shaft Bearing Sub-Assembly',
                'type' => 'semi_finished',
                'supplier_method' => 'manufacture',
                'uom_id' => $pcs->id,
                'cost_price' => 320000.00,
                'selling_price' => 0.00,
                'opening_stock' => 3.00,
                'reorder_point' => 1.00,
                'warehouse_id' => $mainWh->id,
            ],
            [
                'sku' => 'SF-TOGGLE-SAFETY-SET',
                'name' => 'Toggle Plate & Tension Spring Safety Assembly',
                'type' => 'semi_finished',
                'supplier_method' => 'manufacture',
                'uom_id' => $sets->id,
                'cost_price' => 65000.00,
                'selling_price' => 0.00,
                'opening_stock' => 5.00,
                'reorder_point' => 2.00,
                'warehouse_id' => $mainWh->id,
            ],

            // ── MAIN FINISHED GOOD (Single Crusher Machine Final Product) ────
            [
                'sku' => 'FG-CRUSHER-JAW-3020',
                'name' => 'Heavy Duty Industrial Stone Jaw Crusher (30" x 20")',
                'type' => 'finished_good',
                'supplier_method' => 'manufacture',
                'uom_id' => $pcs->id,
                'cost_price' => 850000.00,
                'selling_price' => 1250000.00,
                'opening_stock' => 2.00,
                'reorder_point' => 1.00,
                'warehouse_id' => $fgWh->id,
            ],
        ];

        // 4. Seed Products and Warehouse Stocks
        foreach ($items as $data) {
            $whId = $data['warehouse_id'];
            unset($data['warehouse_id']);

            $product = Product::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'sku' => $data['sku'],
                ],
                array_merge($data, [
                    'tenant_id' => $tenant->id,
                    'item_type' => 'Goods',
                    'variation_type' => 'Single',
                    'status' => 'active',
                    'opening_stock_rate' => $data['cost_price'],
                ])
            );

            // Seed Product Warehouse Stock record
            ProductWarehouseStock::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $whId,
                ],
                [
                    'quantity' => $data['opening_stock'],
                    'unit_cost' => $data['cost_price'],
                ]
            );
        }

        $this->command->info('Crusher Machine Inventory seeded cleanly with 1 Finished Good, Sub-Assemblies, and Raw Materials!');
    }
}
