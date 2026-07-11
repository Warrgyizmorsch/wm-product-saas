<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterDashboardDetailTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private WorkCenter $workCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'MES Test Tenant',
            'slug' => 'mes-test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'MES Admin',
            'email' => 'mesadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Machining Center',
            'code' => 'WC-TEST-MACH',
            'status' => 'active',
            'efficiency_percentage' => 90.0,
        ]);
    }

    /** @test */
    public function it_displays_fallback_shift_info_when_no_active_shifts_exist()
    {
        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'mes-test-tenant')
            ->get(route('production.mes.work-centers.show', $this->workCenter->id));

        $response->assertStatus(200);
        $response->assertSee('Based on Standard Shift (8 hours)');
        $response->assertSee('Adjusted for 90% efficiency.');
        $response->assertSee('(No active shifts configured; showing fallback)');
    }

    /** @test */
    public function it_displays_assigned_shifts_info_when_active_shifts_exist()
    {
        $shift1 = ProductionShift::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Day Shift',
            'code' => 'DS1',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 30,
            'active' => true,
        ]);

        $shift2 = ProductionShift::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Night Shift',
            'code' => 'NS1',
            'start_time' => '16:00:00',
            'end_time' => '00:00:00',
            'break_minutes' => 45,
            'active' => true,
        ]);

        // Attach shifts to work center
        $this->workCenter->shifts()->attach([
            $shift1->id => ['tenant_id' => $this->tenant->id],
            $shift2->id => ['tenant_id' => $this->tenant->id],
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'mes-test-tenant')
            ->get(route('production.mes.work-centers.show', $this->workCenter->id));

        $response->assertStatus(200);
        $response->assertSee('Based on active shifts:');
        $response->assertSee('Day Shift');
        $response->assertSee('08:00 - 16:00');
        $response->assertSee('break: 30m');
        $response->assertSee('Night Shift');
        $response->assertSee('16:00 - 00:00');
        $response->assertSee('break: 45m');
        $response->assertSee('Adjusted for 90% efficiency.');
        $response->assertDontSee('Based on Standard Shift (8 hours)');
    }

    /** @test */
    public function it_tracks_paused_duration_on_pause_and_resume()
    {
        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Test Product',
            'sku'      => 'TEST-PROD-SKU',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $order = \App\Domains\Production\Models\ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-001',
            'product_id' => $product->id,
            'status' => 'released',
            'quantity_ordered' => 10,
            'start_date' => today(),
            'end_date' => today()->addDays(5),
        ]);

        $schedule = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-001',
            'status' => 'scheduled',
            'start_date' => today(),
            'end_date' => today()->addDays(5),
        ]);

        $orderOp = \App\Domains\Production\Models\ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Assembly',
            'sequence' => 1,
            'status' => 'ready',
            'operation_number' => 'OP-001',
            'setup_time_planned' => 10,
            'processing_time_planned' => 20,
        ]);

        $op = \App\Domains\Production\Models\ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 1,
            'planned_start' => now(),
            'planned_finish' => now()->addHours(1),
            'status' => 'running',
            'actual_start' => now()->subMinutes(10),
        ]);

        $mesService = app(\App\Domains\Production\Services\MesExecutionService::class);

        // 1. Pause the operation
        $this->travelTo(now());
        $mesService->pauseOperation($op->id, 'Coffee Break');

        $op->refresh();
        $this->assertTrue($op->isPaused());
        $this->assertNotNull($op->last_paused_at);
        $this->assertEquals(0, $op->accumulated_paused_seconds);

        // 2. Travel 5 minutes into the future and resume
        $this->travel(5)->minutes();
        $mesService->resumeOperation($op->id);

        $op->refresh();
        $this->assertTrue($op->isRunning());
        $this->assertNull($op->last_paused_at);
        $this->assertEquals(300, $op->accumulated_paused_seconds);

        $this->travelBack();
    }
}
