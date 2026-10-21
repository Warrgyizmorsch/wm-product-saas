<?php

namespace Tests\Feature\QA;

use App\Models\Access\Role;
use App\Models\WhatsAppConfiguration;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use Tests\TestCase;

/**
 * The QA suite proves the QA-01..11 holes are closed; this proves the
 * legitimate paths through the same code still work after the fixes.
 */
class QaFixesStillWorkTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants();
        Http::fake();
    }

    public function test_the_bridge_with_the_sessions_token_can_deliver_a_message(): void
    {
        $config = WhatsAppConfiguration::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenantA->id, 'session_key' => 'wm-qa-a', 'bridge_url' => 'http://127.0.0.1:3210',
            'bridge_token' => 'qa-session-secret', 'is_active' => true, 'is_default' => true, 'status' => 'connected',
        ]);

        $this->withHeaders(['X-Tenant' => $this->tenantA->slug, 'X-Bridge-Token' => 'qa-session-secret'])
            ->postJson(route('platform.whatsapp.webhook'), [
                'session_key' => $config->session_key, 'sender_number' => '919999999999',
                'message_body' => 'Hello', 'direction' => 'inbound',
            ])->assertOk();

        // (The auto-reply bot may store its own answer alongside it.)
        $this->assertSame(1, WhatsAppMessage::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantA->id)->where('direction', 'inbound')->where('message_body', 'Hello')->count());

        // Right session, wrong token: still refused.
        $this->withHeaders(['X-Tenant' => $this->tenantA->slug, 'X-Bridge-Token' => 'guess'])
            ->postJson(route('platform.whatsapp.webhook'), [
                'session_key' => $config->session_key, 'sender_number' => '919999999999', 'message_body' => 'Hi',
            ])->assertUnauthorized();
    }

    public function test_the_bridge_token_never_appears_in_json(): void
    {
        $config = new WhatsAppConfiguration(['bridge_token' => 'qa-session-secret', 'session_key' => 'k']);

        $this->assertArrayNotHasKey('bridge_token', $config->toArray());
    }

    public function test_a_tenant_owner_still_manages_their_own_settings(): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner');

        foreach (['platform.emailSettings.index', 'platform.gstSettings.index', 'platform.payment-terms.index', 'platform.notification-rules.index'] as $route) {
            $this->as($owner, $this->tenantA)->get(route($route))->assertOk();
        }
    }

    public function test_staff_can_still_check_whatsapp_status_from_crm_screens(): void
    {
        $user = $this->userWithRole($this->tenantA, 'sales_executive');

        $this->assertNotSame(403, $this->as($user, $this->tenantA)->get(route('platform.whatsapp.status'))->getStatusCode());
    }

    public function test_a_tenant_can_still_create_a_role_called_admin_under_a_safe_slug(): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner');

        $this->as($owner, $this->tenantA)->post(route('access.roles.store'), ['name' => 'Admin']);

        $this->assertSame('admin-role', Role::query()->where('tenant_id', $this->tenantA->id)->latest('id')->value('slug'));
    }
}
