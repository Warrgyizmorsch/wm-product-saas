<?php

namespace Tests\Feature\Notification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSecurityTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
    }

    public function test_index_page_escapes_title_and_message(): void
    {
        $this->makeNotification($this->userA, [
            'title' => '<script>alert(1)</script>',
            'message' => '<img src=x onerror=alert(2)>',
        ]);

        $html = $this->asUser($this->userA, $this->tenantA)->get('/notifications')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(2)>', $html);
    }

    /**
     * BUG (expected to fail): action_url is rendered into href without a scheme check.
     */
    public function test_index_page_does_not_render_javascript_urls(): void
    {
        $this->makeNotification($this->userA, ['action_url' => 'javascript:alert(1)']);

        $html = $this->asUser($this->userA, $this->tenantA)->get('/notifications')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    /**
     * The unread endpoint returns raw strings, so the bell script must escape them.
     * Guard the template: no unescaped interpolation of n.title / n.message into HTML.
     */
    public function test_bell_template_escapes_dynamic_fields(): void
    {
        $tpl = file_get_contents(resource_path('views/partials/topbar-notifications.blade.php'));

        $this->assertDoesNotMatchRegularExpression(
            '/\$\{\s*n\.(title|message|action_url)\s*\}/',
            $tpl,
            'topbar-notifications.blade.php interpolates notification text into HTML without escaping.'
        );
    }

    public function test_unread_json_returns_raw_text_for_client_side_escaping(): void
    {
        $this->makeNotification($this->userA, ['title' => '<b>x</b>']);

        $json = $this->asUser($this->userA, $this->tenantA)->getJson('/notifications/unread')->json();

        $this->assertSame('<b>x</b>', $json['notifications'][0]['title']);
    }
}
