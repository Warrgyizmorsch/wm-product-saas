<?php

namespace Tests\Feature\Notification;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
        $this->actAsTenant($this->tenantA);
    }

    public function test_scopes_filter_by_read_state_user_and_module(): void
    {
        $unread = $this->makeNotification($this->userA, ['module' => 'hrms']);
        $read = $this->makeNotification($this->userA, ['module' => 'production', 'read_at' => now()]);
        $this->makeNotification($this->otherUserA, ['module' => 'hrms']);

        $this->assertEquals([$unread->id], Notification::forUser($this->userA->id)->unread()->pluck('id')->all());
        $this->assertEquals([$read->id], Notification::forUser($this->userA->id)->read()->pluck('id')->all());
        $this->assertEquals([$read->id], Notification::forUser($this->userA->id)->forModule('production')->pluck('id')->all());
        $this->assertCount(2, Notification::forUser($this->userA->id)->get());
    }

    public function test_casts_data_to_array_and_read_at_to_datetime(): void
    {
        $n = $this->makeNotification($this->userA, ['data' => ['a' => 1], 'read_at' => now()]);
        $n = Notification::find($n->id);

        $this->assertSame(['a' => 1], $n->data);
        $this->assertInstanceOf(\Carbon\Carbon::class, $n->read_at);
    }

    public function test_creating_fills_tenant_id_from_context(): void
    {
        $n = Notification::create([
            'user_id' => $this->userA->id,
            'module' => 'hrms',
            'type' => 'general',
            'title' => 'T',
            'message' => 'M',
        ]);

        $this->assertSame($this->tenantA->id, $n->tenant_id);
    }

    public function test_global_scope_hides_other_tenant_rows(): void
    {
        $this->makeNotification($this->userA);
        $this->makeNotification($this->userB);

        $this->assertSame(1, Notification::count());
        $this->assertSame(2, Notification::withoutGlobalScopes()->count());
    }

    public function test_without_tenant_context_scope_is_not_applied(): void
    {
        $this->makeNotification($this->userA);
        $this->makeNotification($this->userB);
        $this->clearTenant();

        // Documents current behaviour: no tenant context means no tenant filter.
        $this->assertSame(2, Notification::count());
    }
}
