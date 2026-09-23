<?php

namespace Tests\Feature\Platform;

use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\PlatformSetting;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Domains\Platform\Services\SubscriptionPaymentService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Self-service "install a module": checkout, verify, and what paying unlocks. */
class ModuleAddonPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['navigation.module_addon_price' => 999]);

        $this->seed(RbacSeeder::class);

        $this->plan = Plan::create([
            'name' => 'Addon Test', 'slug' => 'addon-test', 'price' => 0, 'currency' => 'INR',
            'billing_cycle' => 'monthly', 'features' => ['crm', 'sales'], 'is_active' => true,
        ]);

        $this->tenant = $this->makeTenant('acme');
        app(TenantContext::class)->set($this->tenant);
        $this->owner = $this->makeUser($this->tenant, 'owner@acme.test', 'tenant_owner');
        $this->withHeader('X-Tenant', 'acme');

        $gateway = new FakeModuleGateway();
        app(PaymentGatewayManager::class)->register($gateway);
        PlatformSetting::set('active_payment_gateway', $gateway->identifier());
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active',
            'plan' => $this->plan->slug, 'plan_id' => $this->plan->id,
        ]);
    }

    private function makeUser(Tenant $tenant, string $email, ?string $roleSlug): User
    {
        $user = User::create(['tenant_id' => $tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password')]);

        if ($roleSlug !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        return $user;
    }

    private function checkout(array $modules, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)
            ->postJson(route('platform.modules.checkout'), ['modules' => $modules]);
    }

    private function verify(SubscriptionPayment $payment, string $signature = FakeModuleGateway::VALID_SIGNATURE)
    {
        return $this->actingAs($this->owner)->post(route('platform.modules.verify'), [
            'gateway_order_id' => $payment->gateway_order_id,
            'gateway_payment_id' => 'pay_123',
            'gateway_signature' => $signature,
        ]);
    }

    public function test_checkout_charges_only_for_modules_not_yet_installed(): void
    {
        $response = $this->checkout(['crm', 'production', 'hrms'])->assertOk();

        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(SubscriptionPayment::PURPOSE_MODULE_ADDON, $payment->purpose);
        $this->assertSame(['production', 'hrms'], $payment->modules);
        $this->assertSame(2 * 999 * 100, $payment->amount);
        $this->assertSame(SubscriptionPayment::STATUS_CREATED, $payment->status);
        $response->assertJson(['order_id' => $payment->gateway_order_id, 'amount' => 2 * 999 * 100]);
    }

    public function test_checkout_ignores_a_client_supplied_amount(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('platform.modules.checkout'), ['modules' => ['production'], 'amount' => 1])
            ->assertOk();

        $this->assertSame(999 * 100, SubscriptionPayment::query()->sole()->amount);
    }

    public function test_checkout_rejects_modules_that_are_already_installed(): void
    {
        $this->checkout(['crm', 'sales'])->assertStatus(422);

        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_checkout_rejects_unknown_modules(): void
    {
        $this->checkout(['platform'])->assertStatus(422)->assertJsonValidationErrors('modules.0');
        $this->checkout([])->assertStatus(422)->assertJsonValidationErrors('modules');

        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_checkout_needs_the_subscription_permission(): void
    {
        $user = $this->makeUser($this->tenant, 'plain@acme.test', null);

        $this->checkout(['production'], $user)->assertForbidden();

        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_checkout_fails_cleanly_without_an_active_gateway(): void
    {
        PlatformSetting::set('active_payment_gateway', null);

        $this->checkout(['production'])->assertStatus(503);
    }

    public function test_verified_payment_installs_the_modules_and_provisions_their_masters(): void
    {
        $this->checkout(['production'])->assertOk();
        $payment = SubscriptionPayment::query()->sole();

        $this->assertSame(0, DB::table('production_shifts')->where('tenant_id', $this->tenant->id)->count());

        $this->verify($payment)
            ->assertRedirect(route('platform.subscription.index'))
            ->assertSessionHas('success');

        $this->tenant->refresh();
        app(TenantContext::class)->set($this->tenant);

        $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame(['production'], $this->tenant->settings['installed_modules']);
        $this->assertEqualsCanonicalizing(['crm', 'sales', 'production'], tenant_allowed_modules());

        // Starter masters for the new module were filled ...
        $this->assertSame(2, DB::table('production_shifts')->where('tenant_id', $this->tenant->id)->count());

        // ... without touching the Plan other tenants share, or the tenant's plan.
        $this->assertSame(['crm', 'sales'], $this->plan->fresh()->features);
        $this->assertSame($this->plan->id, $this->tenant->plan_id);
    }

    public function test_installed_module_is_reachable_and_other_tenants_on_the_plan_are_not_affected(): void
    {
        $other = $this->makeTenant('globex');

        $this->checkout(['production'])->assertOk();
        $this->verify(SubscriptionPayment::query()->sole());

        $this->assertTrue($this->tenant->fresh()->hasModule('production'));
        $this->assertFalse($other->fresh()->hasModule('production'));
        $this->assertSame(0, DB::table('production_shifts')->where('tenant_id', $other->id)->count());
    }

    public function test_bad_signature_installs_nothing(): void
    {
        $this->checkout(['production'])->assertOk();
        $payment = SubscriptionPayment::query()->sole();

        $this->verify($payment, 'forged')
            ->assertRedirect(route('platform.subscription.index'))
            ->assertSessionHas('error');

        $this->assertSame(SubscriptionPayment::STATUS_CREATED, $payment->fresh()->status);
        $this->assertArrayNotHasKey('installed_modules', $this->tenant->fresh()->settings ?? []);
    }

    public function test_a_plan_switch_order_cannot_be_verified_as_a_module_install(): void
    {
        $payment = SubscriptionPayment::create([
            'tenant_id' => $this->tenant->id, 'plan_id' => $this->plan->id,
            'purpose' => SubscriptionPayment::PURPOSE_PLAN_SWITCH,
            'gateway' => 'fake', 'gateway_order_id' => 'order_plan', 'amount' => 100,
            'currency' => 'INR', 'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        $this->verify($payment)->assertSessionHas('error');

        $this->assertSame(SubscriptionPayment::STATUS_CREATED, $payment->fresh()->status);
    }

    public function test_another_tenants_order_cannot_be_verified(): void
    {
        $other = $this->makeTenant('globex');
        $payment = SubscriptionPayment::create([
            'tenant_id' => $other->id, 'plan_id' => $this->plan->id,
            'purpose' => SubscriptionPayment::PURPOSE_MODULE_ADDON, 'modules' => ['production'],
            'gateway' => 'fake', 'gateway_order_id' => 'order_other', 'amount' => 99900,
            'currency' => 'INR', 'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        $this->verify($payment)->assertSessionHas('error');

        $this->assertSame(SubscriptionPayment::STATUS_CREATED, $payment->fresh()->status);
        $this->assertArrayNotHasKey('installed_modules', $other->fresh()->settings ?? []);
        $this->assertArrayNotHasKey('installed_modules', $this->tenant->fresh()->settings ?? []);
    }

    public function test_marking_paid_twice_is_idempotent(): void
    {
        $this->checkout(['production'])->assertOk();
        $payment = SubscriptionPayment::query()->sole();
        $service = app(SubscriptionPaymentService::class);

        $service->markPaid($payment, 'pay_1', 'sig', $this->tenant);
        $service->markPaid($payment->fresh(), 'pay_2', null);

        $this->assertSame('pay_1', $payment->fresh()->gateway_payment_id);
        $this->assertSame(['production'], $this->tenant->fresh()->settings['installed_modules']);
        $this->assertSame(2, DB::table('production_shifts')->where('tenant_id', $this->tenant->id)->count());
    }
}

/** Stand-in gateway: no network, signature is valid only when it equals VALID_SIGNATURE. */
class FakeModuleGateway implements PaymentGateway
{
    public const VALID_SIGNATURE = 'valid-signature';

    public function identifier(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCheckout(Tenant $tenant, Plan $plan): array
    {
        throw new \LogicException('Not used in these tests.');
    }

    public function createModuleCheckout(Tenant $tenant, array $modules, int $amountInSmallestUnit, string $currency): array
    {
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $tenant->plan_id,
            'purpose' => SubscriptionPayment::PURPOSE_MODULE_ADDON,
            'modules' => $modules,
            'gateway' => $this->identifier(),
            'gateway_order_id' => 'order_'.uniqid(),
            'amount' => $amountInSmallestUnit,
            'currency' => $currency,
            'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        return [
            'payment' => $payment,
            'checkout' => [
                'gateway' => $this->identifier(),
                'order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
        ];
    }

    public function verifyCheckoutCallback(array $callbackInput, SubscriptionPayment $payment): bool
    {
        return ($callbackInput['gateway_signature'] ?? null) === self::VALID_SIGNATURE;
    }

    public function resolveWebhookPayment(Request $request): ?array
    {
        return null;
    }
}
