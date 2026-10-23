<?php

namespace Tests\Feature\QA\Platform;

use App\Domains\Platform\Models\NotificationRule;
use App\Domains\Platform\Models\PaymentTerm;
use App\Domains\Platform\Models\Transporter;
use App\Models\EmailConfiguration;
use App\Models\GstConfiguration;
use App\Models\MetaConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use Tests\TestCase;

/**
 * Tenant B's owner legitimately manages B's settings. None of that may reach
 * tenant A's rows by guessing an id — not reading, updating, toggling,
 * test-firing or deleting. Records are created through the real screens as
 * tenant A's owner so they carry exactly the columns production writes.
 */
class SettingsTenantIsolationTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    private User $ownerA;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants();
        Http::fake();
        Mail::fake();

        $this->ownerA = $this->userWithRole($this->tenantA, 'tenant_owner');
        $this->ownerB = $this->userWithRole($this->tenantB, 'tenant_owner');
    }

    private function asA(): static
    {
        return $this->as($this->ownerA, $this->tenantA);
    }

    private function asB(): static
    {
        return $this->as($this->ownerB, $this->tenantB);
    }

    public function test_payment_terms_of_another_tenant_cannot_be_changed_or_deleted(): void
    {
        $this->asA()->post(route('platform.payment-terms.store'), ['name' => 'A Net 45', 'due_days' => 45, 'is_active' => 1]);
        $term = PaymentTerm::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->get(route('platform.payment-terms.edit', $term->id)), 'edit');
        $this->assertDenied($this->asB()->put(route('platform.payment-terms.update', $term->id), ['name' => 'Hijacked', 'due_days' => 0, 'is_active' => 0]), 'update');
        $this->assertDenied($this->asB()->post(route('platform.payment-terms.toggle-status', $term->id)), 'toggle');
        $this->assertDenied($this->asB()->delete(route('platform.payment-terms.destroy', $term->id)), 'destroy');

        $fresh = PaymentTerm::withoutGlobalScopes()->find($term->id);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->deleted_at, 'Tenant B (soft-)deleted tenant A\'s payment term');
        $this->assertSame('A Net 45', $fresh->name);
        $this->assertTrue((bool) $fresh->is_active);
    }

    public function test_notification_rules_of_another_tenant_cannot_be_changed_fired_or_deleted(): void
    {
        $this->asA()->post(route('platform.notification-rules.store'), [
            'name' => 'A invoice alert', 'event_key' => 'sales.invoice.created', 'module' => 'sales',
            'title_template' => 'New invoice', 'body_template' => 'Body', 'icon_class' => 'feather-bell', 'is_active' => 1,
        ]);
        $rule = NotificationRule::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->get(route('platform.notification-rules.edit', $rule->id)), 'edit');
        $this->assertDenied($this->asB()->put(route('platform.notification-rules.update', $rule->id), [
            'name' => 'Hijacked', 'event_key' => 'x', 'module' => 'sales', 'title_template' => 'x', 'body_template' => 'x', 'icon_class' => 'feather-bell',
        ]), 'update');
        $this->assertDenied($this->asB()->post(route('platform.notification-rules.toggle-status', $rule->id)), 'toggle');
        $this->assertDenied($this->asB()->post(route('platform.notification-rules.test-send', $rule->id)), 'test-send');
        $this->assertDenied($this->asB()->delete(route('platform.notification-rules.destroy', $rule->id)), 'destroy');

        $this->assertSame('A invoice alert', NotificationRule::withoutGlobalScopes()->find($rule->id)?->name);
    }

    public function test_smtp_settings_of_another_tenant_cannot_be_read_tested_overwritten_or_deleted(): void
    {
        $this->asA()->post(route('platform.emailSettings.store'), [
            'name' => 'A SMTP', 'email_address' => 'billing@tenant-a.test', 'host' => 'smtp.a.test',
            'port' => 587, 'encryption' => 'tls', 'username' => 'a-user', 'password' => 'a-secret',
        ]);
        $config = EmailConfiguration::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->postJson(route('platform.emailSettings.test', $config->id)), 'test connection');
        $this->assertDenied($this->asB()->postJson(route('platform.emailSettings.sendTestMail', $config->id), ['to_email' => 'attacker@evil.test', 'subject' => 'probe', 'body_html' => '<p>probe</p>']), 'send test mail');

        // The store endpoint doubles as update when given an id.
        $this->asB()->post(route('platform.emailSettings.store'), [
            'id' => $config->id, 'name' => 'Hijacked', 'email_address' => 'billing@tenant-a.test', 'host' => 'smtp.evil.test',
            'port' => 25, 'encryption' => 'none', 'username' => 'evil', 'password' => 'evil',
        ]);
        // destroy() wraps findOrFail in a try/catch and redirects with an
        // error flash, so judge by the data rather than the status.
        $this->asB()->delete(route('platform.emailSettings.destroy', $config->id));

        $fresh = EmailConfiguration::withoutGlobalScopes()->find($config->id);
        $this->assertNotNull($fresh, 'Tenant B deleted tenant A\'s SMTP account');
        $this->assertSame('smtp.a.test', $fresh->host, 'Tenant B re-pointed tenant A\'s SMTP host');
        $this->assertSame($this->tenantA->id, (int) $fresh->tenant_id);
        Mail::assertNothingSent();
    }

    public function test_meta_and_gst_settings_of_another_tenant_cannot_be_deleted(): void
    {
        $this->asA()->post(route('platform.metaSettings.store'), [
            'name' => 'A Meta', 'access_token' => 'EAA-a', 'default_source' => 'facebook', 'default_priority' => 'high',
        ]);
        $this->asA()->post(route('platform.gstSettings.store'), [
            'provider' => 'sandbox', 'environment' => 'sandbox', 'auth_type' => 'api_key',
            'seller_gstin' => '08AAAAA0000A1Z5', 'legal_name' => 'A Pvt Ltd', 'address_line1' => 'Line 1',
            'location' => 'Udaipur', 'pincode' => '313001', 'state_code' => '08',
        ]);

        $meta = MetaConfiguration::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();
        $gst = GstConfiguration::query()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        // MetaSettingController::destroy catches every exception and redirects
        // with a flash error, so judge by the data rather than the status.
        $this->asB()->delete(route('platform.metaSettings.destroy', $meta->id));
        $this->assertDenied($this->asB()->delete(route('platform.gstSettings.destroy', $gst->id)), 'gst destroy');
        $this->assertDenied($this->asB()->postJson(route('platform.gstSettings.test', $gst->id)), 'gst test');

        $this->assertNotNull(MetaConfiguration::withoutGlobalScopes()->find($meta->id));
        $this->assertNotNull(GstConfiguration::query()->find($gst->id));
    }

    public function test_transporters_of_another_tenant_cannot_be_viewed_changed_or_deleted(): void
    {
        $this->asA()->post(route('platform.transporters.store'), ['name' => 'A Logistics']);
        $transporter = Transporter::withoutGlobalScopes()->where('tenant_id', $this->tenantA->id)->firstOrFail();

        $this->assertDenied($this->asB()->get(route('platform.transporters.show', $transporter->id)), 'show');
        $this->assertDenied($this->asB()->put(route('platform.transporters.update', $transporter->id), ['name' => 'Hijacked']), 'update');
        $this->assertDenied($this->asB()->delete(route('platform.transporters.destroy', $transporter->id)), 'destroy');

        $this->assertSame('A Logistics', Transporter::withoutGlobalScopes()->find($transporter->id)?->name);
    }

    public function test_one_tenants_smtp_address_does_not_block_another_tenant_from_using_it(): void
    {
        $payload = [
            'name' => 'Shared', 'email_address' => 'noreply@shared-domain.test', 'host' => 'smtp.shared.test',
            'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p',
        ];

        $this->asA()->post(route('platform.emailSettings.store'), $payload);
        $this->asB()->post(route('platform.emailSettings.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, EmailConfiguration::withoutGlobalScopes()->where('tenant_id', $this->tenantB->id)->count());
    }
}
