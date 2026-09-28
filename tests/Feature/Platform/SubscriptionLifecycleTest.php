<?php

namespace Tests\Feature\Platform;

use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\ModulePrice;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\PlatformSetting;
use App\Domains\Platform\Models\SubscriptionPayment;
use App\Domains\Platform\Models\TenantModule;
use App\Domains\Platform\Models\TenantSubscription;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Recurring per-user subscriptions (billing step 3): subscribe, verify the
 * first payment, and the Razorpay lifecycle webhooks. Verification and
 * webhooks run through the real RazorpayGateway (signatures only — no network);
 * creating the subscription uses a fake gateway.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'test_key_secret';
    private const WEBHOOK_SECRET = 'test_webhook_secret';

    private Plan $starter;
    private Plan $pro;
    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.gst_rate' => 18,
            'billing.grace_days' => 7,
            'services.razorpay.key' => 'rzp_test_key',
            'services.razorpay.secret' => self::KEY_SECRET,
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
        $this->seed(RbacSeeder::class);

        $this->starter = Plan::create([
            'name' => 'Starter', 'slug' => 'starter-sub', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm', 'sales'], 'is_active' => true, 'monthly_price_per_user' => 100, 'yearly_price_per_user' => 80,
        ]);
        $this->pro = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-sub', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm', 'sales', 'purchase'], 'is_active' => true, 'monthly_price_per_user' => 250, 'yearly_price_per_user' => 200,
        ]);
        ModulePrice::query()->where('module', 'inventory')->update(['monthly_price_per_user' => 40, 'yearly_price_per_user' => 35]);

        $this->tenant = Tenant::create([
            'name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'starter-sub', 'plan_id' => $this->starter->id, 'max_users' => 100,
        ]);
        app(TenantContext::class)->set($this->tenant);
        $this->owner = $this->makeUser('owner@acme.test', 'tenant_owner');
        $this->makeUser('two@acme.test', null);
        $this->withHeader('X-Tenant', 'acme');

        $gateway = new FakeSubscriptionGateway();
        app(PaymentGatewayManager::class)->register($gateway);
        PlatformSetting::set('active_payment_gateway', $gateway->identifier());
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

    private function subscribe(array $body = [])
    {
        return $this->actingAs($this->owner)->postJson(route('platform.billing.subscribe'), $body + [
            'plan_id' => $this->pro->id, 'cycle' => 'yearly', 'seats' => 5, 'modules' => ['inventory'],
        ]);
    }

    /** A started subscription, re-pointed at the real Razorpay gateway for signature checks. */
    private function startedSubscription(): TenantSubscription
    {
        $this->subscribe()->assertOk();

        $subscription = TenantSubscription::query()->sole();
        $subscription->update(['gateway' => 'razorpay']);

        return $subscription->fresh();
    }

    private function verify(TenantSubscription $subscription, string $paymentId = 'pay_first', ?string $signature = null)
    {
        $signature ??= hash_hmac('sha256', $paymentId.'|'.$subscription->gateway_subscription_id, self::KEY_SECRET);

        return $this->actingAs($this->owner)->post(route('platform.billing.verify'), [
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_payment_id' => $paymentId,
            'gateway_signature' => $signature,
        ]);
    }

    private function webhook(string $event, TenantSubscription $subscription, array $payment = [], array $entity = [], ?string $secret = null)
    {
        $body = json_encode([
            'event' => 'subscription.'.$event,
            'payload' => array_filter([
                'subscription' => ['entity' => $entity + ['id' => $subscription->gateway_subscription_id]],
                'payment' => $payment === [] ? null : ['entity' => $payment],
            ]),
        ]);

        return $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret ?? self::WEBHOOK_SECRET),
        ], $body);
    }

    public function test_subscribe_creates_a_pending_subscription_priced_on_the_server(): void
    {
        $this->subscribe(['total' => 1, 'amount' => 1])
            ->assertOk()
            ->assertJsonStructure(['gateway', 'subscription_id', 'amount', 'currency', 'tenant_subscription_id']);

        $subscription = TenantSubscription::query()->sole();
        $perSeat = (200 + 35) * 100 * 12;                         // ₹2,820 per user per year
        $this->assertSame(TenantSubscription::STATUS_CREATED, $subscription->status);
        $this->assertSame(5, $subscription->seats);
        $this->assertSame(['inventory'], $subscription->modules);
        $this->assertSame(5 * $perSeat, $subscription->subtotal);
        $this->assertSame(5 * ($perSeat + (int) round($perSeat * 0.18)), $subscription->total);
        $this->assertSame($subscription->total, $subscription->perSeatTotal() * 5, 'gateway charges per-seat x seats');
        $this->assertSame('sub_fake_'.$subscription->id, $subscription->gateway_subscription_id);

        // Nothing changes for the tenant until the first payment is verified.
        $tenant = $this->tenant->fresh();
        $this->assertSame($this->starter->id, $tenant->plan_id);
        $this->assertSame(100, $tenant->max_users);
    }

    public function test_a_gateway_error_is_reported_and_leaves_no_subscription_behind(): void
    {
        FakeSubscriptionGateway::$failWith = 'The requested URL was not found on the server.';

        $this->subscribe()
            ->assertStatus(422)
            ->assertJson(['message' => 'Fake subscriptions could not start the subscription: The requested URL was not found on the server.']);

        $this->assertSame(0, TenantSubscription::query()->count());
        FakeSubscriptionGateway::$failWith = null;
    }

    public function test_subscribe_validates_like_the_quote(): void
    {
        $this->subscribe(['seats' => 1])->assertStatus(422)->assertJsonValidationErrors('seats');
        $this->assertSame(0, TenantSubscription::query()->count());
    }

    public function test_verified_first_payment_activates_the_subscription(): void
    {
        $subscription = $this->startedSubscription();

        $this->verify($subscription)
            ->assertRedirect(route('platform.billing.checkout'))
            ->assertSessionHas('subscribed', fn ($s) => $s['plan'] === 'Pro' && $s['seats'] === 5);

        $subscription->refresh();
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNotNull($subscription->current_end);
        $this->assertTrue($subscription->current_end->isSameDay(now()->addYear()));

        $tenant = $this->tenant->fresh();
        $this->assertSame($this->pro->id, $tenant->plan_id);
        $this->assertSame(5, $tenant->max_users, 'seats bought become the user limit');
        $this->assertSame(Tenant::SUBSCRIPTION_ACTIVE, $tenant->subscription_status);

        $row = TenantModule::query()->where('module', 'inventory')->sole();
        $this->assertSame(TenantModule::BILLING_RECURRING, $row->billing);
        $this->assertTrue($row->isActive());

        $payment = SubscriptionPayment::query()->sole();
        $this->assertSame(SubscriptionPayment::PURPOSE_SUBSCRIPTION, $payment->purpose);
        $this->assertSame($subscription->total, $payment->amount);
        $this->assertSame('pay_first', $payment->gateway_payment_id);
        $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->status);
    }

    public function test_the_confirmation_step_is_shown_after_paying(): void
    {
        $subscription = $this->startedSubscription();

        $this->followingRedirects()->actingAs($this->owner)->post(route('platform.billing.verify'), [
            'gateway_subscription_id' => $subscription->gateway_subscription_id,
            'gateway_payment_id' => 'pay_first',
            'gateway_signature' => hash_hmac('sha256', 'pay_first|'.$subscription->gateway_subscription_id, self::KEY_SECRET),
        ])->assertOk()->assertSee("You're subscribed", false)->assertSee('Pro for 5 users, billed yearly');
    }

    public function test_a_forged_signature_activates_nothing(): void
    {
        $subscription = $this->startedSubscription();

        $this->verify($subscription, 'pay_first', 'forged')->assertSessionHas('error');

        $this->assertSame(TenantSubscription::STATUS_CREATED, $subscription->fresh()->status);
        $this->assertSame($this->starter->id, $this->tenant->fresh()->plan_id);
        $this->assertSame(0, SubscriptionPayment::query()->count());
    }

    public function test_another_tenants_subscription_cannot_be_verified_here(): void
    {
        $subscription = $this->startedSubscription();
        $other = Tenant::create(['name' => 'Globex', 'slug' => 'globex', 'status' => 'active', 'plan' => 'starter-sub']);
        TenantSubscription::query()->whereKey($subscription->id)->update(['tenant_id' => $other->id]);

        $this->verify($subscription)->assertSessionHas('error');

        $this->assertNull($other->fresh()->plan_id);
    }

    public function test_verifying_twice_records_one_payment(): void
    {
        $subscription = $this->startedSubscription();

        $this->verify($subscription);
        $this->verify($subscription);

        $this->assertSame(1, SubscriptionPayment::query()->count());
    }

    public function test_a_tenant_with_a_live_subscription_changes_it_instead_of_starting_another(): void
    {
        $this->liveSubscription();

        $this->subscribe(['seats' => 6])->assertOk()->assertJsonStructure(['order_id', 'amount']);

        $this->assertSame(1, TenantSubscription::query()->count());
    }

    /** An active Pro (5 users, yearly, + Inventory) subscription, half-way through its year. */
    private function liveSubscription(): TenantSubscription
    {
        Carbon::setTestNow(now()->startOfSecond());
        $subscription = $this->startedSubscription();
        $this->verify($subscription);
        Carbon::setTestNow(now()->addDays((int) round(now()->diffInDays(now()->addYear()) / 2)));

        // Back on the fake gateway so changes don't reach the network.
        $subscription->update(['gateway' => 'fakesub']);
        FakeSubscriptionGateway::$calls = [];

        return $subscription->fresh();
    }

    private function quote(array $body = [])
    {
        return $this->actingAs($this->owner)->postJson(route('platform.billing.quote'), $body + [
            'plan_id' => $this->pro->id, 'cycle' => 'yearly', 'seats' => 5, 'modules' => ['inventory'],
        ]);
    }

    private function verifyChange(SubscriptionPayment $payment, string $paymentId = 'pay_upgrade', ?string $signature = null)
    {
        // Signature checked by the real Razorpay gateway.
        $payment->update(['gateway' => 'razorpay']);
        $signature ??= hash_hmac('sha256', $payment->gateway_order_id.'|'.$paymentId, self::KEY_SECRET);

        return $this->actingAs($this->owner)->post(route('platform.billing.change.verify'), [
            'gateway_order_id' => $payment->gateway_order_id,
            'gateway_payment_id' => $paymentId,
            'gateway_signature' => $signature,
        ]);
    }

    public function test_quote_previews_what_a_change_costs(): void
    {
        $subscription = $this->liveSubscription();

        $upgrade = $this->quote(['seats' => 7])->assertOk()->json('change');
        $this->assertSame('now', $upgrade['effective']);
        $this->assertNull($upgrade['error']);
        $expected = app(\App\Domains\Platform\Services\SubscriptionPricing::class)->prorateSubtotals(
            $subscription->subtotal, intdiv($subscription->subtotal, 5) * 7, $subscription->current_start, $subscription->current_end, now(),
        );
        $this->assertSame($expected, $upgrade['due_now']);
        $this->assertGreaterThan(0, $upgrade['due_now']);
        $this->assertLessThan(intdiv($subscription->total, 5) * 2, $upgrade['due_now'], 'only the rest of the period is charged');

        $downgrade = $this->quote(['modules' => []])->json('change');
        $this->assertSame('renewal', $downgrade['effective']);
        $this->assertSame(0, $downgrade['due_now']);

        $this->assertNotNull($this->quote()->json('change.error'), 'same as now');
        $this->assertStringContainsString('monthly and yearly', $this->quote(['cycle' => 'monthly'])->json('change.error'));
        Carbon::setTestNow();
    }

    public function test_an_upgrade_is_paid_pro_rata_then_applied_at_once(): void
    {
        $subscription = $this->liveSubscription();
        $due = $this->quote(['plan_id' => $this->pro->id, 'seats' => 8])->json('change.due_now');

        $this->subscribe(['seats' => 8])->assertOk()->assertJson(['amount' => $due]);

        $payment = SubscriptionPayment::query()->where('purpose', SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE)->sole();
        $this->assertSame($due, $payment->amount);
        $this->assertSame($subscription->id, $payment->tenant_subscription_id);
        $this->assertSame(5, $subscription->fresh()->seats, 'nothing changes before the payment');
        $this->assertSame(5, $this->tenant->fresh()->max_users);

        $this->verifyChange($payment)
            ->assertRedirect(route('platform.billing.checkout'))
            ->assertSessionHas('subscriptionChanged', fn ($m) => str_contains($m, 'Pro for 8 users'));

        $subscription->refresh();
        $this->assertSame(8, $subscription->seats);
        $this->assertSame(intdiv($subscription->total, 8) * 8, $subscription->total);
        $this->assertNull($subscription->pending_change);
        $this->assertSame(8, $this->tenant->fresh()->max_users);
        $this->assertSame(SubscriptionPayment::STATUS_PAID, $payment->fresh()->status);

        // Renewals move to the new amount.
        $this->assertSame([['schedule', $subscription->perSeatTotal(), 8]], FakeSubscriptionGateway::$calls);
        $this->assertSame('plan_fake_'.$subscription->perSeatTotal(), $subscription->gateway_plan_id);
        Carbon::setTestNow();
    }

    public function test_an_upgrade_to_another_plan_switches_the_tenants_plan_and_drops_unbilled_addons(): void
    {
        $this->pro->update(['features' => ['crm', 'sales', 'purchase']]);
        $enterprise = Plan::create([
            'name' => 'Enterprise', 'slug' => 'ent-sub', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm', 'sales', 'purchase', 'inventory'], 'is_active' => true, 'monthly_price_per_user' => 500, 'yearly_price_per_user' => 400,
        ]);
        $subscription = $this->liveSubscription();

        $this->subscribe(['plan_id' => $enterprise->id, 'modules' => []])->assertOk();
        $this->verifyChange(SubscriptionPayment::query()->where('purpose', SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE)->sole());

        $this->assertSame($enterprise->id, $subscription->fresh()->plan_id);
        $this->assertSame($enterprise->id, $this->tenant->fresh()->plan_id);
        $this->assertSame([], $subscription->fresh()->modules);
        $this->assertFalse(TenantModule::query()->where('module', 'inventory')->sole()->isActive(), 'the recurring add-on is no longer billed');
        Carbon::setTestNow();
    }

    public function test_a_forged_upgrade_payment_changes_nothing(): void
    {
        $subscription = $this->liveSubscription();
        $this->subscribe(['seats' => 8])->assertOk();
        $payment = SubscriptionPayment::query()->where('purpose', SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE)->sole();

        $this->verifyChange($payment, 'pay_upgrade', 'forged')->assertSessionHas('error');

        $this->assertSame(5, $subscription->fresh()->seats);
        $this->assertSame(SubscriptionPayment::STATUS_CREATED, $payment->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_a_downgrade_is_booked_for_renewal_and_applied_when_it_is_charged(): void
    {
        $subscription = $this->liveSubscription();

        $this->subscribe(['modules' => []])->assertOk()->assertJson(['scheduled' => true]);

        $subscription->refresh();
        $this->assertSame(['inventory'], $subscription->modules, 'nothing changes until renewal');
        $this->assertSame([], $subscription->scheduledChange()['modules']);
        $newPerSeat = intdiv($subscription->scheduledChange()['total'], 5);
        $this->assertSame([['schedule', $newPerSeat, 5]], FakeSubscriptionGateway::$calls);
        $this->assertSame(0, SubscriptionPayment::query()->where('purpose', SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE)->count());
        $this->assertTrue(TenantModule::query()->where('module', 'inventory')->sole()->isActive());

        $this->actingAs($this->owner)->get(route('platform.billing.checkout'))
            ->assertOk()->assertSee('Subscription updated')->assertSee('takes effect when your subscription renews');
        $this->actingAs($this->owner)->get(route('platform.billing.checkout'))
            ->assertOk()->assertSee('Booked for '.$subscription->current_end->format('d M Y'));

        $this->webhook('charged', $subscription, ['id' => 'pay_renewal', 'amount' => $newPerSeat * 5], [
            'current_start' => $subscription->current_end->getTimestamp(), 'current_end' => $subscription->current_end->addYear()->getTimestamp(),
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame([], $subscription->modules);
        $this->assertSame($newPerSeat * 5, $subscription->total);
        $this->assertNull($subscription->pending_change);
        $this->assertFalse(TenantModule::query()->where('module', 'inventory')->sole()->isActive());
        Carbon::setTestNow();
    }

    public function test_a_new_change_replaces_a_booked_downgrade(): void
    {
        $subscription = $this->liveSubscription();
        $this->subscribe(['modules' => []])->assertOk();

        $this->subscribe(['seats' => 8])->assertOk()->assertJsonStructure(['order_id']);

        $this->assertSame('cancel', FakeSubscriptionGateway::$calls[1][0]);
        $this->assertNull($subscription->fresh()->scheduledChange());
        Carbon::setTestNow();
    }

    public function test_changes_are_refused_while_a_renewal_payment_is_failing(): void
    {
        $subscription = $this->liveSubscription();
        $subscription->update(['status' => TenantSubscription::STATUS_PENDING]);

        $this->subscribe(['seats' => 8])->assertStatus(422)->assertJson(['message' => 'Your last renewal payment failed — settle it before changing your subscription.']);
        Carbon::setTestNow();
    }

    public function test_renewal_webhook_records_the_charge_and_extends_the_period(): void
    {
        $subscription = $this->startedSubscription();
        $this->verify($subscription);
        $end = now()->addYears(2)->startOfSecond();

        $this->webhook('charged', $subscription, ['id' => 'pay_renewal', 'amount' => 123456], [
            'current_start' => now()->addYear()->getTimestamp(), 'current_end' => $end->getTimestamp(),
        ])->assertOk()->assertJson(['status' => 'ok']);

        $renewal = SubscriptionPayment::query()->where('gateway_payment_id', 'pay_renewal')->sole();
        $this->assertSame(123456, $renewal->amount);
        $this->assertSame($subscription->id, $renewal->tenant_subscription_id);
        $this->assertTrue($subscription->fresh()->current_end->equalTo($end));
        $this->assertTrue($this->tenant->fresh()->plan_expires_at->equalTo($end));
    }

    public function test_a_failed_renewal_starts_a_grace_period_that_a_later_charge_clears(): void
    {
        $subscription = $this->startedSubscription();
        $this->verify($subscription);
        Carbon::setTestNow(now()->startOfSecond());

        $this->webhook('pending', $subscription)->assertOk();

        $subscription->refresh();
        $this->assertSame(TenantSubscription::STATUS_PENDING, $subscription->status);
        $this->assertTrue($subscription->grace_ends_at->equalTo(now()->addDays(7)));
        $this->assertSame(Tenant::SUBSCRIPTION_PAST_DUE, $this->tenant->fresh()->subscription_status);

        // Razorpay giving up keeps the original grace deadline.
        Carbon::setTestNow(now()->addDays(2));
        $this->webhook('halted', $subscription);
        $this->assertTrue($subscription->fresh()->grace_ends_at->equalTo(now()->subDays(2)->addDays(7)));

        $this->webhook('charged', $subscription, ['id' => 'pay_retry', 'amount' => $subscription->total]);

        $subscription->refresh();
        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->grace_ends_at);
        $this->assertSame(Tenant::SUBSCRIPTION_ACTIVE, $this->tenant->fresh()->subscription_status);
        Carbon::setTestNow();
    }

    public function test_cancelled_webhook_ends_the_subscription(): void
    {
        $subscription = $this->startedSubscription();
        $this->verify($subscription);

        $this->webhook('cancelled', $subscription)->assertOk();

        $this->assertSame(TenantSubscription::STATUS_CANCELLED, $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
        $this->assertSame(Tenant::SUBSCRIPTION_CANCELLED, $this->tenant->fresh()->subscription_status);
    }

    public function test_activated_webhook_activates_even_if_the_browser_never_came_back(): void
    {
        $subscription = $this->startedSubscription();

        $this->webhook('activated', $subscription)->assertOk();

        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->fresh()->status);
        $this->assertSame($this->pro->id, $this->tenant->fresh()->plan_id);
    }

    public function test_a_webhook_with_a_bad_signature_is_ignored(): void
    {
        $subscription = $this->startedSubscription();
        $this->verify($subscription);

        $this->webhook('cancelled', $subscription, [], [], 'wrong_secret')->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame(TenantSubscription::STATUS_ACTIVE, $subscription->fresh()->status);
    }

    public function test_checkout_defaults_to_the_current_head_count_not_the_user_limit(): void
    {
        $this->actingAs($this->owner)->get(route('platform.billing.checkout'))
            ->assertOk()
            ->assertSee('id="seats" min="2" value="2"', false);
    }

    public function test_subscription_page_shows_the_live_subscription(): void
    {
        $this->verify($this->startedSubscription());

        $this->actingAs($this->owner)->get(route('platform.subscription.index'))
            ->assertOk()
            ->assertSee('5 users')
            ->assertSee('billed yearly')
            ->assertSee('Subscription: Pro');
    }
}

/** Creates subscriptions without the network; ids are predictable. */
class FakeSubscriptionGateway implements PaymentGateway
{
    /** When set, createSubscription() throws like the SDK does on an API error. */
    public static ?string $failWith = null;

    /** Subscription changes sent to the gateway: ['schedule', perSeatTotal, seats] | ['cancel']. */
    public static array $calls = [];

    public function identifier(): string
    {
        return 'fakesub';
    }

    public function label(): string
    {
        return 'Fake subscriptions';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function createCheckout(Tenant $tenant, Plan $plan): array
    {
        throw new \LogicException('Not used.');
    }

    public function createModuleCheckout(Tenant $tenant, array $modules, int $amountInSmallestUnit, string $currency): array
    {
        throw new \LogicException('Not used.');
    }

    public function verifyCheckoutCallback(array $callbackInput, SubscriptionPayment $payment): bool
    {
        return false;
    }

    public function resolveWebhookPayment(Request $request): ?array
    {
        return null;
    }

    public function createSubscription(Tenant $tenant, TenantSubscription $subscription): array
    {
        if (self::$failWith !== null) {
            throw new \Exception(self::$failWith);
        }

        return [
            'gateway_plan_id' => 'plan_fake_'.$subscription->perSeatTotal(),
            'gateway_subscription_id' => 'sub_fake_'.$subscription->id,
            'checkout' => [
                'gateway' => $this->identifier(),
                'subscription_id' => 'sub_fake_'.$subscription->id,
                'amount' => $subscription->total,
                'currency' => $subscription->currency,
            ],
        ];
    }

    public function verifySubscriptionCallback(array $callbackInput, TenantSubscription $subscription): bool
    {
        return false;
    }

    public function resolveSubscriptionWebhook(Request $request): ?array
    {
        return null;
    }

    public function createChangeCheckout(Tenant $tenant, TenantSubscription $subscription, int $amountInSmallestUnit): array
    {
        $payment = SubscriptionPayment::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $subscription->plan_id,
            'tenant_subscription_id' => $subscription->id,
            'purpose' => SubscriptionPayment::PURPOSE_SUBSCRIPTION_CHANGE,
            'gateway' => $this->identifier(),
            'gateway_order_id' => 'order_fake_'.uniqid(),
            'amount' => $amountInSmallestUnit,
            'currency' => $subscription->currency,
            'status' => SubscriptionPayment::STATUS_CREATED,
        ]);

        return ['payment' => $payment, 'checkout' => [
            'gateway' => $this->identifier(), 'order_id' => $payment->gateway_order_id, 'amount' => $payment->amount, 'currency' => $payment->currency,
        ]];
    }

    public function scheduleSubscriptionChange(TenantSubscription $subscription, int $perSeatTotal, int $seats): string
    {
        self::$calls[] = ['schedule', $perSeatTotal, $seats];

        return 'plan_fake_'.$perSeatTotal;
    }

    public function cancelScheduledSubscriptionChange(TenantSubscription $subscription): void
    {
        self::$calls[] = ['cancel'];
    }
}
