<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\LoadsTestProductionData;
use Database\Seeders\Support\TestProductionLinks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opening stock of the test production dataset (docs/production_seeder_audit_2026-11-26.md, SR-07; decisions SD-03 /
 * SD-05): enough of everything that a first, normal-size production order runs from stock; a larger quantity makes
 * semis inside the order ("looped" production), deeper as the quantity grows. There are no production orders and no
 * stock movements in the test data — only this opening stock.
 *
 * Booked like the old execution seeder did, without journals: one 'Opening Stock' IN ledger row per product and
 * warehouse at the product's ₹ cost (balance_qty = the quantity, the FIFO layer), the warehouse balance row
 * (quantity = available_qty, nothing reserved) and products.opening_stock / opening_stock_rate. Raw material in
 * WH-RM-TBL, semi-finished in WH-WIP-TBL, finished goods in WH-FG-TBL — all on branch HQ, so an order created with the
 * HQ branch selected counts this stock.
 */
class TestProductionStockSeeder extends Seeder
{
    use LoadsTestProductionData;

    /** Every test raw material starts with this quantity (SD-03: "give all raw atleast 500K stock"). */
    public const RAW_MATERIAL_QTY = 500000;

    /** Warehouse (by code) the opening stock of a product type is booked into. */
    private const HOME_WAREHOUSE = [
        'raw_material' => 'WH-RM-TBL',
        'semi_finished' => 'WH-WIP-TBL',
        'finished_good' => 'WH-FG-TBL',
    ];

    public function run(?int $tenantId = null): void
    {
        $tenantId = $this->resolveTenantId($tenantId);
        $links = new TestProductionLinks($tenantId);
        $companyId = $links->companyId();
        $branchId = $links->branchId('HQ');
        $stockColumns = array_flip(Schema::getColumnListing('product_warehouse_stocks'));
        $ledgerColumns = array_flip(Schema::getColumnListing('stock_transactions'));
        $booked = 0;

        // service products (the subcontract job-work charges) hold no stock
        foreach (DB::table('products')->where('tenant_id', $tenantId)->where('sku', 'LIKE', 'test-%')->where('type', '!=', 'service')->orderBy('id')->get() as $product) {
            $qty = $this->openingQuantity($product);
            $warehouseId = $links->warehouseId(self::HOME_WAREHOUSE[$product->type] ?? 'WH-RM-TBL');
            $rate = (float) ($product->cost_price ?? 0);
            $at = $product->created_at ?? now();

            DB::table('products')->where('id', $product->id)->update(['opening_stock' => $qty, 'opening_stock_rate' => $rate]);
            if ($qty <= 0) {
                continue;
            }

            DB::table('product_warehouse_stocks')->updateOrInsert(
                ['tenant_id' => $tenantId, 'product_id' => $product->id, 'warehouse_id' => $warehouseId],
                array_intersect_key([
                    'company_id' => $companyId, 'branch_id' => $branchId, 'quantity' => $qty, 'available_qty' => $qty, 'reserved_qty' => 0,
                    'unit_cost' => $rate, 'created_at' => $at, 'updated_at' => $at,
                ], $stockColumns)
            );

            DB::table('stock_transactions')->updateOrInsert(
                ['tenant_id' => $tenantId, 'product_id' => $product->id, 'warehouse_id' => $warehouseId, 'reference_type' => 'Opening Stock'],
                array_intersect_key([
                    'company_id' => $companyId, 'branch_id' => $branchId, 'type' => 'IN', 'reference_id' => $product->id, 'quantity' => $qty,
                    'unit_cost' => $rate, 'total_value' => $qty * $rate, 'balance_qty' => $qty, 'created_at' => $at, 'updated_at' => $at,
                ], $ledgerColumns)
            );
            $booked++;
        }

        $this->command?->info("TestProductionStockSeeder: opening stock booked for {$booked} product(s) of Tenant ID {$tenantId}.");
    }

    private function openingQuantity(object $product): float
    {
        if ($product->type === 'raw_material') {
            return self::RAW_MATERIAL_QTY;
        }

        return (float) (self::openingStock()[$product->sku]
            ?? throw new \RuntimeException("Test production data: no opening stock defined for {$product->sku}."));
    }

    /**
     * Semi-finished and finished goods (SD-05: raised so normal orders run from stock). Thresholds while nothing is
     * reserved: test-final-01 up to 100 runs from stock; 101+ makes base chassis in the order (looped QC "Base chassis
     * check"); above 200 corner braces are made too; top boards (shared by both tables) above 150.
     *
     * @return array<string, float>
     */
    public static function openingStock(): array
    {
        return [
            'test-semi-01' => 2000,      // leg column
            'test-semi-02' => 2000,      // bracket
            'test-semi-03' => 10000,     // flange plate — very high: its routing links an outdated QC version (SD-02)
            'test-semi-04' => 500,       // top panel
            'test-semi-05' => 400,       // corner brace leg
            'test-semi-06' => 200,       // stiffener
            'test-semi-07' => 2000,      // aluminium profile, metres
            'test-semi-08' => 0,         // not used (SEC-4 has no capacity, SD-01)
            'test-semifinal-01' => 100,  // base chassis
            'test-semifinal-02' => 100,  // frame assembly
            'test-semifinal-03' => 150,  // top board, used by both tables
            'test-final-01' => 25,
            'test-final-02' => 25,
            // subcontract examples (Part C of the doc): an order of the product itself always runs its own routing (and
            // its job works); stock decides only when children are made inside a larger order
            'test-semi-09' => 400,       // gusset plate: 100 frames' worth (4 per frame)
            'test-semi-10' => 200,       // vendor-made support rail: 100 frames' worth (2 per frame)
            'test-semifinal-04' => 50,   // outdoor frame: test-final-03 up to 50 runs from stock, 51+ makes frames
            'test-final-03' => 10,
        ];
    }
}
