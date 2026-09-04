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
use Illuminate\Support\Facades\Schema;

class TableManufacturingProductSeeder extends Seeder
{
    /**
     * Run the database seeds for Dining Table Manufacturing demo product flow.
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
     * Clean up previous stock transactions, warehouse stock balances, and products for Table Manufacturing.
     */
    private function cleanupPreviousData(int $tenantId): void
    {
        Schema::disableForeignKeyConstraints();

        $productIds = Product::where('tenant_id', $tenantId)
            ->pluck('id')
            ->toArray();

        if (!empty($productIds)) {
            StockTransaction::where('tenant_id', $tenantId)->delete();
            ProductWarehouseStock::where('tenant_id', $tenantId)->delete();
            Product::where('tenant_id', $tenantId)->forceDelete();
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Ensure standard Chart of Accounts exist for Inventory Asset, Sales Revenue, and Cost of Goods Sold.
     */
    private function ensureChartOfAccounts(int $tenantId): array
    {
        $accounts = [];

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
     * Ensure UOMs exist (Pcs, Mtr, Set, Kg).
     */
    private function ensureUoms(int $tenantId): array
    {
        $uoms = [];

        $units = [
            ['name' => 'Pieces', 'code' => 'Pcs', 'category' => 'unit'],
            ['name' => 'Meters', 'code' => 'Mtr', 'category' => 'length'],
            ['name' => 'Set', 'code' => 'Set', 'category' => 'unit'],
            ['name' => 'Kilograms', 'code' => 'Kg', 'category' => 'weight'],
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
     * Ensure standard warehouses exist.
     */
    private function ensureWarehouses(int $tenantId): array
    {
        $warehouses = [];

        $whList = [
            [
                'name' => 'Central Raw Material Store',
                'code' => 'WH-RM-TBL',
                'type' => 'raw_material',
            ],
            [
                'name' => 'Work-In-Progress Store',
                'code' => 'WH-WIP-TBL',
                'type' => 'wip',
            ],
            [
                'name' => 'Finished Goods Warehouse',
                'code' => 'WH-FG-TBL',
                'type' => 'finished_goods',
            ],
        ];

        foreach ($whList as $w) {
            $wh = Warehouse::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $w['code']],
                [
                    'name' => $w['name'],
                    'type' => $w['type'],
                    'status' => 'active',
                ]
            );
            $warehouses[$w['code']] = $wh->id;
        }

        return $warehouses;
    }

    /**
     * Seed Products for Industrial Dining Table manufacturing flow.
     */
    private function seedProducts(int $tenantId, array $uoms, array $accounts): array
    {
        $items = [
            'rm_pipe' => [
                'name' => 'Steel Square Pipe Heavy Stock (50x50mm x 2mm)',
                'sku' => 'RM-TBL-PIPE',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Mtr'],
                'cost_price' => 300.00,
                'selling_price' => 0.00,
                'reorder_point' => 50.00,
                'opening_stock' => 200.00,
                'opening_stock_rate' => 300.00,
                'hsn_sac' => '73066100',
                'gst_rate' => 18.00,
                'description' => 'Heavy duty structural steel square hollow section pipe for table leg and frame fabrication.',
                'warehouse' => 'WH-RM-TBL',
            ],
            'rm_top_board' => [
                'name' => 'Engineered Wood Table Top Board (1800x900mm)',
                'sku' => 'RM-TBL-TOP-BOARD',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 2200.00,
                'selling_price' => 0.00,
                'reorder_point' => 15.00,
                'opening_stock' => 50.00,
                'opening_stock_rate' => 2200.00,
                'hsn_sac' => '44111400',
                'gst_rate' => 18.00,
                'description' => 'High-density moisture-resistant engineered wood board for dining table top processing.',
                'warehouse' => 'WH-RM-TBL',
            ],
            'rm_fastener' => [
                'name' => 'Dining Table Fastener & Hardware Set',
                'sku' => 'RM-TBL-FASTENER',
                'type' => 'raw_material',
                'planning_type' => 'purchase',
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 250.00,
                'selling_price' => 0.00,
                'reorder_point' => 30.00,
                'opening_stock' => 100.00,
                'opening_stock_rate' => 250.00,
                'hsn_sac' => '73181500',
                'gst_rate' => 18.00,
                'description' => 'High tensile M8 hex bolts, locking nuts, spring washers, and leveling foot pads.',
                'warehouse' => 'WH-RM-TBL',
            ],
            'sfg_leg' => [
                'name' => 'Fabricated Steel Table Leg (750mm)',
                'sku' => 'SFG-TBL-LEG',
                'type' => 'semi_finished',
                'planning_type' => 'manufacture',
                'default_production_model' => Product::MODEL_PURE_MANUFACTURING,
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 600.00,
                'selling_price' => 900.00,
                'reorder_point' => 20.00,
                'opening_stock' => 0.00,
                'opening_stock_rate' => 600.00,
                'hsn_sac' => '94039000',
                'gst_rate' => 18.00,
                'description' => 'Precision cut and deburred 750mm steel table leg component.',
                'warehouse' => 'WH-WIP-TBL',
            ],
            'sfg_support' => [
                'name' => 'Horizontal Support Beam',
                'sku' => 'SFG-TBL-SUPPORT',
                'type' => 'semi_finished',
                'planning_type' => 'manufacture',
                'default_production_model' => Product::MODEL_PURE_MANUFACTURING,
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 400.00,
                'selling_price' => 600.00,
                'reorder_point' => 10.00,
                'opening_stock' => 0.00,
                'opening_stock_rate' => 400.00,
                'hsn_sac' => '94039000',
                'gst_rate' => 18.00,
                'description' => 'Precision cut cross-brace support beam component for frame reinforcement.',
                'warehouse' => 'WH-WIP-TBL',
            ],
            'sfg_frame' => [
                'name' => 'Table Frame Assembly',
                'sku' => 'SFG-TBL-FRAME',
                'type' => 'semi_finished',
                'planning_type' => 'manufacture',
                'default_production_model' => Product::MODEL_PURE_MANUFACTURING,
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 4500.00,
                'selling_price' => 6800.00,
                'reorder_point' => 5.00,
                'opening_stock' => 0.00,
                'opening_stock_rate' => 4500.00,
                'hsn_sac' => '94039000',
                'gst_rate' => 18.00,
                'description' => 'Fully MIG welded, ground, and powder coated industrial steel table frame sub-assembly.',
                'warehouse' => 'WH-WIP-TBL',
            ],
            'sfg_top' => [
                'name' => 'Engineered Wood Table Top',
                'sku' => 'SFG-TBL-TOP',
                'type' => 'semi_finished',
                'planning_type' => 'manufacture',
                'default_production_model' => Product::MODEL_PURE_MANUFACTURING,
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 3200.00,
                'selling_price' => 4800.00,
                'reorder_point' => 5.00,
                'opening_stock' => 0.00,
                'opening_stock_rate' => 3200.00,
                'hsn_sac' => '94039000',
                'gst_rate' => 18.00,
                'description' => 'Routed, edge-banded, sealed, and lacquered wood table top component.',
                'warehouse' => 'WH-WIP-TBL',
            ],
            'fg_table' => [
                'name' => 'Industrial Dining Table (6-Seater 1800x900mm)',
                'sku' => 'FG-TBL-001',
                'type' => 'finished_good',
                'planning_type' => 'manufacture',
                'default_production_model' => Product::MODEL_PURE_MANUFACTURING,
                'uom_id' => $uoms['Pcs'],
                'cost_price' => 8500.00,
                'selling_price' => 15500.00,
                'reorder_point' => 5.00,
                'opening_stock' => 10.00,
                'opening_stock_rate' => 8500.00,
                'hsn_sac' => '94032010',
                'gst_rate' => 18.00,
                'description' => 'Complete industrial dining table assembly comprising steel frame and engineered wood top.',
                'warehouse' => 'WH-FG-TBL',
            ],
        ];

        $company = DB::table('companies')->where('tenant_id', $tenantId)->first();
        $companyId = $company?->id;
        $branch = DB::table('branches')->where('tenant_id', $tenantId)->first();
        $branchId = $branch?->id;

        $createdProducts = [];

        foreach ($items as $key => $item) {
            $product = Product::updateOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $item['sku']],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'planning_type' => $item['planning_type'],
                    'default_production_model' => $item['default_production_model'] ?? Product::MODEL_PURE_MANUFACTURING,
                    'uom_id' => $item['uom_id'],
                    'item_type' => 'Goods',
                    'variation_type' => 'Single',
                    'status' => 'active',
                    'unit_cost' => $item['cost_price'],
                    'cost_price' => $item['cost_price'],
                    'selling_price' => $item['selling_price'],
                    'hsn_sac' => $item['hsn_sac'],
                    'gst_rate' => $item['gst_rate'],
                    'reorder_point' => $item['reorder_point'],
                    'opening_stock' => $item['opening_stock'],
                    'opening_stock_rate' => $item['opening_stock_rate'],
                    'description' => $item['description'],
                    'sales_account' => $accounts['sales'],
                    'purchase_account' => $accounts['purchase'],
                    'inventory_account' => $accounts['inventory'],
                ]
            );

            $createdProducts[$key] = [
                'model' => $product,
                'warehouse' => $item['warehouse'],
                'opening_stock' => $item['opening_stock'],
                'cost_price' => $item['cost_price'],
            ];
        }

        return $createdProducts;
    }

    /**
     * Seed initial inventory stock and ledger transactions.
     */
    private function seedInitialStock(int $tenantId, array $products, array $warehouses): void
    {
        $company = DB::table('companies')->where('tenant_id', $tenantId)->first();
        $companyId = $company?->id;
        $branch = DB::table('branches')->where('tenant_id', $tenantId)->first();
        $branchId = $branch?->id;

        foreach ($products as $key => $info) {
            $product = $info['model'];
            $whCode = $info['warehouse'];
            $whId = $warehouses[$whCode] ?? reset($warehouses);
            $qty = (float) $info['opening_stock'];
            $rate = (float) $info['cost_price'];

            if ($qty <= 0) {
                continue;
            }

            ProductWarehouseStock::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'warehouse_id' => $whId,
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

            StockTransaction::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'warehouse_id' => $whId,
                    'reference_type' => 'Opening Stock',
                ],
                [
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'type' => 'IN',
                    'reference_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $rate,
                    'total_value' => $qty * $rate,
                    'balance_qty' => $qty,
                ]
            );
        }
    }
}
