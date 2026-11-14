<?php

namespace Tests\Feature\QA\Platform;

use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use Tests\TestCase;

/**
 * Public, CSRF-exempt endpoints. Anyone on the internet can reach these, so
 * each must prove the caller is the real sender before writing tenant data.
 */
class PublicWebhookSecurityTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants();
        Http::fake();
    }

    public function test_whatsapp_webhook_rejects_a_payload_without_the_bridge_token(): void
    {
        $before = WhatsAppMessage::query()->count();

        $this->withHeader('X-Tenant', $this->tenantA->slug)
            ->postJson(route('platform.whatsapp.webhook'), [
                'sender_number' => '919999999999',
                'sender_name' => 'Spoofed Customer',
                'message_body' => 'Please cancel my order',
                'direction' => 'inbound',
            ]);

        $this->assertSame(
            $before,
            WhatsAppMessage::query()->count(),
            'An unauthenticated caller injected a WhatsApp message into tenant A\'s inbox.'
        );
    }

    public function test_whatsapp_webhook_cannot_forge_an_outbound_message(): void
    {
        $this->withHeader('X-Tenant', $this->tenantA->slug)
            ->postJson(route('crm.whatsapp.webhook'), [
                'sender_number' => '919999999999',
                'message_body' => 'We have refunded you in full',
                'direction' => 'outbound',
            ]);

        $this->assertDatabaseMissing('whatsapp_messages', [
            'direction' => 'outbound',
            'message_body' => 'We have refunded you in full',
        ]);
    }

    public function test_meta_webhook_handshake_does_not_echo_the_challenge_for_a_wrong_verify_token(): void
    {
        $response = $this->get(route('webhooks.meta-leadgen', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'definitely-wrong',
            'hub_challenge' => 'CHALLENGE-123',
        ]));

        $this->assertStringNotContainsString('CHALLENGE-123', (string) $response->getContent());
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_meta_webhook_ignores_an_unsigned_lead_payload(): void
    {
        $leadsBefore = \App\Domains\CRM\Models\Lead::withoutGlobalScopes()->count();

        $this->postJson(route('webhooks.meta-leadgen'), [
            'object' => 'page',
            'entry' => [['id' => '123', 'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => '999', 'page_id' => '123']]]]],
        ]);

        $this->assertSame($leadsBefore, \App\Domains\CRM\Models\Lead::withoutGlobalScopes()->count());
    }
}
