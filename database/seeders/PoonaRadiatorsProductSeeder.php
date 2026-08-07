<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PoonaRadiatorsProductSeeder extends Seeder
{
    /**
     * Run the database seeds for Poona Radiators & Oil Coolers manufacturing demo product flow.
     */
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();

        if (!$tenant) {
            $this->command->error('No tenant found. Please ensure a tenant exists before running this seeder.');
            return;
        }

        $tenantId = $tenant->id;
        $user = User::where('tenant_id', $tenantId)->first() ?? User::first();
        $userId = $user?->id ?? 1;

        DB::transaction(function () use ($tenantId, $userId) {
            $this->command->info('Seeding Accounting Chart of Accounts...');
            $accounts = $this->ensureChartOfAccounts($tenantId);

            $this->command->info('Seeding Units of Measurement (UOMs)...');
            $uoms = $this->ensureUoms($tenantId);

            $this->command->info('Seeding Warehouses...');
            $warehouses = $this->ensureWarehouses($tenantId);

            $this->command->info('Seeding Poona Radiators Products (Raw Materials, Sub-Assemblies, Finished Goods)...');
            $products = $this->seedProducts($tenantId, $uoms, $accounts);

            $this->command->info('Seeding Stock Balances and Initial Stock Transactions...');
            $this->seedInitialStock($tenantId, $products, $warehouses);

            $this->command->info('Seeding Manufacturing Bill of Materials (BOM)...');
            $this->seedBillOfMaterials($tenantId, $products, $uoms, $userId);
        });

        $this->command->info('Poona Radiators End-to-End Product, BOM, Inventory & Accounting Seeder completed successfully!');
    }

    /**
     * Ensure standard Chart of Accounts exist for Inventory Asset, Sales Revenue, and Cost of Goods Sold.
     */
    private function ensureChartOfAccounts(int $tenantId): array
    {
        $accounts = [];

        // 1. Inventory Asset (1200)
        $inventoryAcc = ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => '1200'],
            [
                'name' => 'Inventory Asset',
                'type' => ChartOfAccount::TYPE_ASSET,
                'subtype' => 'current_asset',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $accounts['inventory'] = $inventoryAcc->id;

        // 2. Sales Revenue (4010)
        $salesAcc = ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => '4010'],
            [
                'name' => 'Sales Revenue',
                'type' => ChartOfAccount::TYPE_INCOME,
                'subtype' => 'operating_income',
                'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $accounts['sales'] = $salesAcc->id;

        // 3. Purchase / Cost of Goods Sold (5010)
        $cogsAcc = ChartOfAccount::firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => '5010'],
            [
                'name' => 'Cost of Goods Sold (Purchase/Goods)',
                'type' => ChartOfAccount::TYPE_EXPENSE,
                'subtype' => 'cogs',
                'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
                'is_system' => true,
                'is_active' => true,
            ]
        );
        $accounts['purchase'] = $cogsAcc->id;

        return $accounts;
    }

    /**
     * Ensure UOMs exist (Pcs, Kg, Mtr).
     */
    private function ensureUoms(int $tenantId): array
    {
        $uoms = [];

        $units = [
            ['name' => 'Pieces', 'code' => 'Pcs', 'category' => 'Goods'],
            ['name' => 'Kilograms', 'code' => 'Kg', 'category' => 'Goods'],
            ['name' => 'Meters', 'code' => 'Mtr', 'category' => 'Goods'],
        ];

        foreach ($units as $u) {
            $uom = Uom::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $u['code']],
                ['name' => $u['name'], 'category' => $u['category']]
            );
            $uoms[$u['code']] = $uom->id;
        }

        return $uoms;
    }

    /**
     * Ensure Warehouses exist (RM Store, WIP Store, Finished Goods Store).
     */
    private function ensureWarehouses(int $tenantId): array
    {
        $warehouses = [];

        $whList = [
            ['name' => 'Main Raw Material Store', 'code' => 'WH-RM-01', 'is_default' => true],
            ['name' => 'Production WIP Store', 'code' => 'WH-WIP-01', 'is_default' => false],
            ['name' => 'Finished Goods Warehouse', 'code' => 'WH-FG-01', 'is_default' => false],
        ];

        foreach ($whList as $w) {
            $wh = Warehouse::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $w['code']],
                [
                    'name' => $w['name'],
                    'status' => 'active',
                    'address' => 'Plot No. 42, Chakan Industrial Area, Phase II, Pune, Maharashtra - 410501',
                    'is_default' => $w['is_default'],
                ]
            );
            $warehouses[$w['code']] = $wh->id;
        }

        return $warehouses;
    }

    /**
     * Seed Products (Raw Materials, Sub-Assemblies, Finished Goods for Poona Radiators).
     */
    private function seedProducts(int $tenantId, array $uoms, array $accounts): array
    {
        $products = [];

        $items = [
            // ── RAW MATERIALS (RM) ──
            'rm_fin' => [
                'name' => 'Corrugated Aluminum Cooling Fins (0.1mm Coil)',
                'sku' => 'PR-RM-ALU-FIN-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Kg'],
                'cost_price' => 320.00,
                'selling_price' => 0.00,
                'reorder_point' => 100.00,
                'opening_stock' => 450.00,
                'opening_stock_rate' => 320.00,
                'hsn_sac' => '76061100',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'High conductivity corrugated aluminum alloy 3003 foil for radiator cooling fin matrix.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_tube' => [
                'name' => 'Extruded Aluminum Radiator Tubes (Flat Oval 2-Row)',
                'sku' => 'PR-RM-ALU-TUBE-F',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Mtr'],
                'cost_price' => 145.00,
                'selling_price' => 0.00,
                'reorder_point' => 300.00,
                'opening_stock' => 1200.00,
                'opening_stock_rate' => 145.00,
                'hsn_sac' => '76082000',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Precision seam welded flat oval aluminum tubes for heavy duty coolant circulation.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_plate' => [
                'name' => 'Top & Bottom Header Tank Plates (3mm Aluminum Sheet)',
                'sku' => 'PR-RM-TANK-PLATE',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 850.00,
                'selling_price' => 0.00,
                'reorder_point' => 20.00,
                'opening_stock' => 100.00,
                'opening_stock_rate' => 850.00,
                'hsn_sac' => '76109090',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'CNC punched 3mm thick aluminum header plates for tube slotted assembly.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_nozzle' => [
                'name' => 'Coolant Inlet/Outlet Brass Nozzles (45mm Flange)',
                'sku' => 'PR-RM-NOZZLE-45',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 380.00,
                'selling_price' => 0.00,
                'reorder_point' => 30.00,
                'opening_stock' => 150.00,
                'opening_stock_rate' => 380.00,
                'hsn_sac' => '84819090',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Heavy duty machined brass coolant connection hose nipples.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_flux' => [
                'name' => 'Nocolok Flux & Brazing Alloy Powder',
                'sku' => 'PR-RM-BRAZE-FLUX',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Kg'],
                'cost_price' => 1250.00,
                'selling_price' => 0.00,
                'reorder_point' => 15.00,
                'opening_stock' => 80.00,
                'opening_stock_rate' => 1250.00,
                'hsn_sac' => '38101000',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Non-corrosive potassium fluoroaluminate flux for controlled atmosphere CAB furnace brazing.',
                'warehouse' => 'WH-RM-01',
            ],

            // ── SUB-ASSEMBLIES (WIP / COMPONENT) ──
            'sub_core' => [
                'name' => 'Radiator Aluminum Core Sub-Assembly (Brazed Matrix)',
                'sku' => 'PR-SUB-CORE-750',
                'type' => 'component',
                'planning_type' => 'manufacture',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 11400.00,
                'selling_price' => 16500.00,
                'reorder_point' => 10.00,
                'opening_stock' => 25.00,
                'opening_stock_rate' => 11400.00,
                'hsn_sac' => '87089100',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Fully CAB furnace brazed radiator core block comprising aluminum tubes, corrugated fins & flux.',
                'warehouse' => 'WH-WIP-01',
            ],

            // ── FINISHED GOODS (FG) ──
            'fg_radiator' => [
                'name' => 'Heavy Duty Aluminum Radiator Assembly (Model: PR-RAD-750)',
                'sku' => 'PR-FG-RAD-750',
                'type' => 'finished_good',
                'planning_type' => 'manufacture',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 18200.00,
                'selling_price' => 28500.00,
                'reorder_point' => 5.00,
                'opening_stock' => 15.00,
                'opening_stock_rate' => 18200.00,
                'hsn_sac' => '87089100',
                'gst_rate' => 18.00,
                'track_serial_number' => false,
                'track_batch' => false,
                'description' => 'Poona Radiators & Oil Coolers Heavy-Duty Aluminum Radiator Assembly suitable for 500 kVA Generator / Heavy Commercial Vehicle OEMs.',
                'warehouse' => 'WH-FG-01',
            ],
        ];

        foreach ($items as $key => $item) {
            $product = Product::updateOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'planning_type' => $item['planning_type'],
                    'item_type' => 'Goods',
                    'variation_type' => 'Single',
                    'uom_id' => $item['uom_id'],
                    'status' => 'active',
                    'unit_cost' => $item['cost_price'],
                    'cost_price' => $item['cost_price'],
                    'selling_price' => $item['selling_price'],
                    'hsn_sac' => $item['hsn_sac'],
                    'gst_rate' => $item['gst_rate'],
                    'sales_account' => $accounts['sales'],
                    'purchase_account' => $accounts['purchase'],
                    'inventory_account' => $accounts['inventory'],
                    'reorder_point' => $item['reorder_point'],
                    'opening_stock' => $item['opening_stock'],
                    'opening_stock_rate' => $item['opening_stock_rate'],
                    'description' => $item['description'],
                    'brand' => 'Poona Radiators',
                    'manufacturer' => 'Poona Radiators & Oil Coolers Ltd.',
                    'track_batch' => $item['track_batch'] ?? false,
                    'track_serial_number' => $item['track_serial_number'] ?? false,
                    'inventory_valuation_method' => 'FIFO',
                ]
            );

            $products[$key] = [
                'model' => $product,
                'warehouse_code' => $item['warehouse'],
            ];
        }

        return $products;
    }

    /**
     * Seed initial opening stock balances in product_warehouse_stocks and record stock_transactions.
     */
    private function seedInitialStock(int $tenantId, array $products, array $warehouses): void
    {
        foreach ($products as $key => $pInfo) {
            /** @var Product $product */
            $product = $pInfo['model'];
            $warehouseId = $warehouses[$pInfo['warehouse_code']];
            $qty = $product->opening_stock;
            $rate = $product->opening_stock_rate;
            $totalValue = $qty * $rate;

            // 1. Update/Create Product Warehouse Stock Balance
            ProductWarehouseStock::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'quantity' => $qty,
                    'available_qty' => $qty,
                    'reserved_qty' => 0.00,
                    'unit_cost' => $rate,
                ]
            );

            // 2. Record Stock Transaction Audit Log
            StockTransaction::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'reference_type' => 'Opening Stock',
                ],
                [
                    'type' => 'IN',
                    'reference_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $rate,
                    'total_value' => $totalValue,
                    'balance_qty' => $qty,
                ]
            );
        }
    }

    /**
     * Seed Multi-Level Manufacturing Bill of Materials (BOM) for Radiator Assembly.
     */
    private function seedBillOfMaterials(int $tenantId, array $products, array $uoms, int $userId): void
    {
        // ── LEVEL 1 BOM: Radiator Core Sub-Assembly ──
        $subCoreProduct = $products['sub_core']['model'];

        $coreBom = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-SUB-CORE-01'],
            [
                'bom_name' => 'BOM - Radiator Aluminum Core Sub-Assembly',
                'bom_type' => 'manufacturing',
                'usage_context' => 'manufacturing',
                'product_id' => $subCoreProduct->id,
                'base_quantity' => 1.00,
                'base_uom_id' => $uoms['Pcs'],
                'version' => '1.0',
                'revision' => 0,
                'revision_reason' => 'Standard OEM Production Release',
                'effective_date' => now(),
                'status' => 'approved',
                'notes' => 'Primary furnace brazing BOM for aluminum cooling core matrix.',
                'created_by' => $userId,
            ]
        );

        // Core BOM Raw Material Ingredients:
        $coreMaterials = [
            ['key' => 'rm_fin', 'qty' => 8.50, 'uom' => 'Kg', 'scrap' => 2.00, 'seq' => 1],
            ['key' => 'rm_tube', 'qty' => 32.00, 'uom' => 'Mtr', 'scrap' => 1.50, 'seq' => 2],
            ['key' => 'rm_flux', 'qty' => 1.20, 'uom' => 'Kg', 'scrap' => 0.50, 'seq' => 3],
        ];

        foreach ($coreMaterials as $mat) {
            ProductionBomItem::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'bom_id' => $coreBom->id,
                    'material_id' => $products[$mat['key']]['model']->id,
                ],
                [
                    'quantity' => $mat['qty'],
                    'uom_id' => $uoms[$mat['uom']],
                    'material_scrap_percentage' => $mat['scrap'],
                    'sequence' => $mat['seq'],
                    'notes' => 'Raw material consumed during tube-fin assembly & CAB brazing.',
                ]
            );
        }

        // ── LEVEL 2 BOM: Heavy Duty Finished Radiator Assembly ──
        $fgRadiatorProduct = $products['fg_radiator']['model'];

        $fgBom = ProductionBom::updateOrCreate(
            ['tenant_id' => $tenantId, 'bom_number' => 'BOM-FG-RAD-750'],
            [
                'bom_name' => 'BOM - Heavy Duty Aluminum Radiator Assembly (PR-RAD-750)',
                'bom_type' => 'manufacturing',
                'usage_context' => 'manufacturing',
                'product_id' => $fgRadiatorProduct->id,
                'base_quantity' => 1.00,
                'base_uom_id' => $uoms['Pcs'],
                'version' => '1.0',
                'revision' => 0,
                'revision_reason' => 'Final Finished Good Assembly BOM for 500 kVA Genset/Commercial OEM',
                'effective_date' => now(),
                'status' => 'approved',
                'notes' => 'Final assembly BOM including core, header plates, and brass inlet/outlet nozzles.',
                'created_by' => $userId,
            ]
        );

        // FG Radiator Assembly Component Ingredients:
        $fgMaterials = [
            ['key' => 'sub_core', 'qty' => 1.00, 'uom' => 'Pcs', 'scrap' => 0.00, 'seq' => 1, 'child_bom' => $coreBom->id],
            ['key' => 'rm_plate', 'qty' => 2.00, 'uom' => 'Pcs', 'scrap' => 0.00, 'seq' => 2, 'child_bom' => null],
            ['key' => 'rm_nozzle', 'qty' => 2.00, 'uom' => 'Pcs', 'scrap' => 0.00, 'seq' => 3, 'child_bom' => null],
        ];

        foreach ($fgMaterials as $mat) {
            ProductionBomItem::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'bom_id' => $fgBom->id,
                    'material_id' => $products[$mat['key']]['model']->id,
                ],
                [
                    'child_bom_id' => $mat['child_bom'],
                    'quantity' => $mat['qty'],
                    'uom_id' => $uoms[$mat['uom']],
                    'material_scrap_percentage' => $mat['scrap'],
                    'sequence' => $mat['seq'],
                    'notes' => 'Component required for final radiator tank fitting and pressure leak test.',
                ]
            );
        }
    }
}
