<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTenantIsolationTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
    }

    public function test_user_never_sees_other_tenant_rows_through_http(): void
    {
        $this->makeNotification($this->userA, ['title' => 'A-note']);
        $this->makeNotification($this->userB, ['title' => 'B-note']);

        $json = $this->asUser($this->userA, $this->tenantA)->getJson('/notifications/unread')->json();

        $this->assertSame(1, $json['unread_count']);
        $this->assertSame(['A-note'], array_column($json['notifications'], 'title'));
    }

    public function test_user_cannot_touch_other_tenant_row_by_id(): void
    {
        $b = $this->makeNotification($this->userB);

        $this->asUser($this->userA, $this->tenantA)->postJson("/notifications/{$b->id}/read")->assertNotFound();
        $this->asUser($this->userA, $this->tenantA)->deleteJson("/notifications/{$b->id}")->assertNotFound();

        $this->assertNotNull(Notification::withoutGlobalScopes()->find($b->id));
        $this->assertNull(Notification::withoutGlobalScopes()->find($b->id)->read_at);
    }

    public function test_mark_all_read_does_not_touch_other_tenant(): void
    {
        $b = $this->makeNotification($this->userB);
        $this->makeNotification($this->userA);

        $this->asUser($this->userA, $this->tenantA)->postJson('/notifications/read-all')->assertOk();

        $this->assertNull(Notification::withoutGlobalScopes()->find($b->id)->read_at);
    }

    public function test_send_to_other_tenant_user_object_is_stored_under_recipient_tenant(): void
    {
        $this->actAsTenant($this->tenantA);

        $n = NotificationService::send($this->userB, 'Hi', 'msg');

        $this->assertSame($this->tenantB->id, $n->tenant_id);
    }

    public function test_send_by_id_to_other_tenant_user_is_dropped_in_tenant_context(): void
    {
        $this->actAsTenant($this->tenantA);

        $this->assertNull(NotificationService::send($this->userB->id, 'Hi', 'msg'));
        $this->assertSame(0, Notification::withoutGlobalScopes()->where('user_id', $this->userB->id)->count());
    }

    public function test_send_to_roles_stays_inside_tenant_context(): void
    {
        $adminA = $this->makeUser($this->tenantA, 'admin.a@example.com', 'hr_manager');
        $adminB = $this->makeUser($this->tenantB, 'admin.b@example.com', 'hr_manager');
        $this->actAsTenant($this->tenantA);

        NotificationService::sendToRoles(['hr_manager'], 'T', 'M');

        $this->assertSame(1, Notification::withoutGlobalScopes()->where('user_id', $adminA->id)->count());
        $this->assertSame(0, Notification::withoutGlobalScopes()->where('user_id', $adminB->id)->count());
    }

    /**
     * BUG (expected to fail): with no tenant context (queue/CLI/seeder) the role query is not
     * tenant-filtered, so a role notification reaches every tenant.
     */
    public function test_send_to_roles_without_tenant_context_does_not_leak_across_tenants(): void
    {
        $adminA = $this->makeUser($this->tenantA, 'admin.a@example.com', 'hr_manager');
        $adminB = $this->makeUser($this->tenantB, 'admin.b@example.com', 'hr_manager');
        $this->clearTenant();

        NotificationService::sendToRoles(['hr_manager'], 'T', 'M');

        $reached = Notification::withoutGlobalScopes()
            ->whereIn('user_id', [$adminA->id, $adminB->id])->pluck('tenant_id')->unique();

        $this->assertLessThanOrEqual(1, $reached->count(), 'Role notification reached more than one tenant.');
    }

    public function test_send_to_all_employees_stays_inside_tenant_context(): void
    {
        $this->makeEmployee($this->userA);
        $this->makeEmployee($this->userB);
        $this->actAsTenant($this->tenantA);

        NotificationService::sendToAllEmployees('Broadcast', 'msg');

        $this->assertSame(1, Notification::withoutGlobalScopes()->where('user_id', $this->userA->id)->count());
        $this->assertSame(0, Notification::withoutGlobalScopes()->where('user_id', $this->userB->id)->count());
    }
}
