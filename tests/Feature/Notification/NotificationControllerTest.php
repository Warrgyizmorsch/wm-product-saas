<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
    }

    public function test_guest_cannot_use_notification_endpoints(): void
    {
        $n = $this->makeNotification($this->userA);
        $h = ['X-Tenant' => $this->tenantA->slug];

        $this->withHeaders($h)->getJson('/notifications/unread')->assertStatus(401);
        $this->withHeaders($h)->postJson("/notifications/{$n->id}/read")->assertStatus(401);
        $this->withHeaders($h)->postJson('/notifications/read-all')->assertStatus(401);
        $this->withHeaders($h)->deleteJson("/notifications/{$n->id}")->assertStatus(401);
        $this->assertNull(Notification::withoutGlobalScopes()->find($n->id)->read_at);
    }

    public function test_unread_returns_count_and_caps_list_at_six_unread_first(): void
    {
        foreach (range(1, 4) as $i) {
            $this->makeNotification($this->userA, ['title' => "read {$i}", 'read_at' => now()]);
        }
        foreach (range(1, 5) as $i) {
            $this->makeNotification($this->userA, ['title' => "unread {$i}"]);
        }
        $this->makeNotification($this->otherUserA, ['title' => 'not mine']);

        $json = $this->asUser($this->userA, $this->tenantA)->getJson('/notifications/unread')->assertOk()->json();

        $this->assertSame(5, $json['unread_count']);
        $this->assertCount(6, $json['notifications']);
        $this->assertCount(5, array_filter($json['notifications'], fn ($n) => ! $n['is_read']));
        $this->assertEmpty(array_filter($json['notifications'], fn ($n) => $n['title'] === 'not mine'));
        $this->assertFalse($json['notifications'][0]['is_read']);
        $this->assertTrue($json['notifications'][5]['is_read']);
    }

    public function test_unread_defaults_null_action_url_to_hash(): void
    {
        $this->makeNotification($this->userA, ['action_url' => null]);

        $n = $this->asUser($this->userA, $this->tenantA)->getJson('/notifications/unread')->json('notifications.0');

        $this->assertSame('#', $n['action_url']);
    }

    public function test_module_badge_classes(): void
    {
        $expected = [
            'hrms' => 'bg-soft-primary text-primary',
            'purchase' => 'bg-soft-info text-info',
            'production' => 'bg-soft-warning text-warning',
            'sales' => 'bg-soft-success text-success',
            'crm' => 'bg-soft-danger text-danger',
            'inventory' => 'bg-soft-purple text-purple',
            'accounting' => 'bg-soft-dark text-dark',
            'projects' => 'bg-soft-secondary text-secondary',
            'unknown' => 'bg-soft-secondary text-dark',
        ];
        foreach (array_keys($expected) as $module) {
            $this->makeNotification($this->userA, ['module' => $module, 'title' => $module]);
        }

        $items = $this->asUser($this->userA, $this->tenantA)->getJson('/notifications/unread')->json('notifications');

        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame($expected[$item['module']], $item['module_badge_class']);
        }
    }

    public function test_mark_as_read_marks_only_own_notification(): void
    {
        $mine = $this->makeNotification($this->userA);

        $this->asUser($this->userA, $this->tenantA)->postJson("/notifications/{$mine->id}/read")
            ->assertOk()->assertJson(['success' => true]);

        $this->assertNotNull(Notification::withoutGlobalScopes()->find($mine->id)->read_at);
    }

    public function test_cannot_mark_or_delete_another_users_notification(): void
    {
        $theirs = $this->makeNotification($this->otherUserA);

        $this->asUser($this->userA, $this->tenantA)->postJson("/notifications/{$theirs->id}/read")->assertNotFound();
        $this->asUser($this->userA, $this->tenantA)->deleteJson("/notifications/{$theirs->id}")->assertNotFound();

        $fresh = Notification::withoutGlobalScopes()->find($theirs->id);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->read_at);
    }

    public function test_mark_all_read_only_touches_own_unread_rows(): void
    {
        $a1 = $this->makeNotification($this->userA);
        $a2 = $this->makeNotification($this->userA);
        $other = $this->makeNotification($this->otherUserA);

        $this->asUser($this->userA, $this->tenantA)->postJson('/notifications/read-all')->assertOk();

        $this->assertNotNull(Notification::withoutGlobalScopes()->find($a1->id)->read_at);
        $this->assertNotNull(Notification::withoutGlobalScopes()->find($a2->id)->read_at);
        $this->assertNull(Notification::withoutGlobalScopes()->find($other->id)->read_at);
    }

    public function test_mark_all_read_keeps_original_read_at_of_already_read_rows(): void
    {
        $when = now()->subDay()->startOfSecond();
        $old = $this->makeNotification($this->userA, ['read_at' => $when]);

        $this->asUser($this->userA, $this->tenantA)->postJson('/notifications/read-all')->assertOk();

        $this->assertTrue(Notification::withoutGlobalScopes()->find($old->id)->read_at->equalTo($when));
    }

    public function test_destroy_deletes_own_notification(): void
    {
        $mine = $this->makeNotification($this->userA);

        $this->asUser($this->userA, $this->tenantA)->deleteJson("/notifications/{$mine->id}")->assertOk();

        $this->assertNull(Notification::withoutGlobalScopes()->find($mine->id));
    }

    public function test_unknown_id_returns_404(): void
    {
        $this->asUser($this->userA, $this->tenantA)->postJson('/notifications/999999/read')->assertNotFound();
        $this->asUser($this->userA, $this->tenantA)->deleteJson('/notifications/999999')->assertNotFound();
    }

    public function test_index_paginates_and_filters(): void
    {
        foreach (range(1, 25) as $i) {
            $this->makeNotification($this->userA, ['module' => 'hrms', 'title' => "hrms-{$i}"]);
        }
        $this->makeNotification($this->userA, ['module' => 'production', 'title' => 'prod-only', 'read_at' => now()]);
        $this->makeNotification($this->otherUserA, ['title' => 'someone-elses']);

        $page1 = $this->asUser($this->userA, $this->tenantA)->get('/notifications')->assertOk();
        $this->assertCount(20, $page1->viewData('notifications')->items());
        $this->assertSame(26, $page1->viewData('notifications')->total());
        $page1->assertDontSee('someone-elses');

        $prod = $this->asUser($this->userA, $this->tenantA)->get('/notifications?module=production')->assertOk();
        $this->assertSame(1, $prod->viewData('notifications')->total());

        $read = $this->asUser($this->userA, $this->tenantA)->get('/notifications?status=read')->assertOk();
        $this->assertSame(1, $read->viewData('notifications')->total());

        $unread = $this->asUser($this->userA, $this->tenantA)->get('/notifications?status=unread')->assertOk();
        $this->assertSame(25, $unread->viewData('notifications')->total());

        $all = $this->asUser($this->userA, $this->tenantA)->get('/notifications?module=all&status=bogus')->assertOk();
        $this->assertSame(26, $all->viewData('notifications')->total());
    }
}
