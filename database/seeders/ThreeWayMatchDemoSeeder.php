<?php

namespace Database\Seeders;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\ThreeWayMatchSettings;
use App\Domains\Purchase\Services\VendorBillService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo data for 3-way match (PO ↔ GRN ↔ vendor bill), so the Bill Matching
 * screen has something real to show.
 *
 *   PO-3WM-DEMO    Steel Rod 12mm  100 @ ₹500      Cement Bag 80 @ ₹380
 *   GRN-3WM-DEMO   Steel: 100 received, 95 accepted (5 rejected)
 *                  Cement: 80 received, 80 accepted
 *
 *   Bill 1  Steel  60 @ 500   → MATCHED            (posts normally)
 *   Bill 2  Steel  40 @ 500   → ON HOLD: 60 + 40 = 100 billed, only 95 accepted
 *   Bill 3  Cement 50 @ 410   → ON HOLD: rate 7.89% above PO (tolerance 2%)
 *   Bill 4  Cement 30 @ 386   → MATCHED: rate 1.58% above PO, within tolerance
 *
 * Sets the tenant's matching mode to "hold" (tolerance qty 0%, price 2%) so
 * the held bills appear — change it back on Purchase → Bill Matching.
 * Bills are entered as a separate "Demo Buyer" user so the admin can
 * release them (nobody can release a bill they entered themselves).
 *
 * Idempotent: guarded by the [DEMO-3WM] marker.
 *   php artisan db:seed --class=ThreeWayMatchDemoSeeder
 */
class ThreeWayMatchDemoSeeder extends Seeder
{
    private const MARK = '[DEMO-3WM]';

    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            $this->command?->error('No tenant found — aborting.');
            return;
        }

        $company = \App\Domains\HRMS\Models\Company::where('tenant_id', $tenant->id)->first();
        $branch = \App\Domains\HRMS\Models\Branch::where('tenant_id', $tenant->id)->first();

        if (!$company || !$branch) {
            $this->command?->error('Tenant has no company/branch — aborting.');
            return;
        }

        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);
        app(BranchContext::class)->set($branch);

        if (VendorBill::where('notes', 'like', self::MARK . '%')->exists()) {
            $this->command?->warn('3-way match demo already seeded — nothing to do.');
            return;
        }

        // Prefer a warehouse in this company/branch; otherwise any of the tenant's.
        $warehouse = Warehouse::where('tenant_id', $tenant->id)->orderBy('id')->first()
            ?? Warehouse::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('id')->first();
        if (!$warehouse) {
            $this->command?->error('Tenant has no warehouse — aborting.');
            return;
        }

        $scope = ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'branch_id' => $branch->id];

        $vendor = Vendor::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Bharat Steel & Cement Suppliers'],
            $scope + ['status' => 'active']
        );

        $steel = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'DEMO-3WM-ROD12'],
            $scope + ['name' => 'Steel Rod 12mm', 'type' => 'raw_material', 'item_type' => 'Goods', 'status' => 'active', 'unit_cost' => 500]
        );
        $cement = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'DEMO-3WM-CEMENT'],
            $scope + ['name' => 'Cement Bag 50kg', 'type' => 'raw_material', 'item_type' => 'Goods', 'status' => 'active', 'unit_cost' => 380]
        );

        $po = PurchaseOrder::create($scope + [
            'purchase_order_number' => 'PO-3WM-DEMO',
            'vendor_id' => $vendor->id,
            'date' => now()->subDays(10)->toDateString(),
            'status' => 'Approved',
            'tax_type' => 'without_tax',
            'discount_type' => 'without_discount',
        ]);
        $poSteel = PurchaseOrderItem::create($scope + [
            'purchase_order_id' => $po->id, 'product_id' => $steel->id,
            'quantity' => 100, 'received_qty' => 100, 'rate' => 500, 'amount' => 50000, 'total_amount' => 50000,
        ]);
        $poCement = PurchaseOrderItem::create($scope + [
            'purchase_order_id' => $po->id, 'product_id' => $cement->id,
            'quantity' => 80, 'received_qty' => 80, 'rate' => 380, 'amount' => 30400, 'total_amount' => 30400,
        ]);

        $grn = GoodsReceiptNote::create($scope + [
            'grn_number' => 'GRN-3WM-DEMO',
            'purchase_order_id' => $po->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $warehouse->id,
            'received_date' => now()->subDays(5)->toDateString(),
            'status' => 'Approved',
            'notes' => self::MARK . ' 5 steel rods rejected at inspection.',
        ]);
        $grnSteel = GoodsReceiptNoteItem::create($scope + [
            'goods_receipt_note_id' => $grn->id, 'purchase_order_item_id' => $poSteel->id, 'product_id' => $steel->id,
            'ordered_qty' => 100, 'received_qty' => 100, 'accepted_qty' => 95, 'rejected_qty' => 5,
            'unit_rate' => 500, 'total_amount' => 47500,
        ]);
        $grnCement = GoodsReceiptNoteItem::create($scope + [
            'goods_receipt_note_id' => $grn->id, 'purchase_order_item_id' => $poCement->id, 'product_id' => $cement->id,
            'ordered_qty' => 80, 'received_qty' => 80, 'accepted_qty' => 80, 'rejected_qty' => 0,
            'unit_rate' => 380, 'total_amount' => 30400,
        ]);

        app(ThreeWayMatchSettings::class)->update($tenant->id, [
            'mode' => ThreeWayMatchSettings::MODE_HOLD,
            'qty_tolerance_percent' => 0,
            'price_tolerance_percent' => 2,
        ]);

        // Bills are entered by a separate user so the admin can release them.
        $buyer = User::firstOrCreate(
            ['email' => 'demo.buyer@example.com'],
            ['tenant_id' => $tenant->id, 'name' => 'Demo Buyer', 'password' => bcrypt(str()->random(32))]
        );
        Auth::login($buyer);

        $bills = app(VendorBillService::class);
        $scenarios = [
            ['VINV-3WM-001', $steel, $poSteel, $grnSteel, 60, 500, 'Steel 60 @ 500 — matches PO and GRN.'],
            ['VINV-3WM-002', $steel, $poSteel, $grnSteel, 40, 500, 'Steel 40 @ 500 — with bill 1 that is 100 billed, but only 95 were accepted.'],
            ['VINV-3WM-003', $cement, $poCement, $grnCement, 50, 410, 'Cement 50 @ 410 — 7.89% above the PO rate of 380.'],
            ['VINV-3WM-004', $cement, $poCement, $grnCement, 30, 386, 'Cement 30 @ 386 — 1.58% above PO, within the 2% tolerance.'],
        ];

        foreach ($scenarios as [$invoiceNo, $product, $poItem, $grnItem, $qty, $rate, $note]) {
            $bill = $bills->storeBill([
                'purchase_order_id' => $po->id,
                'goods_receipt_note_id' => $grn->id,
                'vendor_id' => $vendor->id,
                'vendor_invoice_number' => $invoiceNo,
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => self::MARK . ' ' . $note,
                'items' => [[
                    'product_id' => $product->id,
                    'purchase_order_item_id' => $poItem->id,
                    'goods_receipt_note_item_id' => $grnItem->id,
                    'quantity' => $qty,
                    'unit_price' => $rate,
                    'tax_rate' => 0,
                ]],
            ], $tenant->id)->fresh();

            $this->command?->line(sprintf('  %s  %-9s  %-8s  %s', $bill->bill_number, $bill->status, $bill->match_status, $note));
        }

        Auth::logout();

        $this->command?->info('3-way match demo seeded. Open Purchase → Bill Matching.');
    }
}
