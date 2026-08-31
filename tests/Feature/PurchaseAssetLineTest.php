<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Listeners\CreateAssetFromGrnLine;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Events\GrnAssetLineReceived;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Services\GoodsReceiptNoteService;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Domains\Purchase\Services\VendorBillService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Covers the stock/asset/expense line_type split: a PO line marked as an
 * asset purchase must skip Inventory tracking, auto-create an HRMS Asset at
 * GRN receipt, and post to the category's Fixed Asset GL account (not
 * Inventory) when the Vendor Bill is posted.
 */
class PurchaseAssetLineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Vendor $vendor;
    private Warehouse $warehouse;
    private Product $stockProduct;
    private AssetCategory $assetCategory;
    private ChartOfAccount $fixedAssetAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Supplies',
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
            'status' => 'active',
        ]);

        $this->stockProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Widget',
            'sku' => 'WIDGET-1',
            'type' => 'raw_material',
            'item_type' => 'Goods',
            'status' => 'active',
            'unit_cost' => 100.00,
        ]);

        $this->fixedAssetAccount = $this->seedAccountingBooks($this->tenant->id);

        app(TenantContext::class)->set($this->tenant);
        $company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Test Co',
            'status' => true,
        ]);
        $this->assetCategory = AssetCategory::create([
            'company_id' => $company->id,
            'name' => 'IT Equipment',
            'fixed_asset_account_id' => $this->fixedAssetAccount->id,
        ]);
    }

    private function seedAccountingBooks(int $tenantId): ChartOfAccount
    {
        $headers = [
            ['code' => '1000', 'name' => 'Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
            ['code' => '2000', 'name' => 'Liabilities', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '5000', 'name' => 'Expenses', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
        ];

        $headerIds = [];
        foreach ($headers as $header) {
            $account = ChartOfAccount::create([
                'tenant_id' => $tenantId,
                'code' => $header['code'],
                'name' => $header['name'],
                'type' => $header['type'],
                'normal_balance' => $header['normal_balance'],
                'is_system' => true,
                'is_active' => true,
            ]);
            $headerIds[$header['code']] = $account->id;
        }

        $children = [
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1504', 'name' => 'Computers & IT Equipment', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
        ];

        $fixedAssetAccount = null;
        foreach ($children as $child) {
            $account = ChartOfAccount::create([
                'tenant_id' => $tenantId,
                'code' => $child['code'],
                'name' => $child['name'],
                'type' => $child['type'],
                'normal_balance' => $child['normal_balance'],
                'parent_id' => $headerIds[$child['parent']],
                'is_system' => true,
                'is_active' => true,
            ]);
            if ($child['code'] === '1504') {
                $fixedAssetAccount = $account;
            }
        }

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenantId,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        return $fixedAssetAccount;
    }

    /** @test */
    public function grn_asset_line_event_is_wired_up(): void
    {
        $this->assertTrue(Event::hasListeners(GrnAssetLineReceived::class));
    }

    /** @test */
    public function asset_line_skips_inventory_and_creates_an_unallocated_hrms_asset_on_receipt(): void
    {
        $po = app(PurchaseOrderService::class)->storeOrder([
            'vendor_id' => $this->vendor->id,
            'items' => [
                ['product_id' => $this->stockProduct->id, 'quantity' => 5, 'unit_price' => 100],
                [
                    'line_type' => PurchaseOrderItem::LINE_TYPE_ASSET,
                    'asset_category_id' => $this->assetCategory->id,
                    'quantity' => 1,
                    'unit_price' => 45000,
                ],
            ],
        ], $this->tenant->id);

        $stockPoItem = $po->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_STOCK);
        $assetPoItem = $po->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_ASSET);
        $this->assertNotNull($stockPoItem);
        $this->assertNotNull($assetPoItem);

        $grn = app(GoodsReceiptNoteService::class)->storeGrn([
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $stockPoItem->id, 'received_qty' => 5],
                ['purchase_order_item_id' => $assetPoItem->id, 'received_qty' => 1],
            ],
        ], $this->tenant->id);

        // Stock line moved physical inventory; asset line did not.
        $stock = ProductWarehouseStock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->stockProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEqualsWithDelta(5.0, (float) $stock->quantity, 0.01);

        $assetGrnItem = $grn->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_ASSET);
        $this->assertNotNull($assetGrnItem);

        $asset = Asset::where('goods_receipt_note_item_id', $assetGrnItem->id)->first();
        $this->assertNotNull($asset, 'Expected an HRMS Asset to be auto-created from the asset GRN line.');
        $this->assertSame('available', $asset->status);
        $this->assertNull($asset->assigned_employee_id);
        $this->assertSame($this->assetCategory->id, $asset->asset_category_id);
        $this->assertEqualsWithDelta(45000.0, (float) $asset->purchase_cost, 0.01);
    }

    /** @test */
    public function vendor_bill_posts_asset_line_to_category_fixed_asset_account_not_inventory(): void
    {
        $po = app(PurchaseOrderService::class)->storeOrder([
            'vendor_id' => $this->vendor->id,
            'items' => [
                ['product_id' => $this->stockProduct->id, 'quantity' => 5, 'unit_price' => 100],
                [
                    'line_type' => PurchaseOrderItem::LINE_TYPE_ASSET,
                    'asset_category_id' => $this->assetCategory->id,
                    'quantity' => 1,
                    'unit_price' => 45000,
                ],
            ],
        ], $this->tenant->id);

        $stockPoItem = $po->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_STOCK);
        $assetPoItem = $po->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_ASSET);

        $grn = app(GoodsReceiptNoteService::class)->storeGrn([
            'purchase_order_id' => $po->id,
            'vendor_id' => $this->vendor->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->toDateString(),
            'items' => [
                ['purchase_order_item_id' => $stockPoItem->id, 'received_qty' => 5],
                ['purchase_order_item_id' => $assetPoItem->id, 'received_qty' => 1],
            ],
        ], $this->tenant->id);

        $stockGrnItem = $grn->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_STOCK);
        $assetGrnItem = $grn->items->firstWhere('line_type', PurchaseOrderItem::LINE_TYPE_ASSET);

        $bill = app(VendorBillService::class)->storeBill([
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'purchase_order_id' => $po->id,
            'goods_receipt_note_id' => $grn->id,
            'vendor_id' => $this->vendor->id,
            'vendor_invoice_number' => 'VINV-ASSET-1',
            'items' => [
                [
                    'purchase_order_item_id' => $stockPoItem->id,
                    'goods_receipt_note_item_id' => $stockGrnItem->id,
                    'quantity' => 5,
                    'unit_price' => 100,
                ],
                [
                    'purchase_order_item_id' => $assetPoItem->id,
                    'goods_receipt_note_item_id' => $assetGrnItem->id,
                    'quantity' => 1,
                    'unit_price' => 45000,
                ],
            ],
        ], $this->tenant->id);

        $journal = Journal::withoutGlobalScopes()
            ->where('reference_type', 'vendor_bill')
            ->where('reference_id', $bill->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta((float) $journal->total_debit, (float) $journal->total_credit, 0.01);

        $inventory = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1200')->firstOrFail();
        $ap = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '2010')->firstOrFail();

        $entries = $journal->entries;

        $this->assertEqualsWithDelta(500.0, (float) $entries->firstWhere('chart_of_account_id', $inventory->id)?->debit, 0.01);
        $this->assertEqualsWithDelta(45000.0, (float) $entries->firstWhere('chart_of_account_id', $this->fixedAssetAccount->id)?->debit, 0.01);
        $this->assertEqualsWithDelta(45500.0, (float) $entries->firstWhere('chart_of_account_id', $ap->id)?->credit, 0.01);
    }
}
