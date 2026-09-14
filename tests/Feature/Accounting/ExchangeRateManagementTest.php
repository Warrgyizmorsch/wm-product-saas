<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\ExchangeRateSyncSetting;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRateManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);

        $this->accountant = $this->userWithRole('accountant@example.com', 'accountant');
        $this->auditor = $this->userWithRole('auditor@example.com', 'auditor');
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

    private function rateRow(array $overrides = []): ExchangeRate
    {
        return ExchangeRate::create($overrides + [
            'tenant_id' => $this->tenant->id,
            'from_currency' => 'GBP',
            'to_currency' => 'INR',
            'rate' => 105.5,
            'effective_date' => '2026-09-01',
            'source' => ExchangeRate::SOURCE_MANUAL,
        ]);
    }

    /** @test */
    public function accountant_can_add_list_and_edit_rates(): void
    {
        $this->as($this->accountant)->post(route('accounting.exchange-rates.store'), [
            'from_currency' => 'gbp', 'to_currency' => 'INR', 'rate' => '105.5', 'effective_date' => '2026-09-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rate = ExchangeRate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame('GBP', $rate->from_currency);
        $this->assertSame(ExchangeRate::SOURCE_MANUAL, $rate->source);
        $this->assertSame($this->accountant->id, $rate->created_by);

        $this->as($this->accountant)->get(route('accounting.exchange-rates.index'))
            ->assertOk()
            ->assertSee('1 GBP')
            ->assertSee('105.5 INR');

        $this->as($this->accountant)->put(route('accounting.exchange-rates.update', $rate), [
            'from_currency' => 'GBP', 'to_currency' => 'INR', 'rate' => '106', 'effective_date' => '2026-09-01',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(106.0, $rate->fresh()->rate, 1e-9);
    }

    /** @test */
    public function editing_a_synced_rate_turns_it_into_a_manual_rate(): void
    {
        $rate = $this->rateRow(['source' => ExchangeRate::SOURCE_API]);

        $this->as($this->accountant)->put(route('accounting.exchange-rates.update', $rate), [
            'from_currency' => 'GBP', 'to_currency' => 'INR', 'rate' => '104', 'effective_date' => '2026-09-01',
        ])->assertRedirect();

        $this->assertSame(ExchangeRate::SOURCE_MANUAL, $rate->fresh()->source);
    }

    /** @test */
    public function duplicate_pair_and_date_or_same_currency_is_rejected(): void
    {
        $this->rateRow();

        $this->as($this->accountant)->post(route('accounting.exchange-rates.store'), [
            'from_currency' => 'GBP', 'to_currency' => 'INR', 'rate' => '107', 'effective_date' => '2026-09-01',
        ])->assertSessionHasErrors('effective_date');

        $this->as($this->accountant)->post(route('accounting.exchange-rates.store'), [
            'from_currency' => 'GBP', 'to_currency' => 'GBP', 'rate' => '1', 'effective_date' => '2026-09-02',
        ])->assertSessionHasErrors('to_currency');

        $this->assertSame(1, ExchangeRate::withoutGlobalScopes()->count());
    }

    /** @test */
    public function auditor_can_view_but_not_change_rates_and_accountant_cannot_delete(): void
    {
        $rate = $this->rateRow();

        $this->as($this->auditor)->get(route('accounting.exchange-rates.index'))->assertOk();
        $this->as($this->auditor)->post(route('accounting.exchange-rates.store'), [
            'from_currency' => 'USD', 'to_currency' => 'INR', 'rate' => '83', 'effective_date' => '2026-09-01',
        ])->assertForbidden();
        $this->as($this->auditor)->post(route('accounting.exchange-rates.sync'))->assertForbidden();

        $this->as($this->accountant)->delete(route('accounting.exchange-rates.destroy', $rate))->assertForbidden();
        $this->assertNotNull($rate->fresh());
    }

    /** @test */
    public function another_tenants_rate_cannot_be_edited(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);
        $foreign = $this->rateRow(['tenant_id' => $other->id]);

        $response = $this->as($this->accountant)->put(route('accounting.exchange-rates.update', $foreign), [
            'from_currency' => 'GBP', 'to_currency' => 'INR', 'rate' => '1', 'effective_date' => '2026-09-01',
        ]);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertEqualsWithDelta(105.5, ExchangeRate::withoutGlobalScopes()->find($foreign->id)->rate, 1e-9);
    }

    /** @test */
    public function accountant_can_save_auto_sync_settings(): void
    {
        $this->as($this->accountant)->put(route('accounting.exchange-rates.settings'), [
            'is_enabled' => '1', 'currencies' => ['GBP', 'USD'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $setting = ExchangeRateSyncSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertTrue($setting->is_enabled);
        $this->assertSame(['GBP', 'USD'], $setting->currencies);
    }
}
