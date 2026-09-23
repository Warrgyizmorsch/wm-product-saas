<?php

namespace Tests\Feature\Platform;

use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Models\TenantModule;
use App\Domains\Platform\Services\TenantModuleService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/** Uninstalling a bought module hides it and keeps its data; reinstalling is free. */
class ModuleUninstallTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private Tenant $tenant;
    private User $owner;
    private TenantModuleService $modules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->plan = Plan::create([
            'name' => 'Uninstall Test', 'slug' => 'uninstall-test', 'price' => 0, 'currency' => 'INR',
            'billing_cycle' => 'monthly', 'features' => ['crm', 'sales'], 'is_active' => true,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Acme', 'slug' => 'acme', 'status' => 'active',
            'plan' => $this->plan->slug, 'plan_id' => $this->plan->id,
        ]);
        app(TenantContext::class)->set($this->tenant);
        $this->owner = $this->makeUser('owner@acme.test', 'tenant_owner');
        $this->withHeader('X-Tenant', 'acme');

        $this->modules = app(TenantModuleService::class);
        $this->modules->install($this->tenant, ['inventory', 'production'], null, $this->owner->id);
    }

    private function makeUser(string $email, ?string $roleSlug): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password')]);

        if ($roleSlug !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        return $user;
    }

    private function uninstall(string $module, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->post(route('platform.modules.uninstall', $module));
    }

    private function reinstall(string $module)
    {
        return $this->actingAs($this->owner)->post(route('platform.modules.reinstall', $module));
    }

    private function shifts(): int
    {
        return DB::table('production_shifts')->where('tenant_id', $this->tenant->id)->count();
    }

    /** Re-reads the tenant the way the next request would. */
    private function allowed(): array
    {
        $tenant = $this->tenant->fresh();
        app(TenantContext::class)->set($tenant);

        return tenant_allowed_modules();
    }

    public function test_uninstall_hides_the_module_but_keeps_its_data(): void
    {
        $this->assertSame(2, $this->shifts());

        $this->uninstall('production')
            ->assertRedirect(route('platform.subscription.index'))
            ->assertSessionHas('success');

        $this->assertNotContains('production', $this->allowed());
        $this->assertSame(2, $this->shifts());

        $row = TenantModule::query()->where('module', 'production')->sole();
        $this->assertNotNull($row->uninstalled_at);
        $this->assertSame($this->owner->id, $row->uninstalled_by);

        $this->actingAs($this->owner)->get(route('production.dashboard'))->assertForbidden();
    }

    public function test_reinstall_is_free_and_brings_the_same_data_back(): void
    {
        $this->uninstall('production');

        $this->reinstall('production')->assertSessionHas('success');

        $this->assertContains('production', $this->allowed());
        $this->assertSame(0, SubscriptionPayment::query()->count());
        $this->assertSame(2, $this->shifts(), 'provisioning must not duplicate masters');
        $this->assertNull(TenantModule::query()->where('module', 'production')->sole()->uninstalled_at);
    }

    public function test_reinstall_keeps_the_original_payment_link(): void
    {
        $payment = SubscriptionPayment::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $this->plan->id,
            'purpose' => SubscriptionPayment::PURPOSE_MODULE_ADDON, 'modules' => ['projects'],
            'gateway' => 'fake', 'gateway_order_id' => 'order_p', 'amount' => 99900,
            'currency' => 'INR', 'status' => SubscriptionPayment::STATUS_PAID,
        ]);
        $this->modules->install($this->tenant, ['projects'], $payment);

        $this->uninstall('projects');
        $this->reinstall('projects');

        $this->assertSame($payment->id, TenantModule::query()->where('module', 'projects')->sole()->subscription_payment_id);
    }

    public function test_a_plan_module_cannot_be_uninstalled(): void
    {
        $this->uninstall('crm')->assertSessionHas('error', 'CRM is included in your plan — change your plan to remove it.');

        $this->assertContains('crm', $this->allowed());
    }

    public function test_a_module_another_installed_module_needs_cannot_be_uninstalled(): void
    {
        $this->uninstall('inventory')->assertSessionHas('error', 'Inventory is needed by Production — uninstall that first.');
        $this->assertContains('inventory', $this->allowed());

        $this->uninstall('production')->assertSessionHas('success');
        $this->uninstall('inventory')->assertSessionHas('success');

        $this->assertEqualsCanonicalizing(['crm', 'sales'], $this->allowed());
    }

    public function test_reinstall_needs_its_requirements_back_first(): void
    {
        $this->uninstall('production');
        $this->uninstall('inventory');

        $this->reinstall('production')->assertSessionHas('error', 'Production needs Inventory — install it too.');
        $this->assertNotContains('production', $this->allowed());

        $this->reinstall('inventory')->assertSessionHas('success');
        $this->reinstall('production')->assertSessionHas('success');
    }

    public function test_a_module_never_bought_cannot_be_reinstalled_for_free(): void
    {
        $this->reinstall('hrms')->assertSessionHas('error');

        $this->assertNotContains('hrms', $this->allowed());
    }

    public function test_uninstalling_twice_or_an_unknown_module_is_rejected(): void
    {
        $this->uninstall('hrms')->assertSessionHas('error', 'HR & Payroll is not installed.');
        $this->uninstall('platform')->assertNotFound();
    }

    public function test_uninstall_needs_the_subscription_permission(): void
    {
        $this->uninstall('production', $this->makeUser('plain@acme.test', null))->assertForbidden();

        $this->assertContains('production', $this->allowed());
    }

    public function test_checkout_refuses_to_charge_again_for_an_uninstalled_module(): void
    {
        $this->uninstall('production');

        $this->actingAs($this->owner)
            ->postJson(route('platform.modules.checkout'), ['modules' => ['production']])
            ->assertStatus(422)
            ->assertJson(['message' => "Production was paid for already — use Reinstall, it's free."]);

        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_checkout_requires_a_modules_dependencies(): void
    {
        $this->uninstall('production');
        $this->uninstall('inventory');
        TenantModule::query()->delete();

        $this->actingAs($this->owner)
            ->postJson(route('platform.modules.checkout'), ['modules' => ['production']])
            ->assertStatus(422)
            ->assertJson(['message' => 'Production needs Inventory — install it too.']);

        // Buying both together is fine.
        $this->modules->assertRequirementsMet($this->tenant->fresh(), ['production', 'inventory']);
        $this->expectException(RuntimeException::class);
        $this->modules->assertRequirementsMet($this->tenant->fresh(), ['production']);
    }

    public function test_subscription_page_shows_uninstall_and_reinstall_actions(): void
    {
        $this->uninstall('production');

        $this->actingAs($this->owner)->get(route('platform.subscription.index'))
            ->assertOk()
            ->assertSee('Included in plan')
            ->assertSee('Installed add-on')
            ->assertSee(route('platform.modules.uninstall', 'inventory'))
            ->assertSee(route('platform.modules.reinstall', 'production'))
            ->assertSee('Reinstall');
    }
}
