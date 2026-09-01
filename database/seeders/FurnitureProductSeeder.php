<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FurnitureProductSeeder extends Seeder
{
    /**
     * Run the database seeds for Wooden & Upholstered Furniture manufacturing demo product flow.
     */
    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            $this->command->error('No tenant found. Please ensure a tenant exists before running this seeder.');
            return;
        }

        $tenantId = $tenant->id;
        $user = User::where('tenant_id', $tenantId)->first() ?? User::first();
        $userId = $user?->id ?? 1;

        DB::transaction(function () use ($tenantId, $userId) {
            $this->cleanupPreviousData($tenantId);
            $accounts = $this->ensureChartOfAccounts($tenantId);
            $uoms = $this->ensureUoms($tenantId);
            $warehouses = $this->ensureWarehouses($tenantId);
            $products = $this->seedProducts($tenantId, $uoms, $accounts);
            $this->seedInitialStock($tenantId, $products, $warehouses);
        });
    }

    /**
     * Clean up previous stock transactions and warehouse stock balances for Furniture products.
     */
    private function cleanupPreviousData(int $tenantId): void
    {
        $skus = [
            'FURN-RM-TIMBER-01',
            'FURN-RM-VENEER-01',
            'FURN-RM-HARDWARE-01',
            'FURN-RM-LACQUER-01',
            'FURN-RM-FOAM-01',
            'FURN-RM-FABRIC-01',
            'FURN-RM-GLUE-01',
            'FURN-SUB-LEG-FRAME',
            'FURN-SUB-CUSHION-SEAT',
            'FURN-FG-CHAIR-EXEC',
            'FURN-FG-TABLE-LUX',
        ];

        $productIds = Product::where('tenant_id', $tenantId)
            ->whereIn('sku', $skus)
            ->pluck('id')
            ->toArray();

        if (!empty($productIds)) {
            StockTransaction::where('tenant_id', $tenantId)->whereIn('product_id', $productIds)->delete();
            ProductWarehouseStock::where('tenant_id', $tenantId)->whereIn('product_id', $productIds)->delete();
        }
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
     * Ensure UOMs exist (Pcs, Kg, Mtr, Ltr, Set).
     */
    private function ensureUoms(int $tenantId): array
    {
        $uoms = [];

        $units = [
            ['name' => 'Pieces', 'code' => 'Pcs', 'category' => 'Goods'],
            ['name' => 'Kilograms', 'code' => 'Kg', 'category' => 'Goods'],
            ['name' => 'Meters', 'code' => 'Mtr', 'category' => 'Goods'],
            ['name' => 'Liters', 'code' => 'Ltr', 'category' => 'Goods'],
            ['name' => 'Set', 'code' => 'Set', 'category' => 'Goods'],
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
                    'address' => 'Furniture Park Sector 18, MIDC Industrial Area, Pune, Maharashtra - 411018',
                    'is_default' => $w['is_default'],
                ]
            );
            $warehouses[$w['code']] = $wh->id;
        }

        return $warehouses;
    }

    /**
     * Seed Products (Raw Materials, Sub-Assemblies, Finished Goods for Furniture Manufacturing).
     */
    private function seedProducts(int $tenantId, array $uoms, array $accounts): array
    {
        $products = [];

        $items = [
            // ── RAW MATERIALS (RM) ──
            'rm_timber' => [
                'name' => 'Seasoned Teak Wood Planks (50mm x 150mm x 2400mm)',
                'sku' => 'FURN-RM-TIMBER-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Mtr'],
                'cost_price' => 450.00,
                'selling_price' => 0.00,
                'reorder_point' => 150.00,
                'opening_stock' => 800.00,
                'opening_stock_rate' => 450.00,
                'hsn_sac' => '44071100',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Kiln-dried premium grade Burma Teak wood timber planks for structural furniture frames.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_veneer' => [
                'name' => 'Natural Oak Veneer Sheet (4ft x 8ft x 0.6mm)',
                'sku' => 'FURN-RM-VENEER-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 850.00,
                'selling_price' => 0.00,
                'reorder_point' => 50.00,
                'opening_stock' => 200.00,
                'opening_stock_rate' => 850.00,
                'hsn_sac' => '44089090',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Decorative crown-cut natural American Oak wood veneer sheets for table top lamination.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_hardware' => [
                'name' => 'Stainless Steel Joinery & Fastener Kit (M8 Dowels & Bolts)',
                'sku' => 'FURN-RM-HARDWARE-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Set'],
                'cost_price' => 120.00,
                'selling_price' => 0.00,
                'reorder_point' => 100.00,
                'opening_stock' => 500.00,
                'opening_stock_rate' => 120.00,
                'hsn_sac' => '83024200',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Heavy duty SS 304 concealed connecting bolts, wooden dowel pins & bracket assembly hardware.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_lacquer' => [
                'name' => 'Clear Polyurethane Matte Finish Lacquer',
                'sku' => 'FURN-RM-LACQUER-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Ltr'],
                'cost_price' => 680.00,
                'selling_price' => 0.00,
                'reorder_point' => 30.00,
                'opening_stock' => 150.00,
                'opening_stock_rate' => 680.00,
                'hsn_sac' => '32089090',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Scratch resistant non-yellowing 2-pack PU spray coating polish for premium wood finish.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_foam' => [
                'name' => 'High Resilience Ergonomic Cushion Foam Block (40 Density)',
                'sku' => 'FURN-RM-FOAM-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 520.00,
                'selling_price' => 0.00,
                'reorder_point' => 40.00,
                'opening_stock' => 180.00,
                'opening_stock_rate' => 520.00,
                'hsn_sac' => '39211390',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Pre-contoured molded HR polyurethane foam core for executive seating support.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_fabric' => [
                'name' => 'Premium Upholstery Velvet Fabric Roll (Dark Slate Grey)',
                'sku' => 'FURN-RM-FABRIC-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Mtr'],
                'cost_price' => 380.00,
                'selling_price' => 0.00,
                'reorder_point' => 60.00,
                'opening_stock' => 300.00,
                'opening_stock_rate' => 380.00,
                'hsn_sac' => '54077200',
                'gst_rate' => 12.00,
                'track_batch' => false,
                'description' => 'Stain resistant commercial grade woven upholstery fabric with Martindale rating 50,000 rubs.',
                'warehouse' => 'WH-RM-01',
            ],
            'rm_glue' => [
                'name' => 'Industrial D3 Waterproof Wood Adhesive Glue',
                'sku' => 'FURN-RM-GLUE-01',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Kg'],
                'cost_price' => 240.00,
                'selling_price' => 0.00,
                'reorder_point' => 25.00,
                'opening_stock' => 100.00,
                'opening_stock_rate' => 240.00,
                'hsn_sac' => '35069190',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Fast-curing PVA resin emulsion adhesive for structural woodworking and joint bonding.',
                'warehouse' => 'WH-RM-01',
            ],

            // ── SUB-ASSEMBLIES (WIP / COMPONENT) ──
            'sub_leg_frame' => [
                'name' => 'Chair Leg Frame Sub-Assembly (CNC Machined & Sanded)',
                'sku' => 'FURN-SUB-LEG-FRAME',
                'type' => 'semi_finished',
                'planning_type' => 'manufacture',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 2850.00,
                'selling_price' => 4200.00,
                'reorder_point' => 20.00,
                'opening_stock' => 40.00,
                'opening_stock_rate' => 2850.00,
                'hsn_sac' => '94039000',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Precision mortise-and-tenon jointed teak wood leg frame structure for executive chairs.',
                'warehouse' => 'WH-WIP-01',
            ],
            'sub_cushion_seat' => [
                'name' => 'Upholstered Foam Cushion Seat Sub-Assembly',
                'sku' => 'FURN-SUB-CUSHION-SEAT',
                'type' => 'semi_finished',
                'planning_type' => 'manufacture',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 1650.00,
                'selling_price' => 2500.00,
                'reorder_point' => 15.00,
                'opening_stock' => 30.00,
                'opening_stock_rate' => 1650.00,
                'hsn_sac' => '94039000',
                'gst_rate' => 18.00,
                'track_batch' => false,
                'description' => 'Fully wrapped velvet upholstered HR foam seat cushion with internal plywood base.',
                'warehouse' => 'WH-WIP-01',
            ],

            // ── FINISHED GOODS (FG) ──
            'fg_chair' => [
                'name' => 'Executive Ergonomic Wooden Armchair (Model: FE-CHR-900)',
                'sku' => 'FURN-FG-CHAIR-EXEC',
                'type' => 'finished_good',
                'planning_type' => 'manufacture',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 7400.00,
                'selling_price' => 14500.00,
                'reorder_point' => 10.00,
                'opening_stock' => 25.00,
                'opening_stock_rate' => 7400.00,
                'hsn_sac' => '94016100',
                'gst_rate' => 18.00,
                'track_serial_number' => false,
                'track_batch' => false,
                'description' => 'Premium handcrafted solid teak wood executive armchair with ergonomic upholstered velvet cushion seat and matte PU lacquer finish.',
                'warehouse' => 'WH-FG-01',
            ],
            'fg_table' => [
                'name' => 'Luxury 6-Seater Teak & Oak Dining Table (Model: FE-TBL-1800)',
                'sku' => 'FURN-FG-TABLE-LUX',
                'type' => 'finished_good',
                'planning_type' => 'manufacture',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 18500.00,
                'selling_price' => 38000.00,
                'reorder_point' => 5.00,
                'opening_stock' => 12.00,
                'opening_stock_rate' => 18500.00,
                'hsn_sac' => '94036000',
                'gst_rate' => 18.00,
                'track_serial_number' => false,
                'track_batch' => false,
                'description' => 'Contemporary 6-seater solid teak wood dining table with American Oak veneered top panel and scratch-resistant PU spray finish.',
                'warehouse' => 'WH-FG-01',
            ],
        ];

        $company = DB::table('companies')->where('tenant_id', $tenantId)->first();
        $companyId = $company?->id ?? 1;
        $branch = DB::table('branches')->where('company_id', $companyId)->first();
        $branchId = $branch?->id ?? 1;

        foreach ($items as $key => $item) {
            $product = Product::updateOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $item['sku']],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
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
                    'brand' => 'Furniture Craft ERP',
                    'manufacturer' => 'Furniture Craft Industries Ltd.',
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
        $company = DB::table('companies')->where('tenant_id', $tenantId)->first();
        $companyId = $company?->id ?? 1;
        $branch = DB::table('branches')->where('company_id', $companyId)->first();
        $branchId = $branch?->id ?? 1;

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
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
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
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
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
}
