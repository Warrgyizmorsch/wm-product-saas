<?php

namespace Tests\Feature;

use App\Domains\Accounting\Services\Gst\Gstr1ReturnService;
use App\Domains\Accounting\Services\Gst\Gstr2bReconciliationService;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\PurchaseOrderService;
use App\Domains\Purchase\Services\VendorBillService;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Services\EInvoiceService;
use App\Domains\Sales\Services\SalesOrderService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\ContactRole;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyStructureService;
use App\Services\PartyVerificationException;
use App\Services\PartyVerificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FRS 2.3 / C-17 / BASE-03 + Own-branch GSTIN feature tests.
 */
class PartyBranchStructureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $mhBranch;
    private Branch $gjBranch;
    private User $maker;
    private User $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Encore Test Tenant',
            'slug' => 'encore-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(\App\Support\Tenancy::class)->setTenant($this->tenant);

        $this->seed(RbacSeeder::class);

        $this->maker = $this->userWithRole('maker@encore.test', 'tenant_owner', $this->tenant);
        $this->verifier = $this->userWithRole('verifier@encore.test', 'company_admin', $this->tenant);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Encore Furniture Pvt Ltd',
            'legal_name' => 'Encore Furniture Private Limited',
            'gst_number' => '27AAACE1234F1Z5',
            'pan_number' => 'AAACE1234F',
            'email' => 'ho@encore.test',
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
            'address' => 'Plot 1, MIDC',
            'postal_code' => '400001',
            'status' => true,
        ]);

        $this->mhBranch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Mumbai Head Office',
            'code' => 'MUM-HO',
            'gst_number' => '27AAACE1234F1Z5',
            'is_head_office' => true,
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
            'address' => 'Plot 1, MIDC Mumbai',
            'postal_code' => '400001',
            'status' => true,
        ]);

        $this->gjBranch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Gujarat Branch',
            'code' => 'GJ-01',
            'gst_number' => '24AAACE1234F1Z2',
            'is_head_office' => false,
            'state' => 'Gujarat',
            'city' => 'Ahmedabad',
            'address' => 'GIDC Phase 2, Ahmedabad',
            'postal_code' => '380001',
            'status' => true,
        ]);

        app(\App\Support\Companying::class)->set($this->company);
        app(\App\Support\Branching::class)->set($this->mhBranch);
        $this->actingAs($this->maker);
    }

    private function userWithRole(string $email, string $roleSlug, Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
            'tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    private function verifiedCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'name' => 'ABC Tech Pvt Ltd',
            'email' => 'abc@example.com',
            'phone' => '9876543210',
            'gstin' => '27AABCA9999K1Z1',
            'pan' => 'AABCA9999K',
            'billing_address' => 'Tower A, BKC, Mumbai, Maharashtra',
            'shipping_address' => 'Tower A, BKC, Mumbai, Maharashtra',
            'status' => 'active',
            'verification_status' => Customer::VERIFICATION_VERIFIED,
        ], $overrides));
    }

    private function verifiedVendor(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'name' => 'SteelCraft India Pvt Ltd',
            'code' => 'VEND-0001',
            'email' => 'steel@example.com',
            'phone' => '9876500000',
            'gstin' => '27AABCS8888M1Z3',
            'pan' => 'AABCS8888M',
            'address' => 'Chakan MIDC, Pune, Maharashtra',
            'status' => 'active',
            'verification_status' => Vendor::VERIFICATION_VERIFIED,
        ], $overrides));
    }

    // --------------------------------------------------------- 1. Own branch GSTIN

    #[Test]
    public function own_branch_gstin_overrides_company_falls_back_when_blank_and_enforces_pan_and_single_head_office(): void
    {
        // Branch with own GSTIN overrides company GSTIN & state
        $gjDetails = billing_organization_details($this->gjBranch);
        $this->assertSame('24AAACE1234F1Z2', $gjDetails['gstin']);
        $this->assertSame('24', $gjDetails['state_code']);
        $this->assertSame('Gujarat', $gjDetails['state_name']);

        // Branch with blank GSTIN falls back to company GSTIN
        $puneBranch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Pune Sales Office',
            'code' => 'PUN-01',
            'gst_number' => null,
            'is_head_office' => false,
            'state' => 'Maharashtra',
            'status' => true,
        ]);
        $this->assertSame('27AAACE1234F1Z5', $puneBranch->effectiveGstin());
        $this->assertSame('27', $puneBranch->effectiveStateCode());
        $this->assertSame('27AAACE1234F1Z5', billing_organization_details($puneBranch)['gstin']);

        // Mismatched PAN on own branch is blocked
        $this->expectException(ValidationException::class);
        Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Wrong PAN Branch',
            'code' => 'BAD-01',
            'gst_number' => '29ZZZZZ9999Z1Z5',
            'status' => true,
        ]);
    }

    #[Test]
    public function only_one_head_office_branch_is_allowed_per_company(): void
    {
        $this->expectException(ValidationException::class);

        Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Second HO',
            'code' => 'HO-2',
            'is_head_office' => true,
            'status' => true,
        ]);
    }

    // --------------------------------------------------------- 2. BASE-03 & Branch Verification

    #[Test]
    public function base_03_customer_with_two_branches_four_role_contacts_pan_enforcement_and_branch_verification(): void
    {
        $structure = app(PartyStructureService::class);
        $verification = app(PartyVerificationService::class);

        $customer = $this->verifiedCustomer();

        // Head office auto-synced from parent without a second verification cycle
        $ho = $customer->headOfficeBranch();
        $this->assertNotNull($ho);
        $this->assertTrue($ho->isVerified());
        $this->assertSame('active', $ho->status);
        $this->assertSame('27AABCA9999K1Z1', $ho->gstin);

        // Mismatched PAN branch is rejected
        try {
            $structure->createBranch($customer, [
                'name' => 'Different Legal Entity',
                'branch_type' => 'branch',
                'gstin' => '29ZZZZZ1111Z1Z9',
            ], $this->maker);
            $this->fail('Expected PAN mismatch validation exception');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('gstin', $e->errors());
        }

        // Valid second branch (Karnataka, same PAN) starts pending_verification; parent stays ACTIVE
        $kaSite = $structure->createBranch($customer, [
            'name' => 'Bengaluru Site',
            'code' => 'BLR-01',
            'branch_type' => 'site',
            'gstin' => '29AABCA9999K1Z8',
            'address_line1' => 'Outer Ring Road',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'postal_code' => '560103',
            'is_billing' => true,
            'is_shipping' => true,
        ], $this->maker);

        $this->assertTrue($kaSite->isPendingVerification());
        $this->assertSame('inactive', $kaSite->status);
        $this->assertSame('active', $customer->fresh()->status);

        // Creator cannot verify their own branch
        try {
            $verification->verify($kaSite, $this->maker, ['gstin' => true]);
            $this->fail('Creator should not be able to self-verify branch');
        } catch (PartyVerificationException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }

        // Unverified branch cannot be activated or used on a new Sales Order
        try {
            $structure->activateBranch($kaSite);
            $this->fail('Unverified branch should not activate');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('branch', $e->errors());
        }

        try {
            SalesOrder::create([
                'tenant_id' => $this->tenant->id,
                'company_id' => $this->company->id,
                'branch_id' => $this->mhBranch->id,
                'customer_id' => $customer->id,
                'billing_branch_id' => $ho->id,
                'shipping_branch_id' => $kaSite->id,
                'sales_order_number' => 'SO-UNVERIFIED-1',
                'order_date' => now()->toDateString(),
                'status' => 'Draft',
            ]);
            $this->fail('Unverified branch should be blocked on new SO');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shipping_branch_id', $e->errors());
        }

        // Verifier verifies Bengaluru branch
        $kaSite = $verification->verify($kaSite, $this->verifier, ['gstin' => true], 'Checked GST certificate');
        $this->assertTrue($kaSite->isVerified());
        $this->assertSame('active', $kaSite->status);

        // Create 4 role-based contacts, including one contact with TWO roles
        $dmRole = ContactRole::where('slug', 'decision_maker')->firstOrFail();
        $techRole = ContactRole::where('slug', 'technical_evaluator')->firstOrFail();
        $archRole = ContactRole::where('slug', 'architect')->firstOrFail();
        $accRole = ContactRole::where('slug', 'accounts')->firstOrFail();
        $siteRole = ContactRole::where('slug', 'site_receiver')->firstOrFail();

        $c1 = $structure->createContact('customer', $customer->id, ['name' => 'Rohan', 'is_primary' => true], [$dmRole->id, $techRole->id], $this->tenant->id);
        $c2 = $structure->createContact('customer', $customer->id, ['name' => 'Meera'], [$archRole->id], $this->tenant->id);
        $c3 = $structure->createContact('customer', $customer->id, ['name' => 'Suresh', 'party_branch_id' => $ho->id], [$accRole->id], $this->tenant->id);
        $c4 = $structure->createContact('customer', $customer->id, ['name' => 'Kiran', 'party_branch_id' => $kaSite->id, 'phone' => '9988776655'], [$siteRole->id], $this->tenant->id);

        $this->assertCount(2, $c1->roles);
        $this->assertSame(4, $customer->contacts()->count());
        $this->assertSame($kaSite->id, $c4->party_branch_id);
    }

    // --------------------------------------------------------- 3. Sales Flow & Frozen Snapshots

    #[Test]
    public function sales_order_freezes_gstins_resolves_tax_type_and_stays_immutable_after_branch_gstin_change(): void
    {
        $structure = app(PartyStructureService::class);
        $verification = app(PartyVerificationService::class);

        $customer = $this->verifiedCustomer();
        $kaBranch = $structure->createBranch($customer, [
            'name' => 'Bengaluru Office',
            'code' => 'BLR-02',
            'branch_type' => 'branch',
            'gstin' => '29AABCA9999K1Z8',
            'address_line1' => 'MG Road, Bengaluru',
            'state' => 'Karnataka',
            'is_billing' => true,
            'is_shipping' => true,
        ], $this->maker);
        $kaBranch = $verification->verify($kaBranch, $this->verifier, ['gstin' => true]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Office Workstation',
            'sku' => 'WS-001',
            'selling_price' => 10000,
            'status' => 'active',
        ]);

        // Bill-To Karnataka (29) from our Mumbai Branch (27) -> IGST
        $soService = app(SalesOrderService::class);
        $interSo = $soService->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'customer_id' => $customer->id,
            'billing_branch_id' => $kaBranch->id,
            'shipping_branch_id' => $kaBranch->id,
            'order_date' => now()->toDateString(),
            'tax_type' => 'item_wise_tax',
        ], [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'tax_rate' => 18,
        ]]);

        $this->assertSame('igst', $interSo->gst_type);
        $this->assertSame('27AAACE1234F1Z5', $interSo->seller_gstin);
        $this->assertSame('29AABCA9999K1Z8', $interSo->bill_to_gstin);
        $this->assertSame('29', $interSo->place_of_supply_state);

        // Bill-To Mumbai HO (27) from our Mumbai Branch (27) -> CGST+SGST
        $ho = $customer->headOfficeBranch();
        $intraSo = $soService->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'customer_id' => $customer->id,
            'billing_branch_id' => $ho->id,
            'shipping_branch_id' => $ho->id,
            'order_date' => now()->toDateString(),
            'tax_type' => 'item_wise_tax',
        ], [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'tax_rate' => 18,
        ]]);

        $this->assertSame('cgst_sgst', $intraSo->gst_type);

        // Changing branch GSTIN later must NOT change frozen GSTIN on old SalesOrder
        $structure->updateBranch($kaBranch, ['gstin' => '24AABCA9999K1Z4'], $this->maker);
        $this->assertSame('29AABCA9999K1Z8', $interSo->fresh()->bill_to_gstin);
    }

    // --------------------------------------------------------- 4. GST Compliance & E-Invoice

    #[Test]
    public function gstr1_uses_frozen_pos_blocks_mixed_seller_gstins_and_einvoice_rejects_missing_buyer_gstin(): void
    {
        $customer = $this->verifiedCustomer();

        // Invoice 1 from Maharashtra branch (27), Invoice 2 from Gujarat branch (24) in same month
        $inv1 = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-MUM-001',
            'invoice_date' => '2026-11-05',
            'due_date' => '2026-11-20',
            'status' => 'Posted',
            'seller_gstin' => '27AAACE1234F1Z5',
            'seller_state_code' => '27',
            'bill_to_gstin' => '29AABCA9999K1Z8',
            'place_of_supply_state' => '29',
            'gst_type' => 'igst',
            'subtotal' => 1000,
            'igst_amount' => 180,
            'tax_amount' => 180,
            'total_amount' => 1180,
        ]);

        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->gjBranch->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-GJ-001',
            'invoice_date' => '2026-11-10',
            'due_date' => '2026-11-25',
            'status' => 'Posted',
            'seller_gstin' => '24AAACE1234F1Z2',
            'seller_state_code' => '24',
            'bill_to_gstin' => '27AABCA9999K1Z1',
            'place_of_supply_state' => '27',
            'gst_type' => 'igst',
            'subtotal' => 2000,
            'igst_amount' => 360,
            'tax_amount' => 360,
            'total_amount' => 2360,
        ]);

        $gstr1 = app(Gstr1ReturnService::class);
        $built = $gstr1->build(Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30'));

        // Frozen bill_to_gstin and place_of_supply_state are used
        $this->assertSame('29', $built['documents']["invoice:{$inv1->id}"]['pos']);
        $this->assertSame('29AABCA9999K1Z8', $built['documents']["invoice:{$inv1->id}"]['ctin']);

        // Mixed seller GSTIN blocker names both GSTINs and stops export
        $this->assertNotEmpty($built['blockers']);
        $this->assertStringContainsString('24AAACE1234F1Z2', $built['blockers'][0]);
        $this->assertStringContainsString('27AAACE1234F1Z5', $built['blockers'][0]);

        // E-Invoice no longer uses fake hardcoded GSTIN 27AABCU9603R1ZM
        $unregCustomer = $this->verifiedCustomer(['email' => 'unreg@example.com', 'gstin' => null]);
        $noGstinInv = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'customer_id' => $unregCustomer->id,
            'invoice_number' => 'INV-NOGST-001',
            'invoice_date' => '2026-11-12',
            'due_date' => '2026-11-27',
            'status' => 'Posted',
            'seller_gstin' => '27AAACE1234F1Z5',
            'seller_state_code' => '27',
            'total_amount' => 500,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(EInvoiceService::class)->buildEInvoicePayload($noGstinInv);
    }

    // --------------------------------------------------------- 5. Purchase Flow & Receiving Branch Visibility

    #[Test]
    public function purchase_order_enforces_single_delivery_target_receiving_branch_visibility_and_bill_inheritance(): void
    {
        $structure = app(PartyStructureService::class);
        $verification = app(PartyVerificationService::class);

        $vendor = $this->verifiedVendor();
        $gjVendorBranch = $structure->createBranch($vendor, [
            'name' => 'Ahmedabad Factory',
            'code' => 'AMD-01',
            'branch_type' => 'warehouse',
            'gstin' => '24AABCS8888M1Z0',
            'address_line1' => 'Sanand GIDC, Gujarat',
            'state' => 'Gujarat',
            'is_billing' => true,
            'is_shipping' => true,
        ], $this->maker);
        $gjVendorBranch = $verification->verify($gjVendorBranch, $this->verifier, ['gstin' => true]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Frame',
            'sku' => 'SF-001',
            'cost_price' => 500,
            'status' => 'active',
        ]);

        $poService = app(PurchaseOrderService::class);

        // Multiple delivery targets must be blocked
        try {
            $poService->storeOrder([
                'vendor_id' => $vendor->id,
                'vendor_branch_id' => $gjVendorBranch->id,
                'location' => 'Main Warehouse',
                'delivery_branch_id' => $this->gjBranch->id,
                'items' => [['product_id' => $product->id, 'quantity' => 5, 'rate' => 500]],
            ], $this->tenant->id);
            $this->fail('Expected multiple delivery targets to fail');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delivery_target', $e->errors());
        }

        // Ordering from Mumbai (27), vendor dispatching from Gujarat (24), delivered to our Gujarat Branch
        $po = $poService->storeOrder([
            'branch_id' => $this->mhBranch->id,
            'vendor_id' => $vendor->id,
            'vendor_branch_id' => $gjVendorBranch->id,
            'delivery_branch_id' => $this->gjBranch->id,
            'tax_type' => 'item_wise_tax',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'rate' => 500, 'tax_rate' => 18]],
        ], $this->tenant->id);

        $this->assertSame('igst', $po->gst_type);
        $this->assertSame('27AAACE1234F1Z5', $po->buyer_gstin);
        $this->assertSame('24AABCS8888M1Z0', $po->supplier_gstin);

        // Switch active branch to receiving branch (Gujarat): PO must still be visible despite BelongsToBranch!
        app(\App\Support\Branching::class)->set($this->gjBranch);
        $this->assertNotNull(PurchaseOrder::find($po->id));

        // Vendor bill inherits vendor_branch_id, supplier_gstin, buyer_gstin from PO
        $bill = app(VendorBillService::class)->storeBill([
            'purchase_order_id' => $po->id,
            'vendor_id' => $vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 500, 'tax_rate' => 18]],
        ], $this->tenant->id);

        $this->assertSame($gjVendorBranch->id, $bill->vendor_branch_id);
        $this->assertSame('24AABCS8888M1Z0', $bill->supplier_gstin);
        $this->assertSame('27AAACE1234F1Z5', $bill->buyer_gstin);
    }

    // --------------------------------------------------------- 6. Pre-conversion Contacts & Tenant Isolation

    #[Test]
    public function pre_conversion_account_contacts_migrate_to_customer_and_tenants_stay_isolated(): void
    {
        $structure = app(PartyStructureService::class);

        $account = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'name' => 'Prospect Interiors',
            'email' => 'prospect@example.com',
            'status' => 'active',
            'owner_id' => $this->maker->id,
        ]);

        $contact = $structure->createContact(
            PartyContact::PARTY_CRM_ACCOUNT,
            $account->id,
            ['name' => 'Priya Architect', 'email' => 'priya@example.com', 'role' => 'Architect'],
            [],
            $this->tenant->id
        );

        Customer::$skipAccountAutoCreate = true;
        $customer = $this->verifiedCustomer(['name' => 'Prospect Interiors', 'email' => 'prospect@example.com']);
        Customer::$skipAccountAutoCreate = false;

        $account->update(['customer_id' => $customer->id]);

        $contact->refresh();
        $this->assertSame(PartyContact::PARTY_CUSTOMER, $contact->party_type);
        $this->assertSame($customer->id, $contact->party_id);

        // Tenant isolation: another tenant cannot see Tenant 1's branches or contacts
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        app(\App\Support\Tenancy::class)->setTenant($otherTenant);

        $this->assertSame(0, PartyBranch::count());
        $this->assertSame(0, PartyContact::count());
    }

    // --------------------------------------------------------- 7. GSTR-2B Branch GSTIN Matching & Mixed-GSTIN Guard

    #[Test]
    public function gstr2b_matches_vendor_branch_gstin_and_blocks_mixed_buyer_gstin_periods(): void
    {
        $structure = app(PartyStructureService::class);
        $verification = app(PartyVerificationService::class);

        $vendor = $this->verifiedVendor();
        $gjVendorBranch = $structure->createBranch($vendor, [
            'name' => 'Surat Depot',
            'code' => 'SRT-01',
            'branch_type' => 'warehouse',
            'gstin' => '24AABCS8888M1Z0',
            'address_line1' => 'Hazira Road, Surat',
            'state' => 'Gujarat',
            'is_billing' => true,
            'is_shipping' => true,
        ], $this->maker);
        $gjVendorBranch = $verification->verify($gjVendorBranch, $this->verifier, ['gstin' => true]);

        // Bill 1 received under Mumbai branch GSTIN (27) from vendor's Gujarat branch GSTIN (24)
        $bill1 = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->mhBranch->id,
            'vendor_id' => $vendor->id,
            'vendor_branch_id' => $gjVendorBranch->id,
            'supplier_gstin' => '24AABCS8888M1Z0',
            'buyer_gstin' => '27AAACE1234F1Z5',
            'bill_number' => 'VB-BRANCH-001',
            'vendor_invoice_number' => 'SRT/26-27/001',
            'bill_date' => '2026-11-08',
            'due_date' => '2026-12-08',
            'status' => 'Posted',
            'subtotal' => 1000,
            'igst_amount' => 180,
            'tax_amount' => 180,
            'grand_total' => 1180,
        ]);

        $gstr2bJson = json_encode([
            'data' => [
                'gstin' => '27AAACE1234F1Z5',
                'rtnprd' => '112026',
                'docdata' => [
                    'b2b' => [[
                        'ctin' => '24AABCS8888M1Z0',
                        'trdnm' => 'SteelCraft India Pvt Ltd',
                        'inv' => [[
                            'inum' => 'SRT/26-27/001',
                            'dt' => '08-11-2026',
                            'val' => 1180,
                            'txval' => 1000,
                            'igst' => 180,
                            'cgst' => 0,
                            'sgst' => 0,
                        ]],
                    ]],
                ],
            ],
        ]);

        $gstr2bService = app(Gstr2bReconciliationService::class);
        $import = $gstr2bService->import($gstr2bJson, 'gstr2b-nov-2026.json', $this->maker->id);

        $line = $import->lines()->first();
        $this->assertNotNull($line);
        $this->assertSame($bill1->id, $line->vendor_bill_id);
        $this->assertSame('matched', $line->match_status);

        // Now add a second bill in the same period with Gujarat branch buyer_gstin (24) -> mixed-GSTIN guard blocks import
        VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->gjBranch->id,
            'vendor_id' => $vendor->id,
            'vendor_branch_id' => $gjVendorBranch->id,
            'supplier_gstin' => '24AABCS8888M1Z0',
            'buyer_gstin' => '24AAACE1234F1Z2',
            'bill_number' => 'VB-BRANCH-002',
            'vendor_invoice_number' => 'SRT/26-27/002',
            'bill_date' => '2026-11-14',
            'due_date' => '2026-12-14',
            'status' => 'Posted',
            'subtotal' => 2000,
            'cgst_amount' => 180,
            'sgst_amount' => 180,
            'tax_amount' => 360,
            'grand_total' => 2360,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $gstr2bService->import($gstr2bJson, 'gstr2b-nov-2026-mixed.json', $this->maker->id);
    }
}
