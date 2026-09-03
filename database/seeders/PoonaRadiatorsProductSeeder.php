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
     * Clean up previous stock transactions and warehouse stock balances for Poona Radiators products.
     */
    private function cleanupPreviousData(int $tenantId): void
    {
        $skus = [
            'PR-RM-ALU-FIN-01',
            'PR-RM-ALU-TUBE-F',
            'PR-RM-TANK-PLATE',
            'PR-RM-NOZZLE-45',
            'PR-RM-BRAZE-FLUX',
            'PR-SUB-CORE-750',
            'PR-FG-RAD-750',
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
        $company = \App\Domains\HRMS\Models\Company::firstOrCreate(
            ['tenant_id' => $tenantId, 'company_name' => 'Warrgyizmorsch'],
            ['legal_name' => 'Warrgyizmorsch Pvt Ltd', 'status' => true]
        );
        $branch = \App\Domains\HRMS\Models\Branch::firstOrCreate(
            ['tenant_id' => $tenantId, 'company_id' => $company->id, 'code' => 'HQ'],
            ['name' => 'Headquarters', 'status' => true]
        );

        $whList = [
            ['name' => 'Main Raw Material Store', 'code' => 'WH-RM-01', 'is_default' => true],
            ['name' => 'Production WIP Store', 'code' => 'WH-WIP-01', 'is_default' => false],
            ['name' => 'Finished Goods Warehouse', 'code' => 'WH-FG-01', 'is_default' => false],
        ];

        foreach ($whList as $w) {
            $wh = Warehouse::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $w['code']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
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
                'type' => 'semi_finished',
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
     * Seed initial opening stock balances in product_warehouse_stocks and record stock_transactions and accounting journal.
     */
    private function seedInitialStock(int $tenantId, array $products, array $warehouses): void
    {
        $company = DB::table('companies')->where('tenant_id', $tenantId)->first();
        $companyId = $company?->id ?? 1;
        $branch = DB::table('branches')->where('company_id', $companyId)->first();
        $branchId = $branch?->id ?? 1;

        $totalOpeningValue = 0;
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

            $totalOpeningValue += $totalValue;
        }

        if ($totalOpeningValue > 0) {
            $this->postOpeningStockJournal($tenantId, $totalOpeningValue);
        }
    }

    /**
     * Post Accounting Journal Entry for Opening Stock to align Inventory Asset A/c balance in General Ledger.
     */
    private function postOpeningStockJournal(int $tenantId, float $totalValue): void
    {
        $inventoryAcc = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '1200')->orWhere('name', 'like', '%Inventory%');
            })->first();

        $equityAcc = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '3010')->orWhere('code', '3020');
            })->first();

        if (!$inventoryAcc || !$equityAcc) {
            return;
   
        }

        /** @var \App\Domains\Accounting\Services\JournalService $journalService */
        $journalService = app(\App\Domains\Accounting\Services\JournalService::class);

        $existing = \App\Domains\Accounting\Models\Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'OpeningStockSeeder')
            ->first();

        if ($existing) {
            $existing->forceDelete();
        }

        $lines = [
            [
                'chart_of_account_id' => $inventoryAcc->id,
                'debit' => round($totalValue, 2),
                'credit' => 0,
                'description' => 'Opening Stock Valuation - Inventory Asset',
            ],
            [
                'chart_of_account_id' => $equityAcc->id,
                'debit' => 0,
                'credit' => round($totalValue, 2),
                'description' => 'Opening Stock Valuation - Owner Equity / Capital',
            ],
        ];

        $meta = [
            'tenant_id' => $tenantId,
            'journal_date' => now(),
            'source' => \App\Domains\Accounting\Models\Journal::SOURCE_INVENTORY,
            'reference_type' => 'OpeningStockSeeder',
            'reference_id' => 1,
            'memo' => 'Initial Opening Stock Valuation Accounting Entry',
            'journal_number_prefix' => 'OP-JNL',
        ];

        $journalService->post($lines, $meta);
    }
}
