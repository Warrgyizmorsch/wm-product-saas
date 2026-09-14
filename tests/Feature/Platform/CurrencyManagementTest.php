<?php

namespace Tests\Feature\Platform;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Currency;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $superAdmin;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);

        $this->superAdmin = $this->userWithRole('super@example.com', 'super_admin');
        $this->tenantOwner = $this->userWithRole('owner@example.com', 'tenant_owner');
    }

    private function userWithRole(string $email, string $roleSlug): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => ucfirst($roleSlug), 'email' => $email, 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        return $user;
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'test-tenant');
    }

    /** @test */
    public function super_admin_can_create_edit_and_deactivate_a_currency(): void
    {
        $this->as($this->superAdmin)->post(route('platform.currencies.store'), [
            'code' => 'xts', 'name' => 'Test Currency', 'symbol' => 'T', 'decimals' => 2,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $currency = Currency::where('code', 'XTS')->firstOrFail();
        $this->assertTrue($currency->is_active);

        // The code is ignored on update — it is referenced by value elsewhere.
        $this->as($this->superAdmin)->put(route('platform.currencies.update', $currency), [
            'code' => 'ABC', 'name' => 'Renamed Currency', 'symbol' => 'TT', 'decimals' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $currency->refresh();
        $this->assertSame('XTS', $currency->code);
        $this->assertSame('Renamed Currency', $currency->name);
        $this->assertSame(3, $currency->decimals);

        $this->as($this->superAdmin)->patch(route('platform.currencies.status', $currency))->assertRedirect();
        $this->assertFalse($currency->fresh()->is_active);

        $this->as($this->superAdmin)->get(route('platform.currencies.index'))->assertOk()->assertSee('XTS');
    }

    /** @test */
    public function tenant_owner_cannot_manage_the_platform_currency_list(): void
    {
        $this->as($this->tenantOwner)->get(route('platform.currencies.index'))->assertForbidden();
        $this->as($this->tenantOwner)->post(route('platform.currencies.store'), [
            'code' => 'XTS', 'name' => 'Test Currency', 'symbol' => 'T', 'decimals' => 2,
        ])->assertForbidden();

        $this->assertNull(Currency::where('code', 'XTS')->first());
    }

    /** @test */
    public function duplicate_codes_and_out_of_range_decimals_are_rejected(): void
    {
        $this->as($this->superAdmin)->post(route('platform.currencies.store'), [
            'code' => 'GBP', 'name' => 'Duplicate', 'symbol' => '£', 'decimals' => 2,
        ])->assertSessionHasErrors('code');

        $this->as($this->superAdmin)->post(route('platform.currencies.store'), [
            'code' => 'XTS', 'name' => 'Too precise', 'symbol' => 'T', 'decimals' => 5,
        ])->assertSessionHasErrors('decimals');
    }

    /** @test */
    public function seeder_adds_the_iso_list_without_overwriting_customised_currencies(): void
    {
        Currency::where('code', 'GBP')->update(['name' => 'British Pound', 'is_active' => false]);

        $this->seed(CurrencySeeder::class);
        $this->seed(CurrencySeeder::class); // idempotent

        $this->assertGreaterThan(100, Currency::count());
        $this->assertSame(1, Currency::where('code', 'GBP')->count());
        $this->assertSame('British Pound', Currency::where('code', 'GBP')->value('name'));
        $this->assertFalse((bool) Currency::where('code', 'GBP')->value('is_active'));
    }
}
