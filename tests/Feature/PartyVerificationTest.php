<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\PartyVerificationLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyVerificationException;
use App\Services\PartyVerificationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRS 2.3 / task C-15: second-person verification of customers and vendors.
 */
class PartyVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $maker;
    private User $verifier;
    private User $executive;

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

        $this->maker = $this->userWithRole('maker@example.com', 'tenant_owner');
        $this->verifier = $this->userWithRole('verifier@example.com', 'company_admin');
        $this->executive = $this->userWithRole('exec@example.com', 'sales_executive');
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

    private function pendingCustomer(array $overrides = []): Customer
    {
        $this->actingAs($this->maker);

        return Customer::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'gstin' => '22AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'bank_name' => 'HDFC',
            'account_number' => '123456789',
            'ifsc_code' => 'HDFC0001234',
        ], app(PartyVerificationService::class)->pendingAttributes($this->maker)));
    }

    // ---------------------------------------------------------------- backward compatibility

    /** @test */
    public function parties_created_the_old_way_stay_verified_and_active(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Legacy', 'email' => 'l@example.com', 'status' => 'active']);
        $vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Old Vendor', 'status' => 'active']);

        $this->assertTrue($customer->fresh()->isVerified());
        $this->assertSame('active', $customer->fresh()->status);
        $this->assertTrue($vendor->fresh()->isVerified());
        $this->assertSame('active', $vendor->fresh()->status);
    }

    /** @test */
    public function an_unverified_party_can_never_be_saved_as_active(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sneaky',
            'email' => 's@example.com',
            'status' => 'active',
            'verification_status' => Customer::VERIFICATION_PENDING,
        ]);

        $this->assertSame('inactive', $customer->fresh()->status);

        $customer->update(['status' => 'active']);
        $this->assertSame('inactive', $customer->fresh()->status);
    }

    /** @test */
    public function switching_the_control_off_restores_the_old_behaviour(): void
    {
        config(['party_verification.enabled' => false]);

        $response = $this->as($this->maker)->post(route('crm.customers.store'), [
            'name' => 'No Control Ltd',
            'email' => 'nc@example.com',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $customer = Customer::where('name', 'No Control Ltd')->firstOrFail();
        $this->assertTrue($customer->isVerified());
        $this->assertSame('active', $customer->status);
    }

    // ---------------------------------------------------------------- customers

    /** @test */
    public function a_new_customer_starts_pending_and_inactive_with_an_audit_row(): void
    {
        $this->as($this->maker)->post(route('crm.customers.store'), [
            'name' => 'Fresh Ltd',
            'email' => 'fresh@example.com',
            'gstin' => '22AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'status' => 'active',
        ])->assertRedirect(route('crm.customers.index'));

        $customer = Customer::where('name', 'Fresh Ltd')->firstOrFail();
        $this->assertSame('pending_verification', $customer->verification_status);
        $this->assertSame('inactive', $customer->status);
        $this->assertSame($this->maker->id, (int) $customer->created_by);
        $this->assertSame($this->maker->id, (int) $customer->submitted_by);
        $this->assertSame(1, PartyVerificationLog::where('party_type', 'customer')->where('party_id', $customer->id)->where('action', 'submitted')->count());
    }

    /** @test */
    public function the_person_who_created_a_customer_cannot_verify_it(): void
    {
        $customer = $this->pendingCustomer();

        $response = $this->as($this->maker)->post(route('crm.customers.verify', $customer), [
            'checks' => ['gstin' => 1, 'pan' => 1, 'bank' => 1],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('pending_verification', $customer->fresh()->verification_status);
        $this->assertSame('inactive', $customer->fresh()->status);

        $this->expectException(PartyVerificationException::class);
        app(PartyVerificationService::class)->verify($customer, $this->maker, ['gstin' => 1, 'pan' => 1, 'bank' => 1]);
    }

    /** @test */
    public function a_different_authorised_person_can_verify_and_that_activates_the_customer(): void
    {
        $customer = $this->pendingCustomer();

        $this->as($this->verifier)->post(route('crm.customers.verify', $customer), [
            'checks' => ['gstin' => 1, 'pan' => 1, 'bank' => 1],
            'remarks' => 'Matched certificate',
        ])->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('verified', $customer->verification_status);
        $this->assertSame('active', $customer->status);
        $this->assertSame($this->verifier->id, (int) $customer->verified_by);
        $this->assertNotNull($customer->verified_at);
        $this->assertSame(1, PartyVerificationLog::where('party_id', $customer->id)->where('action', 'verified')->count());
    }

    /** @test */
    public function every_detail_the_party_has_must_be_confirmed_by_the_verifier(): void
    {
        $customer = $this->pendingCustomer();

        $this->as($this->verifier)->post(route('crm.customers.verify', $customer), [
            'checks' => ['gstin' => 1],
        ])->assertSessionHas('error');

        $this->assertSame('pending_verification', $customer->fresh()->verification_status);
    }

    /** @test */
    public function a_user_without_the_verify_permission_is_refused(): void
    {
        $customer = $this->pendingCustomer();

        $this->as($this->executive)->post(route('crm.customers.verify', $customer), [
            'checks' => ['gstin' => 1, 'pan' => 1, 'bank' => 1],
        ])->assertForbidden();

        $this->assertSame('pending_verification', $customer->fresh()->verification_status);
    }

    /** @test */
    public function a_pending_customer_cannot_be_activated_by_toggling_status(): void
    {
        $customer = $this->pendingCustomer();

        $this->as($this->maker)->get(route('crm.customers.toggleStatus', ['customer' => $customer, 'status' => 'active']))
            ->assertSessionHas('error');

        $this->as($this->maker)->patch(route('crm.customers.updateStatus', $customer), ['status' => 'active'])
            ->assertSessionHas('error');

        $this->assertSame('inactive', $customer->fresh()->status);
    }

    /** @test */
    public function changing_tax_or_bank_details_on_a_verified_customer_requires_reverification(): void
    {
        $customer = $this->pendingCustomer();
        app(PartyVerificationService::class)->verify($customer, $this->verifier, ['gstin' => 1, 'pan' => 1, 'bank' => 1]);
        $this->assertSame('active', $customer->fresh()->status);

        // Editing only the name keeps it verified.
        $this->as($this->maker)->put(route('crm.customers.update', $customer), [
            'name' => 'Acme Renamed',
            'email' => 'acme@example.com',
            'gstin' => '22AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'bank_name' => 'HDFC',
            'account_number' => '123456789',
            'ifsc_code' => 'HDFC0001234',
            'status' => 'active',
        ]);
        $this->assertSame('verified', $customer->fresh()->verification_status);
        $this->assertSame('active', $customer->fresh()->status);

        // Changing the bank account sends it back.
        $this->as($this->maker)->put(route('crm.customers.update', $customer), [
            'name' => 'Acme Renamed',
            'email' => 'acme@example.com',
            'gstin' => '22AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'bank_name' => 'HDFC',
            'account_number' => '999999999',
            'ifsc_code' => 'HDFC0001234',
            'status' => 'active',
        ]);

        $customer->refresh();
        $this->assertSame('pending_verification', $customer->verification_status);
        $this->assertSame('inactive', $customer->status);
        $this->assertSame($this->maker->id, (int) $customer->submitted_by);
        $this->assertNull($customer->verified_by);
        $this->assertSame(1, PartyVerificationLog::where('party_id', $customer->id)->where('action', 'reverification_required')->count());

        // The person who changed the bank account cannot approve their own change,
        // even though a different person originally verified the party.
        $this->expectException(PartyVerificationException::class);
        app(PartyVerificationService::class)->verify($customer, $this->maker, ['gstin' => 1, 'pan' => 1, 'bank' => 1]);
    }

    /** @test */
    public function a_returned_customer_needs_a_reason_and_goes_back_to_pending_when_corrected(): void
    {
        $customer = $this->pendingCustomer();

        $this->as($this->verifier)->post(route('crm.customers.verification.return', $customer), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->as($this->verifier)->post(route('crm.customers.verification.return', $customer), ['reason' => 'IFSC does not match'])
            ->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('returned', $customer->verification_status);
        $this->assertSame('inactive', $customer->status);

        $this->as($this->maker)->put(route('crm.customers.update', $customer), [
            'name' => 'Acme Co',
            'email' => 'acme@example.com',
            'gstin' => '22AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'bank_name' => 'HDFC',
            'account_number' => '123456789',
            'ifsc_code' => 'HDFC0009999',
            'status' => 'inactive',
        ]);

        $this->assertSame('pending_verification', $customer->fresh()->verification_status);
    }

    /** @test */
    public function the_verification_queue_is_only_for_verifiers(): void
    {
        $this->pendingCustomer();

        $this->as($this->verifier)->get(route('crm.customers.verification.index'))
            ->assertOk()->assertSee('Acme Co');

        $this->as($this->executive)->get(route('crm.customers.verification.index'))->assertForbidden();
    }

    // ---------------------------------------------------------------- vendors

    /** @test */
    public function a_new_vendor_goes_through_the_same_second_person_check(): void
    {
        $this->as($this->maker)->post(route('purchase.vendors.store'), [
            'name' => 'Steel Traders',
            'gstin' => '27AAAAA0000A1Z5',
            'pan' => 'AAAAA0000A',
            'bank_name' => 'ICICI',
            'account_number' => '555566667777',
            'ifsc_code' => 'ICIC0000001',
            'status' => 'active',
        ]);

        $vendor = Vendor::where('name', 'Steel Traders')->firstOrFail();
        $this->assertSame('pending_verification', $vendor->verification_status);
        $this->assertSame('inactive', $vendor->status);

        $this->as($this->maker)->post(route('purchase.vendors.verify', $vendor->id), [
            'checks' => ['gstin' => 1, 'pan' => 1, 'bank' => 1],
        ])->assertSessionHas('error');
        $this->assertSame('pending_verification', $vendor->fresh()->verification_status);

        $this->as($this->verifier)->post(route('purchase.vendors.verify', $vendor->id), [
            'checks' => ['gstin' => 1, 'pan' => 1, 'bank' => 1],
        ])->assertSessionHas('success');

        $vendor->refresh();
        $this->assertSame('verified', $vendor->verification_status);
        $this->assertSame('active', $vendor->status);
        $this->assertSame($this->verifier->id, (int) $vendor->verified_by);
    }

    /** @test */
    public function a_pending_vendor_cannot_be_toggled_active(): void
    {
        $this->as($this->maker)->post(route('purchase.vendors.store'), ['name' => 'Pending Vendor', 'status' => 'active']);
        $vendor = Vendor::where('name', 'Pending Vendor')->firstOrFail();

        $this->as($this->maker)->post(route('purchase.vendors.toggle-status', $vendor->id))->assertSessionHas('error');

        $this->assertSame('inactive', $vendor->fresh()->status);
    }
    /** @test */
    public function the_customer_page_shows_the_verification_panel_to_the_right_people(): void
    {
        $customer = $this->pendingCustomer();

        $this->as($this->verifier)->get(route('crm.customers.show', $customer))
            ->assertOk()
            ->assertSee('Pending verification')
            ->assertSee('Verify and activate');

        $this->as($this->maker)->get(route('crm.customers.show', $customer))
            ->assertOk()
            ->assertSee('a different authorised person must verify')
            ->assertDontSee('Verify and activate');
    }

    /** @test */
    public function the_vendor_page_shows_the_verification_panel(): void
    {
        $this->actingAs($this->maker);
        $vendor = Vendor::create(array_merge(
            ['tenant_id' => $this->tenant->id, 'name' => 'Panel Vendor', 'gstin' => '27AAAAA0000A1Z5'],
            app(PartyVerificationService::class)->pendingAttributes($this->maker)
        ));

        $this->as($this->verifier)->get(route('purchase.vendors.show', $vendor->id))
            ->assertOk()
            ->assertSee('Verify and activate');
    }

    /** @test */
    public function create_forms_show_a_fixed_inactive_note_instead_of_an_active_choice(): void
    {
        foreach ([route('crm.customers.create'), route('purchase.vendors.create')] as $url) {
            $this->as($this->maker)->get($url)
                ->assertOk()
                ->assertSee('Inactive until verified')
                ->assertSee('name="status" value="inactive"', false)
                ->assertDontSee('<option value="active"', false);
        }
    }

    /** @test */
    public function create_forms_keep_the_dropdown_when_the_control_is_off(): void
    {
        config(['party_verification.enabled' => false]);

        foreach ([route('crm.customers.create'), route('purchase.vendors.create')] as $url) {
            $this->as($this->maker)->get($url)
                ->assertOk()
                ->assertSee('<option value="active"', false)
                ->assertDontSee('Inactive until verified');
        }
    }

    /** @test */
    public function edit_forms_lock_status_for_unverified_parties_and_keep_it_for_verified_ones(): void
    {
        $pending = $this->pendingCustomer();
        $this->as($this->maker)->get(route('crm.customers.edit', $pending))
            ->assertOk()
            ->assertSee('Inactive: pending verification')
            ->assertDontSee('<option value="active"', false);

        app(PartyVerificationService::class)->verify($pending, $this->verifier, ['gstin' => 1, 'pan' => 1, 'bank' => 1]);
        // Verified: status is shown read-only; changing it goes through the reason form (C-16).
        $this->as($this->maker)->get(route('crm.customers.edit', $pending))
            ->assertOk()
            ->assertSee('Make inactive')
            ->assertDontSee('<option value="active"', false);

        $this->actingAs($this->maker);
        $vendor = Vendor::create(array_merge(
            ['tenant_id' => $this->tenant->id, 'name' => 'Locked Vendor'],
            app(PartyVerificationService::class)->pendingAttributes($this->maker)
        ));
        $this->as($this->maker)->get(route('purchase.vendors.edit', $vendor->id))
            ->assertOk()
            ->assertSee('Inactive: pending verification')
            ->assertDontSee('<option value="active"', false);

        $legacy = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Legacy Vendor', 'status' => 'active']);
        $this->as($this->maker)->get(route('purchase.vendors.edit', $legacy->id))
            ->assertOk()
            ->assertSee('Make inactive')
            ->assertDontSee('<option value="active"', false);

        // With the control off the original dropdown is back.
        config(['party_verification.enabled' => false]);
        $this->as($this->maker)->get(route('purchase.vendors.edit', $legacy->id))
            ->assertOk()
            ->assertSee('<option value="active"', false);
    }

    /** @test */
    public function the_customer_list_hides_mark_as_active_for_unverified_customers_only(): void
    {
        $pending = $this->pendingCustomer(['name' => 'Pending Co', 'email' => 'pending@example.com']);
        $legacy = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Deactivated Co', 'email' => 'deact@example.com', 'status' => 'inactive']);

        $html = $this->as($this->maker)->get(route('crm.customers.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('customers/' . $pending->id . '/toggle-status?status=active', $html);
        $this->assertStringContainsString('customers/' . $legacy->id . '/toggle-status?status=active', $html);

        // Once verified, the option appears.
        app(PartyVerificationService::class)->verify($pending, $this->verifier, ['gstin' => 1, 'pan' => 1, 'bank' => 1]);
        $pending->update(['status' => 'inactive']);
        $html = $this->as($this->maker)->get(route('crm.customers.index'))->getContent();
        $this->assertStringContainsString('customers/' . $pending->id . '/toggle-status?status=active', $html);
    }
}
