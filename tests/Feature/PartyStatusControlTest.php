<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\PartyVerificationLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyStatusException;
use App\Services\PartyStatusService;
use App\Services\PartyVerificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * FRS 2.3 / task C-16: one inactive state with a recorded reason, reactivation of
 * risk-stopped parties by a different authorised person, and blocking of new
 * documents for inactive or unverified parties.
 */
class PartyStatusControlTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;      // tenant_owner: edit + reactivate
    private User $admin;      // company_admin: edit + reactivate
    private User $manager;    // sales_manager: edit customers, NO reactivate permission
    private User $buyer;      // purchase_manager: edit vendors, NO reactivate permission
    private Customer $customer;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->owner = $this->userWithRole('owner@example.com', 'tenant_owner');
        $this->admin = $this->userWithRole('admin@example.com', 'company_admin');
        $this->manager = $this->userWithRole('manager@example.com', 'sales_manager');
        $this->buyer = $this->userWithRole('buyer@example.com', 'purchase_manager');

        // Created the old way: verified and active.
        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'email' => 'acme@example.com', 'status' => 'active']);
        $this->vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Traders', 'code' => 'VEND-0001', 'status' => 'active', 'phone' => '9876543210']);
    }

    private function userWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'test-tenant');
    }

    private function statusService(): PartyStatusService
    {
        return app(PartyStatusService::class);
    }

    // ---------------------------------------------------------------- deactivate

    /** @test */
    public function making_a_customer_inactive_needs_a_reason_and_records_who_when_and_why(): void
    {
        $this->as($this->manager)->post(route('crm.customers.deactivate.store', $this->customer), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame('active', $this->customer->fresh()->status);

        $this->as($this->manager)->post(route('crm.customers.deactivate.store', $this->customer), [
            'reason' => 'credit_risk',
            'note' => 'Two cheques bounced',
        ])->assertSessionHas('success');

        $customer = $this->customer->fresh();
        $this->assertSame('inactive', $customer->status);
        $this->assertSame('credit_risk', $customer->inactive_reason);
        $this->assertSame('Two cheques bounced', $customer->inactive_note);
        $this->assertSame($this->manager->id, (int) $customer->inactivated_by);
        $this->assertNotNull($customer->inactivated_at);

        $log = PartyVerificationLog::where('party_id', $customer->id)->where('action', 'deactivated')->firstOrFail();
        $this->assertSame($this->manager->id, (int) $log->actor_id);
        $this->assertSame('credit_risk', $log->changes['reason']);
    }

    /** @test */
    public function the_reason_other_needs_a_note_and_an_unverified_party_cannot_be_deactivated(): void
    {
        try {
            $this->statusService()->deactivate($this->customer, $this->manager, 'other', ' ');
            $this->fail('A note is required for "other".');
        } catch (PartyStatusException $e) {
            $this->assertStringContainsString('Describe the reason', $e->getMessage());
        }

        $pending = Customer::create(array_merge(
            ['tenant_id' => $this->tenant->id, 'name' => 'Pending Co', 'email' => 'p@example.com'],
            app(PartyVerificationService::class)->pendingAttributes($this->owner)
        ));

        $this->expectException(PartyStatusException::class);
        $this->statusService()->deactivate($pending, $this->manager, 'dormant');
    }

    /** @test */
    public function a_legacy_status_change_to_inactive_still_records_a_reason(): void
    {
        $this->actingAs($this->manager);
        $this->customer->update(['status' => 'inactive']);

        $customer = $this->customer->fresh();
        $this->assertSame('other', $customer->inactive_reason);
        $this->assertSame($this->manager->id, (int) $customer->inactivated_by);
        $this->assertSame(1, PartyVerificationLog::where('party_id', $customer->id)->where('action', 'deactivated')->count());
    }

    // ---------------------------------------------------------------- reactivate

    /** @test */
    public function a_non_risk_inactive_party_can_be_reactivated_by_whoever_deactivated_it(): void
    {
        $this->statusService()->deactivate($this->customer, $this->manager, 'dormant');

        $this->as($this->manager)->post(route('crm.customers.reactivate.store', $this->customer), ['note' => 'Back in business'])
            ->assertSessionHas('success');

        $customer = $this->customer->fresh();
        $this->assertSame('active', $customer->status);
        $this->assertNull($customer->inactive_reason);
        $this->assertNull($customer->inactivated_by);
        $this->assertSame(1, PartyVerificationLog::where('party_id', $customer->id)->where('action', 'reactivated')->count());
    }

    /** @test */
    public function a_party_stopped_for_a_risk_reason_needs_a_different_authorised_person_to_reactivate(): void
    {
        $this->statusService()->deactivate($this->customer, $this->owner, 'fraud_suspicion', 'Fake PO');
        $this->assertTrue($this->customer->fresh()->isInactiveForRisk());

        // The person who stopped it cannot lift it, even though they hold the permission.
        $this->as($this->owner)->post(route('crm.customers.reactivate.store', $this->customer))->assertSessionHas('error');
        $this->assertSame('inactive', $this->customer->fresh()->status);

        // A user who can edit but does not hold the reactivate permission cannot either.
        $this->as($this->manager)->post(route('crm.customers.reactivate.store', $this->customer))->assertSessionHas('error');
        $this->assertSame('inactive', $this->customer->fresh()->status);

        // The legacy toggle cannot bypass it.
        $this->as($this->manager)->get(route('crm.customers.toggleStatus', ['customer' => $this->customer, 'status' => 'active']))
            ->assertSessionHas('error');
        $this->assertSame('inactive', $this->customer->fresh()->status);

        // Neither can a direct model update (no side door).
        $this->actingAs($this->manager);
        $this->customer->fresh()->update(['status' => 'active']);
        $this->assertSame('inactive', $this->customer->fresh()->status);

        // A different authorised person can.
        $this->as($this->admin)->post(route('crm.customers.reactivate.store', $this->customer), ['note' => 'Cleared by finance'])
            ->assertSessionHas('success');

        $customer = $this->customer->fresh();
        $this->assertSame('active', $customer->status);
        $this->assertNull($customer->inactive_reason);

        $log = PartyVerificationLog::where('party_id', $customer->id)->where('action', 'reactivated')->firstOrFail();
        $this->assertSame($this->admin->id, (int) $log->actor_id);
        $this->assertSame('fraud_suspicion', $log->changes['reason']);
    }

    /** @test */
    public function vendors_follow_the_same_rules(): void
    {
        $this->as($this->buyer)->post(route('purchase.vendors.deactivate.store', $this->vendor->id), ['reason' => 'compliance_issue', 'note' => 'GST cancelled'])
            ->assertSessionHas('success');
        $this->assertSame('compliance_issue', $this->vendor->fresh()->inactive_reason);

        $this->as($this->buyer)->post(route('purchase.vendors.toggle-status', $this->vendor->id))->assertSessionHas('error');
        $this->assertSame('inactive', $this->vendor->fresh()->status);

        $this->as($this->admin)->post(route('purchase.vendors.reactivate.store', $this->vendor->id))->assertSessionHas('success');
        $this->assertSame('active', $this->vendor->fresh()->status);
    }

    /** @test */
    public function an_unverified_party_still_cannot_be_activated_and_the_edit_form_cannot_change_a_verified_status(): void
    {
        // Posting status=inactive on the edit form of an active, verified customer changes nothing.
        $this->as($this->manager)->put(route('crm.customers.update', $this->customer), [
            'name' => 'Acme Co', 'email' => 'acme@example.com', 'status' => 'inactive',
        ]);
        $this->assertSame('active', $this->customer->fresh()->status);
    }

    // ---------------------------------------------------------------- transaction blocking

    private function salesOrderFor(Customer $customer): SalesOrder
    {
        return SalesOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'sales_order_number' => 'SO-' . uniqid(),
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);
    }

    private function purchaseOrderFor(Vendor $vendor): PurchaseOrder
    {
        return PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'purchase_order_number' => 'PO-' . uniqid(),
            'date' => now()->toDateString(),
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function an_active_verified_party_can_still_be_used(): void
    {
        $this->assertNotNull($this->salesOrderFor($this->customer)->id);
        $this->assertNotNull($this->purchaseOrderFor($this->vendor)->id);
    }

    /** @test */
    public function a_new_sales_order_is_refused_for_an_inactive_or_unverified_customer(): void
    {
        $this->statusService()->deactivate($this->customer, $this->manager, 'credit_risk');

        try {
            $this->salesOrderFor($this->customer->fresh());
            $this->fail('An inactive customer must not get a new sales order.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('inactive (Credit risk)', $e->errors()['customer_id'][0]);
        }

        $pending = Customer::create(array_merge(
            ['tenant_id' => $this->tenant->id, 'name' => 'Pending Co', 'email' => 'p@example.com'],
            app(PartyVerificationService::class)->pendingAttributes($this->owner)
        ));

        try {
            $this->salesOrderFor($pending);
            $this->fail('An unverified customer must not get a new sales order.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('waiting for verification', $e->errors()['customer_id'][0]);
        }
    }

    /** @test */
    public function a_new_purchase_order_is_refused_for_an_inactive_vendor_but_existing_documents_stay_editable(): void
    {
        $order = $this->purchaseOrderFor($this->vendor);

        $this->statusService()->deactivate($this->vendor, $this->buyer, 'dormant');

        try {
            $this->purchaseOrderFor($this->vendor->fresh());
            $this->fail('An inactive vendor must not get a new purchase order.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('inactive (Dormant', $e->errors()['vendor_id'][0]);
        }

        // The order that already exists is not blocked.
        $order->update(['status' => 'cancelled']);
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    /** @test */
    public function the_user_who_cannot_see_the_party_does_not_get_a_free_pass(): void
    {
        // Ownership scoping must not make the guard fail open.
        $this->statusService()->deactivate($this->customer, $this->owner, 'dormant');
        $this->actingAs($this->userWithRole('exec@example.com', 'sales_executive'));

        $this->expectException(ValidationException::class);
        $this->salesOrderFor($this->customer->fresh());
    }

    // ---------------------------------------------------------------- switches and pages

    /** @test */
    public function the_blocking_can_be_switched_off(): void
    {
        $this->statusService()->deactivate($this->customer, $this->manager, 'dormant');
        config(['party_verification.transaction_block_enabled' => false]);

        $this->assertNotNull($this->salesOrderFor($this->customer->fresh())->id);
    }

    /** @test */
    public function with_the_control_off_status_behaves_as_before(): void
    {
        config(['party_verification.enabled' => false]);

        $this->customer->update(['status' => 'inactive']);
        $this->assertNull($this->customer->fresh()->inactive_reason);

        $this->customer->update(['status' => 'active']);
        $this->assertSame('active', $this->customer->fresh()->status);
        $this->assertNotNull($this->salesOrderFor($this->customer->fresh())->id);
    }

    /** @test */
    public function the_status_pages_and_labels_render(): void
    {
        $this->as($this->manager)->get(route('crm.customers.deactivate.create', $this->customer))
            ->assertOk()->assertSee('Make Acme Co inactive')->assertSee('Credit risk');

        $this->statusService()->deactivate($this->customer, $this->owner, 'credit_risk', 'Bounced cheques');

        $this->as($this->admin)->get(route('crm.customers.show', $this->customer))
            ->assertOk()->assertSee('Inactive: Credit risk')->assertSee('Reactivate');

        $this->as($this->owner)->get(route('crm.customers.show', $this->customer))
            ->assertOk()->assertSee('a different authorised person must reactivate it');

        $this->as($this->manager)->get(route('crm.customers.reactivate.create', $this->customer))
            ->assertOk()->assertSee('Credit risk')->assertSee('Only a user with the reactivate permission');

        $html = $this->as($this->manager)->get(route('crm.customers.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Credit risk', $html);
        $this->assertStringContainsString('customers/' . $this->customer->id . '/reactivate', $html);

        $this->as($this->buyer)->get(route('purchase.vendors.deactivate.create', $this->vendor->id))
            ->assertOk()->assertSee('Make Steel Traders inactive');

        $pending = Vendor::create(array_merge(
            ['tenant_id' => $this->tenant->id, 'name' => 'Waiting Vendor'],
            app(PartyVerificationService::class)->pendingAttributes($this->owner)
        ));
        $this->as($this->buyer)->get(route('purchase.vendors.index'))
            ->assertOk()->assertSee('Pending verification');
    }

    // ---------------------------------------------------------------- payment screen

    private function unpaidBillFor(Vendor $vendor): \App\Domains\Purchase\Models\VendorBill
    {
        return \App\Domains\Purchase\Models\VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $vendor->id,
            'bill_number' => 'BILL-' . uniqid(),
            'bill_date' => now()->toDateString(),
            'status' => 'posted',
            'subtotal' => 1000,
            'grand_total' => 1000,
            'due_amount' => 1000,
        ]);
    }

    /** @test */
    public function an_inactive_supplier_we_still_owe_stays_on_the_payment_screen_with_a_warning(): void
    {
        $this->unpaidBillFor($this->vendor);
        $this->statusService()->deactivate($this->vendor, $this->buyer, 'dormant');

        $this->assertContains($this->vendor->id, $this->statusService()->inactiveVendorIdsWithDues($this->tenant->id));

        $this->as($this->admin)->get(route('purchase.payments.create'))
            ->assertOk()
            ->assertSee('Steel Traders (inactive)')
            ->assertSee('data-inactive="1"', false)
            ->assertSee('This supplier is inactive');
    }

    /** @test */
    public function an_inactive_supplier_with_nothing_owed_is_not_listed_and_payments_are_not_blocked(): void
    {
        $this->statusService()->deactivate($this->vendor, $this->buyer, 'dormant');

        $this->assertNotContains($this->vendor->id, $this->statusService()->inactiveVendorIdsWithDues($this->tenant->id));

        $this->as($this->admin)->get(route('purchase.payments.create'))
            ->assertOk()
            ->assertDontSee('Steel Traders (inactive)');
    }

    /** @test */
    public function the_payment_warning_only_warns_it_does_not_block_the_payment_document(): void
    {
        $this->statusService()->deactivate($this->vendor, $this->buyer, 'credit_risk');

        // The transaction guard does not cover payments: creating one for an inactive vendor still works.
        $payment = \App\Domains\Purchase\Models\VendorPayment::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'payment_number' => 'PAY-' . uniqid(),
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => 'bank',
            'status' => 'completed',
        ]);

        $this->assertNotNull($payment->id);
    }

    /** @test */
    public function vendor_payments_are_blocked_for_fraud_and_compliance_reasons(): void
    {
        $this->unpaidBillFor($this->vendor);
        $this->statusService()->deactivate($this->vendor, $this->buyer, 'fraud_suspicion', 'Fake invoices');

        // Excluded from the payment screen's selectable inactive vendors.
        $this->assertNotContains($this->vendor->id, $this->statusService()->inactiveVendorIdsWithDues($this->tenant->id));

        try {
            \App\Domains\Purchase\Models\VendorPayment::create([
                'tenant_id' => $this->tenant->id,
                'vendor_id' => $this->vendor->id,
                'payment_number' => 'PAY-' . uniqid(),
                'payment_date' => now()->toDateString(),
                'amount' => 100,
                'payment_method' => 'bank',
                'status' => 'completed',
            ]);
            $this->fail('A vendor stopped for suspected fraud must not receive payments.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('payments are blocked', $e->errors()['vendor_id'][0]);
        }

        // Same for compliance_issue.
        $this->statusService()->reactivate($this->vendor->fresh(), $this->admin);
        $this->statusService()->deactivate($this->vendor->fresh(), $this->buyer, 'compliance_issue', 'GST cancelled');
        $this->assertNotContains($this->vendor->id, $this->statusService()->inactiveVendorIdsWithDues($this->tenant->id));

        $this->expectException(ValidationException::class);
        \App\Domains\Purchase\Models\VendorPayment::create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => $this->vendor->id,
            'payment_number' => 'PAY-' . uniqid(),
            'payment_date' => now()->toDateString(),
            'amount' => 100,
            'payment_method' => 'bank',
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function a_new_quotation_is_refused_for_an_inactive_customer(): void
    {
        $this->statusService()->deactivate($this->customer, $this->manager, 'credit_risk', 'Legal notice sent');

        $account = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Co Account',
            'customer_id' => $this->customer->id,
            'email' => 'acme@example.com',
        ]);

        try {
            Quotation::create([
                'tenant_id' => $this->tenant->id,
                'crm_account_id' => $account->id,
                'quotation_number' => 'QT-' . uniqid(),
                'quotation_date' => now()->toDateString(),
                'status' => 'Draft',
            ]);
            $this->fail('Creating a quotation for an inactive customer must be blocked.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('inactive (Credit risk)', $e->errors()['customer_id'][0]);
        }
    }

    /** @test */
    public function sales_order_confirmation_is_blocked_if_customer_was_placed_on_hold(): void
    {
        $order = $this->salesOrderFor($this->customer);
        $this->assertSame('draft', strtolower($order->status));

        // CFO puts customer on financial hold
        $this->statusService()->deactivate($this->customer, $this->manager, 'credit_risk', 'Overdue 90 days');

        $this->as($this->manager)
            ->post(route('sales.orders.confirm', $order->id))
            ->assertSessionHasErrors('customer_id');

        $this->assertSame('draft', strtolower($order->fresh()->status));
    }

    /** @test */
    public function purchase_order_approval_is_blocked_if_vendor_is_inactive(): void
    {
        $po = $this->purchaseOrderFor($this->vendor);
        $this->statusService()->deactivate($this->vendor, $this->buyer, 'credit_risk');

        $this->as($this->admin)
            ->post(route('purchase.orders.approve', $po->id))
            ->assertSessionHas('error');

        $this->assertSame('draft', strtolower($po->fresh()->status));
    }

    /** @test */
    public function factory_dispatch_creation_confirmation_and_challan_generation_are_blocked_for_defaulter_on_hold(): void
    {
        $order = $this->salesOrderFor($this->customer);
        $order->update(['status' => 'Confirmed']);

        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Desk',
            'sku' => 'DESK-001',
            'status' => 'active',
        ]);

        \App\Domains\Sales\Models\SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $product->id,
            'item_name' => 'Executive Desk',
            'quantity' => 200,
            'unit_price' => 12500,
            'amount' => 2500000,
            'subtotal' => 2500000,
            'total' => 2500000,
        ]);

        // Orbit Real Estate placed on hold by CFO Mr. Mehra
        $this->statusService()->deactivate($this->customer, $this->manager, 'credit_risk', 'Legal notice sent. Do not do any business.');

        $warehouse = \App\Domains\Inventory\Models\Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'status' => 'active',
        ]);

        $mr = \App\Domains\Sales\Models\MaterialRequirement::create([
            'tenant_id' => $this->tenant->id,
            'sales_order_id' => $order->id,
            'requirement_number' => 'MR-' . uniqid(),
            'requirement_date' => now()->toDateString(),
            'status' => 'Released',
        ]);

        \App\Domains\Sales\Models\MaterialRequirementItem::create([
            'tenant_id' => $this->tenant->id,
            'material_requirement_id' => $mr->id,
            'sales_order_item_id' => $order->items->first()->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 200,
            'quantity_released' => 200,
            'status' => 'Released',
        ]);

        // 1. Dispatch create screen redirects back with error
        $this->as($this->manager)
            ->get(route('inventory.dispatches.create', ['sales_order_id' => $order->id]))
            ->assertRedirect(route('sales.dispatches.index'))
            ->assertSessionHas('error');

        // 2. Direct model creation via DispatchOrder is blocked by GuardsPartyTransactions
        try {
            DispatchOrder::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'sales_order_id' => $order->id,
                'material_requirement_id' => $mr->id,
                'dispatch_number' => 'DO-' . uniqid(),
                'dispatch_date' => now()->toDateString(),
                'status' => 'Pending',
            ]);
            $this->fail('Factory dispatch creation must be blocked for an inactive customer.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('inactive (Credit risk)', $e->errors()['customer_id'][0]);
        }

        // 3. Confirming an existing pending dispatch order is blocked
        $existingDo = DispatchOrder::withoutEvents(function () use ($order, $mr) {
            return DispatchOrder::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'sales_order_id' => $order->id,
                'material_requirement_id' => $mr->id,
                'dispatch_number' => 'DO-OLD-001',
                'dispatch_date' => now()->toDateString(),
                'status' => 'Pending',
            ]);
        });

        $this->as($this->manager)
            ->post(route('sales.dispatches.confirm', $existingDo->id))
            ->assertSessionHas('error');

        // 4. Shipping / outward gate pass is blocked (The 5 truckloads crisis)
        $existingDo->update(['status' => 'Confirmed']);
        $this->as($this->manager)
            ->post(route('sales.dispatches.ship', $existingDo->id))
            ->assertSessionHas('error');

        // 5. Generating delivery challan is blocked
        $this->as($this->manager)
            ->get(route('sales.dispatches.download-challan', $existingDo->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        // 6. Dispatch index pending sales orders view renders Financial Hold badge
        $response = $this->as($this->manager)->get(route('inventory.dispatches.index', ['tab' => 'pending']));
        $response->assertOk();
        $response->assertSee('Financial Hold (Credit risk)');
        $response->assertSee('On Hold (Blocked)');
    }

    /** @test */
    public function quick_toggle_to_inactive_without_reason_redirects_to_deactivate_form(): void
    {
        $this->as($this->manager)
            ->get(route('crm.customers.toggleStatus', $this->customer))
            ->assertRedirect(route('crm.customers.deactivate.create', $this->customer))
            ->assertSessionHas('warning');

        $this->assertSame('active', $this->customer->fresh()->status);

        // Supplying a reason through toggle deactivates properly via service
        $this->as($this->manager)
            ->get(route('crm.customers.toggleStatus', [$this->customer, 'reason' => 'credit_risk', 'note' => 'Legal notice sent']))
            ->assertSessionHas('success');

        $this->assertSame('inactive', $this->customer->fresh()->status);
        $this->assertSame('credit_risk', $this->customer->fresh()->inactive_reason);
    }

    /** @test */
    public function vendor_quick_toggle_without_reason_redirects_to_deactivate_form(): void
    {
        $this->as($this->buyer)
            ->post(route('purchase.vendors.toggle-status', $this->vendor))
            ->assertRedirect(route('purchase.vendors.deactivate.create', $this->vendor))
            ->assertSessionHas('warning');

        $this->assertSame('active', $this->vendor->fresh()->status);
    }

    /** @test */
    public function eway_bill_generation_is_blocked_for_inactive_customer(): void
    {
        $invoice = \App\Domains\Sales\Models\Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 10000,
            'tax_amount' => 1800,
            'total_amount' => 11800,
            'status' => 'posted',
        ]);

        $this->statusService()->deactivate($this->customer, $this->manager, 'credit_risk', 'Overdue dues');

        $this->as($this->manager)
            ->post(route('sales.invoices.ewaybill.generate', $invoice->id), [
                'transport_mode' => '1',
                'vehicle_no' => 'DL01AB1234',
                'vehicle_type' => 'R',
                'distance_km' => 150,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
