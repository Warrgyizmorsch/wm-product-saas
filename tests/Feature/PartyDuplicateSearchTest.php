<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\PartyBranch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PartyDuplicateService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FRS 2.3: search existing customers / vendors before a new one is created.
 */
class PartyDuplicateSearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Dup Tenant',
            'slug' => 'dup-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        app(\App\Support\Tenancy::class)->setTenant($this->tenant);
        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@dup.test',
            'password' => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function as()
    {
        return $this->actingAs($this->user)->withHeader('X-Tenant', 'dup-tenant');
    }

    private function customer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Traders Pvt Ltd',
            'email' => 'accounts@acme.test',
            'phone' => '9820012345',
            'gstin' => '27AAACA1234F1Z5',
            'pan' => 'AAACA1234F',
            'billing_address' => 'Plot 12, MIDC Andheri East, Mumbai',
            'status' => 'active',
        ], $overrides));
    }

    private function find(string $type, array $input, ?int $ignore = null): array
    {
        return app(PartyDuplicateService::class)->find($type, $input, $this->tenant->id, $ignore);
    }

    // ------------------------------------------------------------------ service

    /** @test */
    public function it_matches_on_gstin_pan_email_mobile_name_and_address(): void
    {
        $existing = $this->customer();

        $byGstin = $this->find('customer', ['name' => 'Other', 'gstin' => '27aaaca1234f1z5']);
        $this->assertSame($existing->id, $byGstin[0]['id']);
        $this->assertSame(['GSTIN'], $byGstin[0]['matched']);

        $this->assertSame(['PAN'], $this->find('customer', ['name' => 'Other', 'pan' => 'aaaca1234f'])[0]['matched']);
        $this->assertSame(['email'], $this->find('customer', ['name' => 'Other', 'email' => 'ACCOUNTS@acme.test'])[0]['matched']);
        $this->assertSame(['mobile'], $this->find('customer', ['name' => 'Other', 'phone' => '+91 98200-12345'])[0]['matched']);
        $this->assertSame(['name'], $this->find('customer', ['name' => 'ACME TRADERS PRIVATE LIMITED'])[0]['matched']);
        $this->assertSame(['address'], $this->find('customer', ['name' => 'Other', 'billing_address' => 'plot 12 MIDC, Andheri East - Mumbai'])[0]['matched']);
    }

    /** @test */
    public function an_unrelated_party_and_short_or_blank_values_do_not_match(): void
    {
        $this->customer();

        $this->assertSame([], $this->find('customer', ['name' => 'Totally Different Ltd', 'email' => 'x@y.test', 'phone' => '9000000001']));
        $this->assertSame([], $this->find('customer', ['name' => '', 'email' => '', 'phone' => '12345', 'billing_address' => 'Mumbai']));
        $this->assertSame([], $this->find('customer', []));
    }

    /** @test */
    public function a_party_already_merged_into_another_is_not_reported_as_a_duplicate(): void
    {
        $survivor = $this->customer(['name' => 'Survivor Co', 'email' => 'a@survivor.test', 'phone' => '9000000002', 'gstin' => null, 'pan' => null, 'billing_address' => null]);
        $retired = $this->customer(['merged_into_id' => $survivor->id]);

        $matches = $this->find('customer', ['gstin' => '27AAACA1234F1Z5']);

        $this->assertSame([], $matches);
        $this->assertNotSame($retired->id, $survivor->id);
    }

    /** @test */
    public function it_only_searches_the_same_party_type_and_tenant_and_can_ignore_one_record(): void
    {
        $existing = $this->customer();

        $this->assertSame([], $this->find('vendor', ['name' => 'Acme Traders Pvt Ltd', 'gstin' => '27AAACA1234F1Z5']));

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->assertSame([], app(PartyDuplicateService::class)->find('customer', ['gstin' => '27AAACA1234F1Z5'], $other->id));

        $this->assertSame([], $this->find('customer', ['gstin' => '27AAACA1234F1Z5'], $existing->id));
    }

    /** @test */
    public function a_gstin_already_used_by_a_branch_of_an_existing_party_points_at_that_party(): void
    {
        $existing = $this->customer(['gstin' => null, 'pan' => null]);
        PartyBranch::create([
            'tenant_id' => $this->tenant->id,
            'party_type' => PartyBranch::PARTY_CUSTOMER,
            'party_id' => $existing->id,
            'name' => 'Pune Site',
            'code' => 'PUN-01',
            'branch_type' => 'site',
            'gstin' => '27AAACA1234F2Z4',
        ]);

        $matches = $this->find('customer', ['name' => 'New Name', 'gstin' => '27AAACA1234F2Z4']);

        $this->assertSame($existing->id, $matches[0]['id']);
        $this->assertSame(['GSTIN'], $matches[0]['matched']);
    }

    // ------------------------------------------------------------------ web forms

    /** @test */
    public function the_customer_form_asks_for_confirmation_before_creating_a_duplicate(): void
    {
        $existing = $this->customer();

        $this->as()->from(route('crm.customers.create'))->post(route('crm.customers.store'), [
            'name' => 'Acme Traders',
            'email' => 'new@acme.test',
            'gstin' => '27AAACA1234F1Z5',
            'status' => 'active',
        ])->assertRedirect(route('crm.customers.create'))
            ->assertSessionHas('duplicate_matches', fn (array $m) => $m[0]['id'] === $existing->id);

        $this->assertSame(1, Customer::where('tenant_id', $this->tenant->id)->count());

        $this->as()->post(route('crm.customers.store'), [
            'name' => 'Acme Traders',
            'email' => 'new@acme.test',
            'gstin' => '27AAACA1234F1Z5',
            'status' => 'active',
            'confirm_duplicate' => 1,
        ])->assertRedirect(route('crm.customers.index'));

        $this->assertSame(2, Customer::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function a_customer_with_nothing_in_common_is_created_without_any_question(): void
    {
        $this->customer();

        $this->as()->post(route('crm.customers.store'), [
            'name' => 'Brand New Buyer',
            'email' => 'hello@brandnew.test',
            'status' => 'active',
        ])->assertRedirect(route('crm.customers.index'))->assertSessionMissing('duplicate_matches');

        $this->assertTrue(Customer::where('name', 'Brand New Buyer')->exists());
    }

    /** @test */
    public function the_vendor_form_asks_for_confirmation_before_creating_a_duplicate(): void
    {
        $existing = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Steel Works Ltd',
            'phone' => '9811122233',
            'gstin' => '27AAACS1234F1Z5',
            'status' => 'active',
        ]);

        $payload = ['name' => 'Steel Works Industries', 'phone' => '9811122233', 'status' => 'active'];

        $this->as()->from(route('purchase.vendors.create'))->post(route('purchase.vendors.store'), $payload)
            ->assertRedirect(route('purchase.vendors.create'))
            ->assertSessionHas('duplicate_matches', fn (array $m) => $m[0]['id'] === $existing->id);
        $this->assertFalse(Vendor::where('name', 'Steel Works Industries')->exists());

        $this->as()->post(route('purchase.vendors.store'), $payload + ['confirm_duplicate' => 1])->assertRedirect();
        $this->assertTrue(Vendor::where('name', 'Steel Works Industries')->exists());
    }

    /** @test */
    public function the_live_lookup_returns_matches_and_respects_permissions(): void
    {
        $existing = $this->customer();

        $this->as()->getJson(route('crm.customers.duplicate-check', ['gstin' => '27AAACA1234F1Z5']))
            ->assertOk()
            ->assertJsonPath('duplicates.0.id', $existing->id)
            ->assertJsonPath('duplicates.0.identity', true);

        $this->as()->getJson(route('purchase.vendors.duplicate-check', ['gstin' => '27AAACA1234F1Z5']))
            ->assertOk()->assertJsonPath('duplicates', []);

        $nobody = User::create(['tenant_id' => $this->tenant->id, 'name' => 'No Role', 'email' => 'none@dup.test', 'password' => bcrypt('x')]);
        $this->actingAs($nobody)->withHeader('X-Tenant', 'dup-tenant')
            ->getJson(route('crm.customers.duplicate-check', ['gstin' => '27AAACA1234F1Z5']))
            ->assertForbidden();
    }

    /** @test */
    public function the_lookup_is_refused_for_a_user_without_the_create_or_edit_right(): void
    {
        $customer = $this->customer();
        $vendor = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Works Ltd', 'gstin' => '27AAACS1234F1Z5', 'status' => 'active']);

        $nobody = User::create(['tenant_id' => $this->tenant->id, 'name' => 'No Role', 'email' => 'nobody@dup.test', 'password' => bcrypt('x')]);
        $as = fn () => $this->actingAs($nobody)->withHeader('X-Tenant', 'dup-tenant');

        $as()->getJson(route('purchase.vendors.duplicate-check', ['gstin' => '27AAACS1234F1Z5']))->assertForbidden();
        $as()->getJson(route('purchase.vendors.duplicate-check', ['gstin' => '27AAACS1234F1Z5', 'ignore_id' => $vendor->id]))->assertForbidden();
        // A customer this user cannot even see is hidden (404) rather than forbidden; either way nothing is returned.
        $this->assertContains(
            $as()->getJson(route('crm.customers.duplicate-check', ['gstin' => '27AAACA1234F1Z5', 'ignore_id' => $customer->id]))->getStatusCode(),
            [403, 404]
        );
        $as()->getJson(route('crm.customers.duplicate-check', ['gstin' => '27AAACA1234F1Z5']))->assertForbidden();
    }

    /** @test */
    public function editing_a_party_only_asks_when_an_identifying_field_was_changed_to_a_match(): void
    {
        $this->customer(['name' => 'Acme Traders Pvt Ltd', 'email' => 'accounts@acme.test', 'phone' => '9820012345']);
        // A look-alike that already existed: same name as the customer above.
        $second = $this->customer(['name' => 'Acme Traders', 'email' => 'second@acme.test', 'phone' => '9820099999', 'gstin' => null, 'pan' => null, 'billing_address' => null]);

        $payload = fn (array $over = []) => array_merge([
            'name' => 'Acme Traders', 'email' => 'second@acme.test', 'phone' => '9820099999', 'status' => 'active',
        ], $over);

        // Unrelated change (shipping address): the old look-alike is not raised again.
        $this->as()->put(route('crm.customers.update', $second), $payload(['shipping_address' => 'New godown, Pune']))
            ->assertSessionMissing('duplicate_matches');
        $this->assertSame('New godown, Pune', $second->fresh()->shipping_address);

        // Mobile changed to another customer's number: confirmation needed, nothing saved.
        $this->as()->from(route('crm.customers.edit', $second))
            ->put(route('crm.customers.update', $second), $payload(['phone' => '9820012345']))
            ->assertSessionHas('duplicate_matches');
        $this->assertSame('9820099999', $second->fresh()->phone);

        $this->as()->put(route('crm.customers.update', $second), $payload(['phone' => '9820012345', 'confirm_duplicate' => 1]))
            ->assertSessionMissing('duplicate_matches');
        $this->assertSame('9820012345', $second->fresh()->phone);
    }

    /** @test */
    public function the_update_apis_report_a_changed_match_with_409_until_confirmed(): void
    {
        $company = Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Dup Co']);
        $this->user->update(['company_id' => $company->id]);
        $api = fn () => $this->actingAs($this->user, 'sanctum')->withHeader('X-Tenant', 'dup-tenant');

        $other = $this->customer(['company_id' => $company->id]);
        $mine = $this->customer(['company_id' => $company->id, 'name' => 'Different Buyer', 'email' => 'mine@buyer.test', 'phone' => '9000000003', 'gstin' => null, 'pan' => null, 'billing_address' => null]);

        $api()->putJson("/api/sales/customers/{$mine->id}", ['gstin' => '27AAACA1234F1Z5'])
            ->assertStatus(409)->assertJsonPath('duplicates.0.id', $other->id);
        $this->assertNull($mine->fresh()->gstin);

        $api()->putJson("/api/sales/customers/{$mine->id}", ['gstin' => '27AAACA1234F1Z5', 'confirm_duplicate' => true])->assertOk();

        $vendorA = Vendor::create(['tenant_id' => $this->tenant->id, 'company_id' => $company->id, 'name' => 'Steel Works Ltd', 'gstin' => '27AAACS1234F1Z5', 'status' => 'active']);
        $vendorB = Vendor::create(['tenant_id' => $this->tenant->id, 'company_id' => $company->id, 'name' => 'Other Metals', 'status' => 'active']);

        $api()->putJson("/api/purchase/vendors/{$vendorB->id}", ['gstin' => '27AAACS1234F1Z5'])
            ->assertStatus(409)->assertJsonPath('duplicates.0.id', $vendorA->id);

        // Not touching an identifying field never asks.
        $api()->putJson("/api/purchase/vendors/{$vendorB->id}", ['payment_terms' => 'Net 30'])->assertOk();
    }

    /** @test */
    public function fuzzy_name_matching_catches_spelling_variants_but_not_different_short_names(): void
    {
        $this->customer(['name' => 'Agarwal Hardware Stores', 'email' => 'a@x.test', 'phone' => '9000000010', 'gstin' => null, 'pan' => null, 'billing_address' => null]);

        // Typo / phonetic variants of a longer word are reported.
        $this->assertSame(['name'], $this->find('customer', ['name' => 'Aggarwal Hardware Stores'])[0]['matched']);
        $this->assertSame(['name'], $this->find('customer', ['name' => 'Agarwal Hardware Store'])[0]['matched']);

        // Different short words, or an extra/missing word, are different names.
        $this->customer(['name' => 'Shree Ram Enterprises', 'email' => 'b@x.test', 'phone' => '9000000011', 'gstin' => null, 'pan' => null, 'billing_address' => null]);
        $this->assertSame([], $this->find('customer', ['name' => 'Shree Sai Enterprises']));
        $this->assertSame([], $this->find('customer', ['name' => 'Shree Ram']));
        $this->assertSame([], $this->find('customer', ['name' => 'Agarwal Hardware']));

        // Traders vs Trading (stem variant) and Ram vs Rama (single-letter inflection) match
        $this->customer(['name' => 'Shree Ram Traders', 'email' => 'c@x.test', 'phone' => '9000000012', 'gstin' => null, 'pan' => null, 'billing_address' => null]);
        $this->assertSame(['name'], $this->find('customer', ['name' => 'Shree Ram Trading'])[0]['matched']);

        $this->customer(['name' => 'Ram Electricals', 'email' => 'd@x.test', 'phone' => '9000000013', 'gstin' => null, 'pan' => null, 'billing_address' => null]);
        $this->assertSame(['name'], $this->find('customer', ['name' => 'Rama Electricals'])[0]['matched']);
    }

    // ------------------------------------------------------------------ quick-create modal

    /** @test */
    public function quick_create_refuses_the_same_gstin_email_or_mobile_but_not_a_similar_name(): void
    {
        $this->customer();

        $this->as()->postJson(route('crm.customers.quick-create'), [
            'name' => 'Someone Else', 'email' => 'accounts@acme.test',
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->as()->postJson(route('crm.customers.quick-create'), [
            'name' => 'Acme Traders', 'email' => 'different@acme.test',
        ])->assertOk();

        $this->assertTrue(Customer::where('email', 'different@acme.test')->exists());
    }

    // ------------------------------------------------------------------ API

    /** @test */
    public function the_customer_api_reports_a_duplicate_with_409_until_confirmed(): void
    {
        Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Dup Co']);
        $existing = $this->customer();
        $payload = ['name' => 'Acme API', 'email' => 'api@acme.test', 'gstin' => '27AAACA1234F1Z5'];

        $api = fn () => $this->actingAs($this->user, 'sanctum')->withHeader('X-Tenant', 'dup-tenant');

        $api()->postJson('/api/sales/customers', $payload)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('duplicates.0.id', $existing->id);
        $this->assertFalse(Customer::where('email', 'api@acme.test')->exists());

        $api()->postJson('/api/sales/customers', $payload + ['confirm_duplicate' => true])->assertCreated();
        $this->assertTrue(Customer::where('email', 'api@acme.test')->exists());
    }

    /** @test */
    public function the_vendor_api_reports_a_duplicate_with_409_until_confirmed(): void
    {
        Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Dup Co']);
        $existing = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Works Ltd', 'gstin' => '27AAACS1234F1Z5', 'status' => 'active']);
        $payload = ['name' => 'Steel Works API', 'gstin' => '27AAACS1234F1Z5'];

        $api = fn () => $this->actingAs($this->user, 'sanctum')->withHeader('X-Tenant', 'dup-tenant');

        $api()->postJson('/api/purchase/vendors', $payload)
            ->assertStatus(409)->assertJsonPath('duplicates.0.id', $existing->id);
        $api()->postJson('/api/purchase/vendors/quick-create', $payload)->assertStatus(409);

        $api()->postJson('/api/purchase/vendors', $payload + ['confirm_duplicate' => true])->assertCreated();
        $this->assertTrue(Vendor::where('name', 'Steel Works API')->exists());
    }
}
