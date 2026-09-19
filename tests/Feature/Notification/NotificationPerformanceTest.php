<?php

namespace Tests\Feature\Notification;

use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationPerformanceTest extends TestCase
{
    use RefreshDatabase, NotificationFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareNotificationFixtures();
    }

    /**
     * BUG (expected to fail): Notification::getTable() runs Schema::hasTable on every call, and it is
     * called per hydrated row, so the bell endpoint issues one extra metadata query per returned row.
     */
    public function test_unread_endpoint_query_count_does_not_grow_with_rows(): void
    {
        $count = function (int $rows): int {
            \App\Models\Notification::withoutGlobalScopes()->delete();
            foreach (range(1, $rows) as $i) {
                $this->makeNotification($this->userA, ['title' => "n{$i}"]);
            }
            $this->actingAs($this->userA);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->withHeaders(['X-Tenant' => $this->tenantA->slug])
                ->withSession(['tenant_slug' => $this->tenantA->slug])
                ->getJson('/notifications/unread')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $count(1); $count(1); // warm-up: first requests pay one-off boot/cache queries

        $small = $count(2);
        $large = $count(30);

        $this->assertLessThanOrEqual($small, $large, "Query count grew with row count: {$small} -> {$large}");
    }

    public function test_send_to_all_employees_is_linear_and_bounded_per_employee(): void
    {
        $this->actAsTenant($this->tenantA);
        $perEmployee = [];

        foreach ([3, 9] as $n) {
            \App\Models\Notification::withoutGlobalScopes()->delete();
            \App\Domains\HRMS\Models\Employee::withoutGlobalScopes()->delete();
            foreach (range(1, $n) as $i) {
                $u = $this->makeUser($this->tenantA, "bulk{$n}-{$i}@example.com");
                $this->makeEmployee($u, ['employee_id' => "B{$n}-{$i}"]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            NotificationService::sendToAllEmployees('T', 'M');
            $perEmployee[$n] = count(DB::getQueryLog()) / $n;
            DB::disableQueryLog();
        }

        // Documents the current cost (user lookup + employee lookup + insert per recipient) and
        // fails if it regresses to something worse than that.
        $this->assertLessThanOrEqual(8, $perEmployee[9], 'Queries per recipient: '.$perEmployee[9]);
        $this->assertEqualsWithDelta($perEmployee[3], $perEmployee[9], 1.5, 'Cost per recipient is not linear.');
    }
}
