<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorDocument;
use App\Domains\Purchase\Models\VendorItem;
use App\Domains\Purchase\Models\VendorItemPriceHistory;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRS 2.3 / Task C-19: Vendor Master Intelligence & Compliance Tracking.
 * Tests Vendor Item Codes, MOQ, Lead Times, Rate History Logs, and Document Expiry Alerts.
 */
class VendorIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $buyer;
    private Vendor $vendor;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Manufacturing Tenant',
            'slug' => 'mfg-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(\App\Support\Tenancy::class)->setTenant($this->tenant);
        // Web routes sit behind the tenant middleware, which resolves the tenant from this header.
        $this->withHeader('X-Tenant', 'mfg-tenant');
        $this->seed(RbacSeeder::class);

        $this->buyer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'buyer@example.com',
        ]);

        $role = \App\Models\Access\Role::where('slug', 'tenant_owner')->first();
        if ($role) {
            \App\Models\Access\UserRole::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->buyer->id,
                'role_id' => $role->id,
            ]);
        }

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Century Plyboard Supplies',
            'code' => 'VEND-0010',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => '18mm Marine Plywood',
            'sku' => 'PLY-18MM-MR',
            'unit_cost' => 120.00,
            'minimum_order_qty' => 5.00,
        ]);
    }

    public function test_can_map_product_to_vendor_with_code_moq_lead_time_and_rate(): void
    {
        $this->actingAs($this->buyer);

        $response = $this->post(route('purchase.vendors.items.store', $this->vendor->id), [
            'product_id' => $this->product->id,
            'vendor_item_code' => 'CP-710-18',
            'vendor_item_name' => 'Century Club Prime 18mm',
            'unit_price' => 135.50,
            'moq' => 50.00,
            'lead_time_days' => 10,
            'is_preferred' => 1,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_items', [
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'vendor_item_code' => 'CP-710-18',
            'vendor_item_name' => 'Century Club Prime 18mm',
            'unit_price' => 135.50,
            'moq' => 50.00,
            'lead_time_days' => 10,
            'is_preferred' => 1,
        ]);

        // Verifies initial price history row was recorded
        $this->assertDatabaseHas('vendor_item_price_histories', [
            'tenant_id' => $this->tenant->id,
            'old_price' => null,
            'new_price' => 135.50,
            'reason' => 'Initial contracted rate setup',
        ]);
    }

    public function test_updating_vendor_item_price_logs_rate_history(): void
    {
        $this->actingAs($this->buyer);

        $vendorItem = VendorItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'vendor_item_code' => 'CP-710-18',
            'unit_price' => 135.50,
            'moq' => 50.00,
            'lead_time_days' => 10,
        ]);

        // Rate increases to 145.00
        $response = $this->put(route('purchase.vendors.items.update', [$this->vendor->id, $vendorItem->id]), [
            'vendor_item_code' => 'CP-710-18-REV',
            'unit_price' => 145.00,
            'moq' => 60.00,
            'lead_time_days' => 12,
            'price_change_reason' => 'Q4 Timber rate increase',
            'effective_from' => '2026-11-20',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_items', [
            'id' => $vendorItem->id,
            'vendor_item_code' => 'CP-710-18-REV',
            'unit_price' => 145.00,
            'moq' => 60.00,
            'lead_time_days' => 12,
        ]);

        $this->assertDatabaseHas('vendor_item_price_histories', [
            'vendor_item_id' => $vendorItem->id,
            'old_price' => 135.50,
            'new_price' => 145.00,
            'reason' => 'Q4 Timber rate increase',
        ]);

        // The date column is cast, so compare it through the model rather than the raw DB string.
        $this->assertSame('2026-11-20', $vendorItem->priceHistories()->first()->effective_from->toDateString());
    }

    public function test_vendor_items_lookup_endpoint_returns_catalog_data(): void
    {
        $this->actingAs($this->buyer);

        VendorItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'vendor_item_code' => 'CP-710-18',
            'vendor_item_name' => 'Century Club Prime',
            'unit_price' => 140.00,
            'moq' => 25.00,
            'lead_time_days' => 7,
            'is_preferred' => true,
        ]);

        $response = $this->getJson(route('purchase.vendors.items.lookup', $this->vendor->id));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'vendor_id' => $this->vendor->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'internal_sku' => 'PLY-18MM-MR',
                        'vendor_item_code' => 'CP-710-18',
                        'vendor_item_name' => 'Century Club Prime',
                        'unit_price' => 140.00,
                        'moq' => 25.00,
                        'lead_time_days' => 7,
                        'is_preferred' => true,
                    ],
                ],
            ]);
    }

    public function test_can_upload_compliance_document_and_sync_ldc_fields(): void
    {
        $this->actingAs($this->buyer);

        $response = $this->post(route('purchase.vendors.documents.store', $this->vendor->id), [
            'document_type' => 'ldc_form13',
            'document_name' => 'Lower TDS Certificate FY26-27',
            'document_number' => 'LDC-2026-999',
            'issue_date' => '2026-04-01',
            'expiry_date' => '2027-03-31',
            'notes' => '1% TDS rate approved',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_documents', [
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'ldc_form13',
            'document_number' => 'LDC-2026-999',
        ]);

        // Verifies backward compatibility sync with Vendor model's TDS fields
        $this->vendor->refresh();
        $this->assertSame('LDC-2026-999', $this->vendor->ldc_number);
        $this->assertSame('2027-03-31', $this->vendor->ldc_valid_to?->toDateString());
    }

    public function test_user_without_vendor_permissions_cannot_use_item_and_document_endpoints(): void
    {
        $outsider = User::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'outsider@example.com']);
        $this->actingAs($outsider);

        $item = VendorItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'unit_price' => 100,
        ]);

        $this->post(route('purchase.vendors.items.store', $this->vendor->id), ['product_id' => $this->product->id])->assertForbidden();
        $this->put(route('purchase.vendors.items.update', [$this->vendor->id, $item->id]), ['unit_price' => 1])->assertForbidden();
        $this->delete(route('purchase.vendors.items.destroy', [$this->vendor->id, $item->id]))->assertForbidden();
        $this->getJson(route('purchase.vendors.items.lookup', $this->vendor->id))->assertForbidden();
        $this->getJson(route('purchase.vendors.items.price-history', [$this->vendor->id, $item->id]))->assertForbidden();
        $this->post(route('purchase.vendors.documents.store', $this->vendor->id), [
            'document_type' => 'msme',
            'document_name' => 'Udyam',
        ])->assertForbidden();

        $this->assertSame(100.0, $item->fresh()->unit_price);
    }

    public function test_mapping_a_removed_product_again_restores_it_with_rate_history(): void
    {
        $this->actingAs($this->buyer);

        $item = VendorItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'unit_price' => 100,
        ]);
        $item->delete();

        $this->post(route('purchase.vendors.items.store', $this->vendor->id), [
            'product_id' => $this->product->id,
            'unit_price' => 120,
            'moq' => 10,
            'lead_time_days' => 5,
        ])->assertSessionHas('success');

        $this->assertSame(1, VendorItem::withTrashed()->where('vendor_id', $this->vendor->id)->count());
        $this->assertDatabaseHas('vendor_items', ['id' => $item->id, 'unit_price' => 120, 'deleted_at' => null]);
        $this->assertDatabaseHas('vendor_item_price_histories', ['vendor_item_id' => $item->id, 'old_price' => 100, 'new_price' => 120]);
    }

    public function test_update_without_rate_moq_or_lead_time_keeps_saved_values(): void
    {
        $this->actingAs($this->buyer);

        $item = VendorItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'unit_price' => 135.50,
            'moq' => 50,
            'lead_time_days' => 10,
        ]);

        $this->putJson(route('purchase.vendors.items.update', [$this->vendor->id, $item->id]), ['vendor_item_code' => 'NEW-CODE'])
            ->assertOk();

        $this->assertDatabaseHas('vendor_items', ['id' => $item->id, 'vendor_item_code' => 'NEW-CODE', 'unit_price' => 135.50, 'moq' => 50, 'lead_time_days' => 10]);
        $this->assertDatabaseCount('vendor_item_price_histories', 0);
    }

    public function test_download_serves_private_files_and_older_public_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->buyer);

        $make = fn (string $path) => VendorDocument::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'msme',
            'document_name' => 'Udyam',
            'file_path' => $path,
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->put('docs/new.pdf', 'private');
        \Illuminate\Support\Facades\Storage::disk('public')->put('docs/old.pdf', 'legacy');

        $this->get(route('purchase.vendors.documents.download', [$this->vendor->id, $make('docs/new.pdf')->id]))->assertOk();
        $this->get(route('purchase.vendors.documents.download', [$this->vendor->id, $make('docs/old.pdf')->id]))->assertOk();
        $this->get(route('purchase.vendors.documents.download', [$this->vendor->id, $make('docs/missing.pdf')->id]))->assertSessionHas('error');
    }

    public function test_expiry_command_alerts_once_per_reminder_and_refreshes_status(): void
    {
        $soon = VendorDocument::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'iso_cert',
            'document_name' => 'ISO 9001',
            'reminder_days' => [30, 15, 7],
            'expiry_date' => now()->addDays(10)->toDateString(),
        ]);
        $gone = VendorDocument::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'msme',
            'document_name' => 'Udyam',
            'expiry_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('purchase:check-vendor-document-expiry')->assertSuccessful();

        // 10 days left has passed the 30 and 15 day marks, so both are recorded by one alert.
        $soon->refresh();
        $this->assertEqualsCanonicalizing([30, 15], $soon->alert_history['sent_days']);
        $this->assertSame('expiring_soon', $soon->status);
        $this->assertTrue($gone->fresh()->alert_history['expired']);
        $this->assertSame('expired', $gone->fresh()->status);

        // A second run sends nothing new until the next reminder day (7) is reached.
        $this->artisan('purchase:check-vendor-document-expiry')
            ->expectsOutputToContain('0 expiring alert(s), 0 expired alert(s)')
            ->assertSuccessful();

        // Renewing the document resets its alert history.
        $soon->update(['expiry_date' => now()->addDays(100)->toDateString()]);
        $this->artisan('purchase:check-vendor-document-expiry')->assertSuccessful();
        $this->assertSame([], $soon->fresh()->alert_history['sent_days']);
        $this->assertSame('valid', $soon->fresh()->status);
    }

    public function test_document_expiry_status_computation_and_alerts(): void
    {
        $this->actingAs($this->buyer);

        // 1. Expired document (expired 10 days ago)
        $expiredDoc = VendorDocument::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'msme',
            'document_name' => 'Old MSME Certificate',
            'expiry_date' => now()->subDays(10)->toDateString(),
        ]);

        // 2. Expiring soon document (expires in 15 days)
        $expiringDoc = VendorDocument::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'iso_cert',
            'document_name' => 'ISO 9001:2015',
            'expiry_date' => now()->addDays(15)->toDateString(),
        ]);

        // 3. Valid document (expires in 120 days)
        $validDoc = VendorDocument::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'document_type' => 'agreement',
            'document_name' => 'Annual Supply Agreement',
            'expiry_date' => now()->addDays(120)->toDateString(),
        ]);

        $this->assertTrue($expiredDoc->isExpired());
        $this->assertSame('expired', $expiredDoc->computed_status);

        $this->assertTrue($expiringDoc->isExpiringSoon(30));
        $this->assertSame('expiring_soon', $expiringDoc->computed_status);

        $this->assertFalse($validDoc->isExpired());
        $this->assertFalse($validDoc->isExpiringSoon(30));
        $this->assertSame('valid', $validDoc->computed_status);

        // Check Vendor model alert helpers
        $this->assertTrue($this->vendor->hasDocumentAlerts());
        $this->assertCount(1, $this->vendor->expiredDocuments());
        $this->assertCount(1, $this->vendor->expiringDocuments(30));
    }

    public function test_fuzzy_name_matching_detects_near_spelling_duplicates(): void
    {
        $dupService = app(\App\Services\PartyDuplicateService::class);

        // Near-spelling typo: "Century Plyboards Supply" vs existing "Century Plyboard Supplies"
        $matches = $dupService->find(\App\Services\PartyDuplicateService::VENDOR, [
            'name' => 'Century Plyboards Supply',
        ], $this->tenant->id);

        $this->assertNotEmpty($matches);
        $this->assertSame($this->vendor->id, $matches[0]['id']);
        $this->assertContains('name', $matches[0]['matched']);

        // Suffix variation ("Shree Ram Traders" vs "Shree Ram Trading")
        $traderVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Shree Ram Traders',
            'code' => 'VEND-0077',
            'status' => 'active',
        ]);
        $traderMatches = $dupService->find(\App\Services\PartyDuplicateService::VENDOR, [
            'name' => 'Shree Ram Trading',
        ], $this->tenant->id);
        $this->assertNotEmpty($traderMatches);
        $this->assertSame($traderVendor->id, $traderMatches[0]['id']);

        // Single-letter inflection on short name ("Ram Electricals" vs "Rama Electricals")
        $elecVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Ram Electricals',
            'code' => 'VEND-0088',
            'status' => 'active',
        ]);
        $elecMatches = $dupService->find(\App\Services\PartyDuplicateService::VENDOR, [
            'name' => 'Rama Electricals',
        ], $this->tenant->id);
        $this->assertNotEmpty($elecMatches);
        $this->assertSame($elecVendor->id, $elecMatches[0]['id']);
    }

    public function test_vendor_edit_detects_duplicate_unless_confirmed(): void
    {
        $this->actingAs($this->buyer);

        $otherVendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'National Steel Works',
            'code' => 'VEND-0099',
            'status' => 'inactive',
            'gstin' => '27ABCDE1234F1Z5',
        ]);

        // Attempt to edit $this->vendor's GSTIN to match $otherVendor's GSTIN
        $response = $this->put(route('purchase.vendors.update', $this->vendor->id), [
            'name' => $this->vendor->name,
            'code' => $this->vendor->code,
            'status' => 'inactive',
            'gstin' => '27ABCDE1234F1Z5',
        ]);

        // Should redirect back with duplicate_matches in session
        $response->assertSessionHas('duplicate_matches');

        // Now resubmit with confirm_duplicate = 1
        $responseConfirm = $this->put(route('purchase.vendors.update', $this->vendor->id), [
            'name' => $this->vendor->name,
            'code' => $this->vendor->code,
            'status' => 'inactive',
            'gstin' => '27ABCDE1234F1Z5',
            'confirm_duplicate' => 1,
        ]);

        $responseConfirm->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendors', [
            'id' => $this->vendor->id,
            'gstin' => '27ABCDE1234F1Z5',
        ]);
    }

    public function test_rfq_portal_and_matrix_display_vendor_last_price(): void
    {
        $this->actingAs($this->buyer);

        // Map product to vendor with contracted last price of ₹140.00
        VendorItem::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'product_id' => $this->product->id,
            'vendor_item_code' => 'VP-140',
            'unit_price' => 140.00,
            'moq' => 20.00,
            'lead_time_days' => 5,
        ]);

        $rfq = \App\Domains\Purchase\Models\PurchaseRfq::create([
            'tenant_id' => $this->tenant->id,
            'rfq_number' => 'RFQ-TEST-0099',
            'rfq_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        $rfqItem = \App\Domains\Purchase\Models\PurchaseRfqItem::create([
            'tenant_id' => $this->tenant->id,
            'purchase_rfq_id' => $rfq->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'target_rate' => 130.00,
        ]);

        $rfqVendor = \App\Domains\Purchase\Models\PurchaseRfqVendor::create([
            'tenant_id' => $this->tenant->id,
            'purchase_rfq_id' => $rfq->id,
            'vendor_id' => $this->vendor->id,
            'token' => 'token-test-12345',
            'status' => 'Draft',
        ]);

        // Map item to vendor
        \DB::table('purchase_rfq_item_vendors')->insert([
            'purchase_rfq_item_id' => $rfqItem->id,
            'vendor_id' => $this->vendor->id,
        ]);

        // 1. Check RFQ Show (Matrix View) renders vendor part & last rate
        $showRes = $this->get(route('purchase.rfqs.show', $rfq->id));
        $showRes->assertOk();
        $showRes->assertSee('VP-140');
        $showRes->assertSee('140.00');

        // 2. Check RFQ Portal renders vendor part & last rate
        $portalRes = $this->get(route('purchase.rfqs.portal', $rfqVendor->token));
        $portalRes->assertOk();
        $portalRes->assertSee('VP-140');
        $portalRes->assertSee('140.00');
    }
}
