<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
        $this->actAsTenant($this->tenantA);
    }

    public function test_send_by_user_object_creates_row_with_defaults(): void
    {
        $n = NotificationService::send($this->userA, 'Hello', 'World');

        $this->assertNotNull($n);
        $this->assertSame($this->tenantA->id, $n->tenant_id);
        $this->assertSame($this->userA->id, $n->user_id);
        $this->assertSame('system', $n->module);
        $this->assertSame('general', $n->type);
        $this->assertSame('feather-bell', $n->icon_class);
        $this->assertNull($n->action_url);
        $this->assertNull($n->read_at);
    }

    public function test_send_by_user_id_and_lowercases_module(): void
    {
        $n = NotificationService::send($this->userA->id, 'T', 'M', null, 'HRMS', 'leave', 'feather-x', ['k' => 'v']);

        $this->assertSame('hrms', $n->module);
        $this->assertSame('leave', $n->type);
        $this->assertSame(['k' => 'v'], $n->data);
    }

    public function test_send_returns_null_for_missing_or_zero_user(): void
    {
        $this->assertNull(NotificationService::send(0, 'T', 'M'));
        $this->assertNull(NotificationService::send(999999, 'T', 'M'));
        $this->assertSame(0, Notification::withoutGlobalScopes()->count());
    }

    public function test_send_copies_employee_org_ids(): void
    {
        $emp = $this->makeEmployee($this->userA);

        $n = NotificationService::send($this->userA, 'T', 'M');

        $this->assertSame($emp->id, $n->employee_id);
        $this->assertSame($this->orgIds()['company'], $n->company_id);
        $this->assertNull($n->branch_id);
        $this->assertNull($n->business_unit_id);
    }

    public function test_resolve_url_keeps_paths_and_urls_and_resolves_route_names(): void
    {
        $path = NotificationService::send($this->userA, 'T', 'M', '/hrms/leave/5');
        $url = NotificationService::send($this->userA, 'T', 'M', 'https://example.com/x');
        $route = NotificationService::send($this->userA, 'T', 'M', 'notifications.index');

        $this->assertSame('/hrms/leave/5', $path->action_url);
        $this->assertSame('https://example.com/x', $url->action_url);
        $this->assertSame(route('notifications.index'), $route->action_url);
        $this->assertNull(NotificationService::send($this->userA, 'T', 'M', '')->action_url);
    }

    public function test_send_to_employee_with_user_id_and_by_model_or_id(): void
    {
        $emp = $this->makeEmployee($this->userA);

        $byModel = NotificationService::sendToEmployee($emp, 'T', 'M');
        $byId = NotificationService::sendToEmployee($emp->id, 'T', 'M');
        $byNamed = NotificationService::sendToEmployee(null, 'T', 'M', employeeId: $emp->id);

        foreach ([$byModel, $byId, $byNamed] as $n) {
            $this->assertNotNull($n);
            $this->assertSame($this->userA->id, $n->user_id);
            $this->assertSame('hrms', $n->module);
        }
    }

    public function test_send_to_employee_links_user_by_email_when_user_id_missing(): void
    {
        $emp = $this->makeEmployee($this->userA, ['user_id' => null, 'office_email' => 'user.a@example.com']);

        $n = NotificationService::sendToEmployee($emp, 'T', 'M');

        $this->assertNotNull($n);
        $this->assertSame($this->userA->id, $n->user_id);
        $this->assertSame($this->userA->id, $emp->fresh()->user_id);
    }

    public function test_send_to_employee_returns_null_when_nothing_matches(): void
    {
        $orphan = $this->makeEmployee($this->userA, ['user_id' => null, 'office_email' => 'nobody@example.com']);

        $this->assertNull(NotificationService::sendToEmployee(null, 'T', 'M'));
        $this->assertNull(NotificationService::sendToEmployee(999999, 'T', 'M'));
        $this->assertNull(NotificationService::sendToEmployee($orphan, 'T', 'M'));
    }

    public function test_send_to_user_ids_dedupes_filters_and_skips_unknown(): void
    {
        $result = NotificationService::sendToUserIds(
            [$this->userA->id, $this->userA->id, 0, null, 999999, $this->otherUserA->id],
            'T',
            'M'
        );

        $this->assertCount(2, $result);
        $this->assertSame(1, Notification::where('user_id', $this->userA->id)->count());
    }

    public function test_send_to_roles_matches_role_column(): void
    {
        $hr = $this->makeUser($this->tenantA, 'hr@example.com', 'hr_manager');

        NotificationService::sendToRoles(['hr_manager'], 'T', 'M');

        $this->assertSame(1, Notification::where('user_id', $hr->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->userA->id)->count());
    }

    /**
     * BUG (expected to fail): role matching is LIKE %role%, so "Admin" also notifies
     * super_admin, tenant_admin, etc.
     */
    public function test_send_to_roles_matches_exact_role_not_substring(): void
    {
        $admin = $this->makeUser($this->tenantA, 'admin@example.com', 'admin');
        $superAdmin = $this->makeUser($this->tenantA, 'super@example.com', 'super_admin');
        $assistant = $this->makeUser($this->tenantA, 'assist@example.com', 'hr_admin_assistant');

        NotificationService::sendToRoles(['admin'], 'T', 'M');

        $this->assertSame(1, Notification::where('user_id', $admin->id)->count());
        $this->assertSame(0, Notification::where('user_id', $superAdmin->id)->count(), 'super_admin was notified by an "admin" match.');
        $this->assertSame(0, Notification::where('user_id', $assistant->id)->count(), 'hr_admin_assistant was notified by an "admin" match.');
    }

    /**
     * BUG (expected to fail): when no user has the role, the service falls back to the first
     * five users, notifying people who never held the role.
     */
    public function test_send_to_roles_with_no_matching_role_notifies_nobody(): void
    {
        NotificationService::sendToRoles(['nonexistent_role'], 'T', 'M');

        $this->assertSame(0, Notification::count(), 'Unrelated users were notified by the limit(5) fallback.');
    }

    public function test_send_to_hr_admins_uses_hrms_module(): void
    {
        $hr = $this->makeUser($this->tenantA, 'hr@example.com', 'hr');

        NotificationService::sendToHrAdmins('T', 'M', '/x', 'leave');

        $n = Notification::where('user_id', $hr->id)->first();
        $this->assertNotNull($n);
        $this->assertSame('hrms', $n->module);
        $this->assertSame('leave', $n->type);
    }

    public function test_send_to_all_employees_only_active_with_user(): void
    {
        $this->makeEmployee($this->userA);
        $inactive = $this->makeUser($this->tenantA, 'inactive@example.com');
        $this->makeEmployee($inactive, ['status' => false]);
        $noUser = $this->makeUser($this->tenantA, 'nouser@example.com');
        $this->makeEmployee($noUser, ['user_id' => null, 'office_email' => 'x@none.test']);

        NotificationService::sendToAllEmployees('Broadcast', 'M');

        $this->assertSame(1, Notification::count());
        $this->assertSame('broadcast', Notification::first()->type);
        $this->assertSame($this->userA->id, Notification::first()->user_id);
    }

    /**
     * BUG (expected to fail): a recipient with no tenant is silently written into tenant 1.
     */
    public function test_send_to_tenantless_user_is_not_assigned_to_tenant_one(): void
    {
        $platform = $this->makeUser(null, 'platform@example.com', 'super_admin');
        $this->clearTenant();

        $n = NotificationService::send($platform, 'T', 'M');

        $this->assertNull($n->tenant_id, 'Tenantless user notification was filed under tenant '.$n->tenant_id);
    }
}
