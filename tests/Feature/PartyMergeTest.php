<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Models\PartyVerificationLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyMergeService;
use App\Services\PartyStatusService;
use App\Services\PartyTransactionGuard;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * FRS 2.3 / Task C-18: Party Merge Tool.
 * Tests consolidating duplicate customer and vendor records into surviving master records.
 */
class PartyMergeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private PartyMergeService $mergeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Merge Tenant',
            'slug' => 'merge-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        app(\App\Support\Tenancy::class)->setTenant($this->tenant);
        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Priya Accountant',
            'email' => 'priya@apex.test',
            'password' => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->mergeService = app(PartyMergeService::class);
    }

    private function as()
    {
        return $this->actingAs($this->user)->withHeader('X-Tenant', 'merge-tenant');
    }

    public function test_customer_merge_moves_invoices_and_archives_duplicate(): void
    {
        // Target: Customer #101 (Reliance Retail Ltd)
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reliance Retail Ltd',
            'email' => 'finance@reliance.test',
            'gstin' => '27AAACR1234F1Z1',
            'status' => 'active',
        ]);

        // Source: Customer #204 (Reliance Retail - Mumbai Central)
        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reliance Retail - Mumbai Central',
            'email' => 'mumbai@reliance.test',
            'gstin' => '27AAACR1234F1Z1',
            'status' => 'active',
        ]);

        // Create 2 invoices on target and 3 invoices on source
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $target->id,
            'invoice_number' => 'INV-TARGET-001',
            'invoice_date' => now()->subMonth(),
            'total_amount' => 100000.00,
            'status' => 'paid',
        ]);
        Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $target->id,
            'invoice_number' => 'INV-TARGET-002',
            'invoice_date' => now()->subMonth(),
            'total_amount' => 100000.00,
            'status' => 'paid',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            Invoice::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $source->id,
                'invoice_number' => "INV-SOURCE-00{$i}",
                'invoice_date' => now(),
                'total_amount' => 400000.00,
                'balance_due' => 400000.00,
                'status' => 'unpaid',
            ]);
        }

        $this->assertCount(2, $target->invoices);
        $this->assertCount(3, $source->invoices);

        // Preview before merge
        $preview = $this->mergeService->preview(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->tenant->id
        );
        $this->assertSame(3, $preview['documents']['invoices_count']);
        $this->assertSame(1200000.0, $preview['documents']['invoices_total']);

        // Execute merge
        $result = $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $this->assertSame(3, $result['moved_counts']['invoices']);

        // Refresh models
        $target->refresh();
        $source->refresh();

        // Target should now own all 5 invoices
        $this->assertCount(5, $target->invoices);
        $this->assertCount(0, $source->invoices);

        // Source must be permanently archived as duplicate
        $this->assertSame('inactive', $source->status);
        $this->assertSame('duplicate', $source->inactive_reason);
        $this->assertSame($target->id, $source->merged_into_id);
        $this->assertNotNull($source->merged_at);
        $this->assertSame($this->user->id, $source->merged_by);

        // Audit logs recorded
        $this->assertTrue(PartyVerificationLog::where('party_id', $source->id)->where('action', 'merged')->exists());
        $this->assertTrue(PartyVerificationLog::where('party_id', $target->id)->where('action', 'merged_from')->exists());
    }

    public function test_customer_merge_adopts_branches_and_contacts(): void
    {
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Motors Ltd',
            'email' => 'accounts@tatamotors.test',
            'status' => 'active',
        ]);

        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Motors - Pune Plant',
            'email' => 'pune@tatamotors.test',
            'status' => 'active',
        ]);

        // Branch and contact on source
        $sourceBranch = PartyBranch::create([
            'tenant_id' => $this->tenant->id,
            'party_type' => PartyBranch::PARTY_CUSTOMER,
            'party_id' => $source->id,
            'name' => 'Pune Assembly Line',
            'code' => 'BR-PUNE',
            'branch_type' => 'site',
            'city' => 'Pune',
            'status' => 'active',
            'verified_at' => now(),
        ]);

        $sourceContact = PartyContact::create([
            'tenant_id' => $this->tenant->id,
            'party_type' => PartyContact::PARTY_CUSTOMER,
            'party_id' => $source->id,
            'name' => 'Alok Plant Head',
            'status' => 'active',
        ]);

        $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $sourceBranch->refresh();
        $sourceContact->refresh();

        $this->assertSame($target->id, $sourceBranch->party_id);
        $this->assertFalse((bool) $sourceBranch->is_head_office);

        $this->assertSame($target->id, $sourceContact->party_id);
    }

    public function test_customer_merge_resolves_head_office_branch_code_collision(): void
    {
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Target Corp',
            'status' => 'active',
        ]);

        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Source Corp',
            'status' => 'active',
        ]);

        // Target and Source both automatically receive an auto-created Head Office branch with code 'HO'
        $targetHo = $target->headOfficeBranch();
        $sourceHo = $source->headOfficeBranch();

        $this->assertNotNull($targetHo);
        $this->assertNotNull($sourceHo);
        $this->assertSame('HO', $targetHo->code);
        $this->assertSame('HO', $sourceHo->code);

        $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $sourceHo->refresh();
        $this->assertSame($target->id, $sourceHo->party_id);
        $this->assertNotSame('HO', $sourceHo->code);
        $this->assertFalse((bool) $sourceHo->is_head_office);
    }

    public function test_vendor_merge_moves_purchase_orders_and_bills(): void
    {
        $targetVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Blum Hardware India Pvt Ltd',
            'status' => 'active',
        ]);

        $sourceVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Blum India Mumbai',
            'status' => 'active',
        ]);

        PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $sourceVendor->id,
            'purchase_order_number' => 'PO-SOURCE-001',
            'date' => now(),
            'grand_total' => 50000.00,
            'status' => 'Draft',
        ]);

        VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $sourceVendor->id,
            'bill_number' => 'BILL-SOURCE-001',
            'bill_date' => now(),
            'grand_total' => 50000.00,
            'status' => 'Draft',
        ]);

        $this->mergeService->merge(
            PartyMergeService::VENDOR,
            $sourceVendor->id,
            $targetVendor->id,
            $this->user
        );

        $this->assertSame(1, PurchaseOrder::where('vendor_id', $targetVendor->id)->count());
        $this->assertSame(0, PurchaseOrder::where('vendor_id', $sourceVendor->id)->count());

        $this->assertSame(1, VendorBill::where('vendor_id', $targetVendor->id)->count());
        $this->assertSame(0, VendorBill::where('vendor_id', $sourceVendor->id)->count());

        $sourceVendor->refresh();
        $this->assertSame('inactive', $sourceVendor->status);
        $this->assertSame($targetVendor->id, $sourceVendor->merged_into_id);
    }

    public function test_vendor_merge_moves_catalog_items_documents_and_other_vendor_links(): void
    {
        $target = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Blum India', 'status' => 'active']);
        $source = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Blum India Mumbai', 'status' => 'active']);

        $shared = \App\Domains\Inventory\Models\Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Hinge', 'sku' => 'HNG-1', 'unit_cost' => 10]);
        $onlySource = \App\Domains\Inventory\Models\Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Slide', 'sku' => 'SLD-1', 'unit_cost' => 20]);
        $preferred = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Knob', 'sku' => 'KNB-1', 'unit_cost' => 5, 'preferred_vendor_id' => $source->id,
        ]);

        $targetShared = \App\Domains\Purchase\Models\VendorItem::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $target->id, 'product_id' => $shared->id, 'unit_price' => 11,
        ]);
        $sourceShared = \App\Domains\Purchase\Models\VendorItem::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $source->id, 'product_id' => $shared->id, 'unit_price' => 9,
        ]);
        $sourceOnly = \App\Domains\Purchase\Models\VendorItem::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $source->id, 'product_id' => $onlySource->id, 'vendor_item_code' => 'SL-9',
        ]);
        $document = \App\Domains\Purchase\Models\VendorDocument::create([
            'tenant_id' => $this->tenant->id, 'vendor_id' => $source->id, 'document_type' => 'msme', 'document_name' => 'Udyam',
        ]);

        $this->mergeService->merge(PartyMergeService::VENDOR, $source->id, $target->id, $this->user);

        // Non-overlapping mapping and the document follow the surviving vendor.
        $this->assertSame($target->id, $sourceOnly->fresh()->vendor_id);
        $this->assertSame($target->id, $document->fresh()->vendor_id);
        $this->assertSame($target->id, $preferred->fresh()->preferred_vendor_id);

        // Same product on both: the target's mapping and rate win; the source's row is retired.
        $this->assertSame(11.0, $targetShared->fresh()->unit_price);
        $this->assertNull(\App\Domains\Purchase\Models\VendorItem::find($sourceShared->id));
        $this->assertSame(
            1,
            \App\Domains\Purchase\Models\VendorItem::where('vendor_id', $target->id)->where('product_id', $shared->id)->count()
        );
    }

    public function test_merge_stores_reason_and_original_status_in_audit_log(): void
    {
        $target = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Primary Vendor', 'status' => 'active']);
        $source = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Primary Vendor Dup', 'status' => 'active']);

        $this->mergeService->merge(PartyMergeService::VENDOR, $source->id, $target->id, $this->user, ['reason' => 'Same GSTIN entered twice']);

        $log = PartyVerificationLog::where('party_id', $source->id)->where('action', 'merged')->firstOrFail();
        $this->assertSame('active', $log->from_status);
        $this->assertStringContainsString('Same GSTIN entered twice', $log->remarks);
        $this->assertStringContainsString('Same GSTIN entered twice', $source->fresh()->inactive_note);
    }

    public function test_merge_refuses_self_merge_or_already_merged_parties(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Self Corp',
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);
        $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $customer->id,
            $customer->id,
            $this->user
        );
    }

    public function test_transaction_guard_blocks_new_orders_on_merged_party(): void
    {
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Surviving Corp',
            'status' => 'active',
        ]);

        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Archived Corp',
            'status' => 'active',
        ]);

        $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $guard = app(PartyTransactionGuard::class);
        $reason = $guard->blockedReason('customer', $source->id, $this->tenant->id, 'sales order');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('has been merged into another record', $reason);
    }

    public function test_web_customer_merge_endpoints(): void
    {
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Web Target',
            'status' => 'active',
        ]);

        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Web Source',
            'status' => 'active',
        ]);

        // Preview endpoint
        $previewRes = $this->as()->getJson(route('crm.customers.merge.preview', [
            'customer' => $source->id,
            'target_id' => $target->id,
        ]));
        $previewRes->assertOk();
        $previewRes->assertJsonPath('success', true);

        // Execute merge endpoint
        $mergeRes = $this->as()->post(route('crm.customers.merge.store', $source->id), [
            'target_id' => $target->id,
            'confirm_merge' => '1',
            'reason' => 'Duplicate created by sales',
        ]);

        $mergeRes->assertRedirect(route('crm.customers.show', $target->id));
        $mergeRes->assertSessionHas('success');

        $source->refresh();
        $this->assertSame($target->id, $source->merged_into_id);
    }

    public function test_customer_merge_hands_over_crm_account_when_target_has_none(): void
    {
        // Target genuinely has NO CRM account (skip auto-creation)
        Customer::$skipAccountAutoCreate = true;
        try {
            $target = Customer::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Target Without CRM',
                'status' => 'active',
            ]);

            $source = Customer::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Source With CRM',
                'status' => 'active',
            ]);
        } finally {
            Customer::$skipAccountAutoCreate = false;
        }

        $sourceAccount = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $source->id,
            'name' => 'Source Account',
            'account_number' => 'ACC-TEST-001',
        ]);

        $deal = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $sourceAccount->id,
            'deal_number' => 'DL-TEST-001',
            'title' => 'Big Deal',
            'stage' => 'Proposal',
        ]);

        $quotation = Quotation::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $sourceAccount->id,
            'crm_deal_id' => $deal->id,
            'quotation_number' => 'QT-9999',
            'quotation_date' => now(),
            'status' => 'Sent',
        ]);

        $result = $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $this->assertSame(1, $result['moved_counts']['crm_deals']);
        $this->assertSame(1, $result['moved_counts']['quotations']);

        // Account is handed over to target
        $sourceAccount->refresh();
        $this->assertSame($target->id, $sourceAccount->customer_id);

        // Target's crmAccount relation now points to this account
        $target->refresh();
        $this->assertNotNull($target->crmAccount);
        $this->assertSame($sourceAccount->id, $target->crmAccount->id);
    }

    public function test_party_status_service_blocks_reactivation_of_merged_records(): void
    {
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Living Corp',
            'status' => 'active',
        ]);

        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Merged Corp',
            'status' => 'active',
        ]);

        $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $source->refresh();
        $this->assertTrue($source->isMerged());

        $statusService = app(PartyStatusService::class);
        $blockedReason = $statusService->reactivationBlockedReason($this->user, $source);

        $this->assertNotNull($blockedReason);
        $this->assertStringContainsString("permanently merged into record #{$target->id}", $blockedReason);
    }

    public function test_preview_warns_on_legal_entity_pan_mismatch(): void
    {
        // Source in Maharashtra
        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reliance Retail Maharashtra',
            'gstin' => '27AAACR1234F1Z1',
            'status' => 'active',
        ]);

        // Target with completely different company PAN (Tata Motors)
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Motors Ltd',
            'gstin' => '27AAACT5678F1Z2',
            'status' => 'active',
        ]);

        $preview = $this->mergeService->preview(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            (int) $this->tenant->id
        );

        $this->assertTrue($preview['legal_entity_check']['pan_mismatch']);
        $this->assertStringContainsString('Legal entity mismatch', $preview['legal_entity_check']['warning']);
    }

    public function test_customer_merge_consolidates_multiple_source_crm_accounts_and_deals(): void
    {
        // Target Customer with its auto-created CRM account
        $target = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Target Customer Master',
            'status' => 'active',
        ]);
        $targetAccount = $target->crmAccount;
        $this->assertNotNull($targetAccount);

        // Source Customer has its auto-created primary CRM account PLUS a second CRM account
        $source = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Source Multi-Account Customer',
            'status' => 'active',
        ]);
        $sourceAccount1 = $source->crmAccount;
        $this->assertNotNull($sourceAccount1);

        $deal1 = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $sourceAccount1->id,
            'deal_number' => 'DL-RETAIL-01',
            'title' => 'Retail Deal 1',
            'stage' => 'Proposal',
        ]);

        $sourceAccount2 = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $source->id,
            'name' => 'Source Account 2 (Enterprise)',
            'account_number' => 'ACC-SRC-002',
        ]);
        $deal2 = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $sourceAccount2->id,
            'deal_number' => 'DL-ENTERPRISE-02',
            'title' => 'Enterprise Deal 2',
            'stage' => 'Negotiation',
        ]);

        // Preview should count deals across both accounts
        $preview = $this->mergeService->preview(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            (int) $this->tenant->id
        );
        $this->assertSame(2, $preview['documents']['deals_count']);

        // Execute merge
        $result = $this->mergeService->merge(
            PartyMergeService::CUSTOMER,
            $source->id,
            $target->id,
            $this->user
        );

        $this->assertSame(2, $result['moved_counts']['crm_deals']);

        // Both deals must now point to the target master CRM account
        $deal1->refresh();
        $deal2->refresh();
        $this->assertSame($targetAccount->id, $deal1->crm_account_id);
        $this->assertSame($targetAccount->id, $deal2->crm_account_id);

        // Both redundant source accounts should be soft-deleted
        $this->assertTrue($sourceAccount1->fresh()->trashed());
        $this->assertTrue($sourceAccount2->fresh()->trashed());

        // Target customer now has 2 deals accessible via deals relation
        $this->assertSame(2, $target->deals()->count());
    }

    public function test_merge_refuses_target_that_was_already_merged_elsewhere(): void
    {
        $survivor = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Survivor', 'status' => 'active']);
        $archived = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Archived', 'status' => 'active']);
        $other = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Other', 'status' => 'active']);

        $this->mergeService->merge(PartyMergeService::CUSTOMER, $archived->id, $survivor->id, $this->user);

        try {
            $this->mergeService->merge(PartyMergeService::CUSTOMER, $other->id, $archived->id, $this->user);
            $this->fail('Merging into an already merged record must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('target_id', $e->errors());
        }

        $this->assertNull($other->fresh()->merged_into_id);
    }

    public function test_user_with_edit_but_no_merge_permission_cannot_merge_customer(): void
    {
        $editor = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plain Editor',
            'email' => 'editor@apex.test',
            'password' => bcrypt('password'),
        ]);

        $target = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'T', 'status' => 'active']);
        $source = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'S', 'status' => 'active']);

        $this->actingAs($editor)->withHeader('X-Tenant', 'merge-tenant')
            ->post(route('crm.customers.merge.store', $source->id), [
                'target_id' => $target->id,
                'confirm_merge' => '1',
            ])->assertForbidden();

        $this->assertNull($source->fresh()->merged_into_id);
    }
}
