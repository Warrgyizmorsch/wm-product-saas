<?php

namespace Tests\Feature\QA\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every Platform "settings" screen holds either credentials (SMTP passwords,
 * Meta/Twilio/GSP tokens, WhatsApp bridge token) or tenant-wide behaviour
 * (payment terms, notification rules). None of them should be writable — or,
 * for the credential screens, even readable — by an ordinary staff role.
 *
 * Transporters are included as the control case: that controller does call
 * authorize(), so it should pass while the others show the gap.
 */
class SettingsAuthorizationTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants();
        Http::fake(); // nothing here may reach a real bridge / SMTP / Meta / Twilio
    }

    public static function unprivilegedRoles(): array
    {
        return [
            'read_only' => ['read_only'],
            'sales_executive' => ['sales_executive'],
            'receptionist' => ['receptionist'],
        ];
    }

    /** route name, method, payload, table that must stay untouched */
    public static function settingsWrites(): array
    {
        return [
            'payment term create' => ['platform.payment-terms.store', 'post', [
                'name' => 'Net 30 (QA)', 'due_days' => 30, 'is_active' => 1,
            ], 'payment_terms'],
            'notification rule create' => ['platform.notification-rules.store', 'post', [
                'name' => 'QA rule', 'event_key' => 'sales.invoice.created', 'module' => 'sales',
                'title_template' => 'Hi', 'body_template' => 'Body', 'icon_class' => 'feather-bell',
            ], 'notification_rules'],
            'smtp settings create' => ['platform.emailSettings.store', 'post', [
                'name' => 'QA SMTP', 'email_address' => 'qa-smtp@qa.test', 'host' => 'smtp.qa.test',
                'port' => 587, 'encryption' => 'tls', 'username' => 'qa', 'password' => 'secret',
            ], 'email_configurations'],
            'meta settings create' => ['platform.metaSettings.store', 'post', [
                'name' => 'QA Meta', 'access_token' => 'EAAB-qa', 'default_source' => 'facebook', 'default_priority' => 'high',
            ], 'meta_configurations'],
            'twilio settings create' => ['platform.twilioSettings.store', 'post', [
                'name' => 'QA Twilio', 'account_sid' => 'ACqa', 'auth_token' => 'qa-token', 'phone_number' => '',
                'twiml_app_sid' => '', 'gemini_api_key' => '', 'default_lead_owner_id' => '', 'default_source' => 'twilio',
                'default_priority' => 'medium',
            ], 'twilio_configurations'],
            'gst settings create' => ['platform.gstSettings.store', 'post', [
                'provider' => 'sandbox', 'environment' => 'sandbox', 'auth_type' => 'api_key',
                'seller_gstin' => '08AAAAA0000A1Z5', 'legal_name' => 'QA Pvt Ltd', 'address_line1' => 'Line 1',
                'location' => 'Udaipur', 'pincode' => '313001', 'state_code' => '08',
            ], 'gst_configurations'],
            // Control: TransporterController does authorize(). Sales roles may
            // legitimately create transporters, so only non-sales roles apply.
            'transporter create (control)' => ['platform.transporters.store', 'post', [
                'name' => 'QA Logistics',
            ], 'transporters', ['read_only', 'receptionist']],
        ];
    }

    #[DataProvider('settingsWrites')]
    public function test_a_staff_role_cannot_write_platform_settings(string $route, string $method, array $payload, string $table, ?array $roles = null): void
    {
        foreach ($roles ?? array_keys(self::unprivilegedRoles()) as $roleSlug) {
            $user = $this->userWithRole($this->tenantA, $roleSlug);
            $before = DB::table($table)->count();

            $response = $this->as($user, $this->tenantA)->{$method}(route($route), $payload);

            $this->assertSame(
                $before,
                DB::table($table)->count(),
                "{$roleSlug} created a row in {$table} via {$route} (HTTP {$response->getStatusCode()})."
            );
            $this->assertDenied($response, "{$roleSlug} on {$route}");
        }
    }

    public function test_a_staff_role_cannot_overwrite_the_whatsapp_bridge_credentials(): void
    {
        $user = $this->userWithRole($this->tenantA, 'read_only');

        $response = $this->as($user, $this->tenantA)->postJson(route('platform.whatsapp.updateConfig'), [
            'bridge_url' => 'https://attacker.example',
            'bridge_token' => 'stolen',
        ]);

        $this->assertDatabaseMissing('whatsapp_configurations', ['bridge_url' => 'https://attacker.example']);
        $this->assertDenied($response, 'whatsapp updateConfig');
    }

    public static function credentialScreens(): array
    {
        return [
            'smtp' => ['platform.emailSettings.index'],
            'meta' => ['platform.metaSettings.index'],
            'twilio' => ['platform.twilioSettings.index'],
            'gst' => ['platform.gstSettings.index'],
            'whatsapp' => ['platform.whatsappSettings.index'],
            'payment gateway' => ['platform.payment-gateway.index'],
        ];
    }

    #[DataProvider('credentialScreens')]
    public function test_a_read_only_user_cannot_open_a_credentials_screen(string $route): void
    {
        $user = $this->userWithRole($this->tenantA, 'read_only');

        $this->assertDenied($this->as($user, $this->tenantA)->get(route($route)), $route);
    }

    public function test_a_guest_is_sent_to_login_for_every_settings_screen(): void
    {
        foreach (self::credentialScreens() as [$route]) {
            $this->withHeader('X-Tenant', $this->tenantA->slug)
                ->get(route($route))
                ->assertRedirect(route('login'));
        }
    }
}
