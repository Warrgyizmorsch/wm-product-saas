<?php

namespace Tests\Feature\Production;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\DowntimeService;
use App\Domains\Production\Services\MachineStateService;
use App\Domains\Production\Services\ProductionEventService;
use App\Domains\Production\Services\MesExecutionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OeeFoundationTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Tenant::factory()->create([
            'id' => $this->tenantId,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        $this->actingAs($this->user);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'WC-01',
            'code'      => 'WC01',
            'status'    => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'M-01',
            'code'           => 'M01',
            'status'         => Machine::STATUS_ACTIVE,
            'current_state'  => 'Idle',
        ]);
    }

    /**
     * Test Machine State Override transitions.
     */
    public function test_machine_state_override(): void
    {
        $stateService = app(MachineStateService::class);

        $stateService->transitionState($this->tenantId, $this->machine->id, 'Setup', 'Tool Calibration', $this->user->id);

        $this->machine->refresh();
        $this->assertEquals('Setup', $this->machine->current_state);
        $this->assertEquals('Tool Calibration', $this->machine->current_state_reason);

        // Assert history
        $this->assertDatabaseHas('production_machine_state_histories', [
            'tenant_id'  => $this->tenantId,
            'machine_id' => $this->machine->id,
            'state'      => 'Setup',
            'reason'     => 'Tool Calibration',
            'changed_by' => $this->user->id,
            'ended_at'   => null,
        ]);

        // Transition again to check ending first state
        $stateService->transitionState($this->tenantId, $this->machine->id, 'Running', 'Job Starting', $this->user->id);

        $this->assertDatabaseHas('production_machine_state_histories', [
            'tenant_id'  => $this->tenantId,
            'machine_id' => $this->machine->id,
            'state'      => 'Setup',
            'reason'     => 'Tool Calibration',
        ]);

        $this->assertDatabaseMissing('production_machine_state_histories', [
            'tenant_id'  => $this->tenantId,
            'machine_id' => $this->machine->id,
            'state'      => 'Setup',
            'ended_at'   => null,
        ]);
    }

    /**
     * Test Downtime overlap prevention and end resolution.
     */
    public function test_downtime_overlap_prevention_and_resolution(): void
    {
        $downtimeService = app(DowntimeService::class);

        $downtime = $downtimeService->startDowntime(
            $this->tenantId,
            $this->machine->id,
            'Breakdown',
            'Hydraulic Failure',
            $this->user->id
        );

        $this->assertDatabaseHas('production_machine_downtimes', [
            'id'       => $downtime->id,
            'status'   => ProductionMachineDowntime::STATUS_OPEN,
            'category' => 'Breakdown',
            'reason'   => 'Hydraulic Failure',
        ]);

        // Machine current state should transition to Breakdown
        $this->machine->refresh();
        $this->assertEquals('Breakdown', $this->machine->current_state);
        $this->assertEquals('Hydraulic Failure', $this->machine->current_state_reason);

        // Attempting to open another downtime while active should fail
        $this->expectException(InvalidArgumentException::class);
        $downtimeService->startDowntime(
            $this->tenantId,
            $this->machine->id,
            'Material Shortage',
            'Missing Raw Sheet Metal',
            $this->user->id
        );
    }

    /**
     * Test Downtime close resolution calculates duration.
     */
    public function test_downtime_resolution_duration(): void
    {
        $downtimeService = app(DowntimeService::class);

        $downtime = $downtimeService->startDowntime(
            $this->tenantId,
            $this->machine->id,
            'Breakdown',
            'Hydraulic Failure',
            $this->user->id
        );

        // Resolve
        $resolved = $downtimeService->endDowntime($this->tenantId, $downtime->id, $this->user->id, 'Replaced hydraulic line');

        $this->assertEquals(ProductionMachineDowntime::STATUS_CLOSED, $resolved->status);
        $this->assertNotNull($resolved->duration_minutes);
        $this->assertEquals('Replaced hydraulic line', $resolved->remarks);

        // Machine state should revert back to Idle
        $this->machine->refresh();
        $this->assertEquals('Idle', $this->machine->current_state);
    }

    /**
     * Test Timeline event logger with severity and sources.
     */
    public function test_timeline_event_generation(): void
    {
        $timelineService = app(ProductionEventService::class);

        $event = $timelineService->writeEvent($this->tenantId, [
            'event_type'   => 'Machine Waiting Material',
            'title'        => 'Raw Material Alert',
            'description'  => 'Missing sheet metal on Machine M-01.',
            'severity'     => 'warning',
            'event_source' => 'DowntimeService',
            'machine_id'   => $this->machine->id,
            'metadata'     => ['part_id' => 99],
        ]);

        $this->assertDatabaseHas('production_event_timelines', [
            'id'           => $event->id,
            'tenant_id'    => $this->tenantId,
            'event_type'   => 'Machine Waiting Material',
            'severity'     => 'warning',
            'event_source' => 'DowntimeService',
            'machine_id'   => $this->machine->id,
        ]);

        $this->assertEquals(['part_id' => 99], $event->refresh()->metadata);
    }

    /**
     * Test automation hook transitions during MES shopfloor operations.
     */
    public function test_automated_mes_transitions_and_logging(): void
    {
        $uom = \App\Domains\Inventory\Models\Uom::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Units',
            'code'      => 'PCS',
            'type'      => 'reference'
        ]);

        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'E-Bike Model X',
            'sku'       => 'FG-BIKE-X',
            'type'      => 'finished_good',
            'unit_cost' => 500.00,
            'status'    => 'active',
        ]);

        $bom = \App\Domains\Production\Models\ProductionBom::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $product->id,
            'bom_number'     => 'BOM-BIKE-001',
            'bom_name'       => 'E-Bike Standard BOM',
            'bom_type'       => 'manufacturing',
            'base_quantity'  => 1.0,
            'base_uom_id'    => $uom->id,
            'version'        => '1.0.0',
            'status'         => 'approved',
            'effective_date' => date('Y-m-d'),
        ]);

        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $product->id,
            'routing_number' => 'RT-BIKE-001',
            'name'           => 'E-Bike Routing',
            'version'        => '1.0.0',
            'status'         => 'active',
        ]);

        // 1. Create a dummy Production Order & Schedule & Operation
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'PO-2026-001',
            'product_id'       => $product->id,
            'bom_id'           => $bom->id,
            'routing_id'       => $routing->id,
            'quantity_ordered' => 10,
            'status'           => ProductionOrder::STATUS_RELEASED,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(7)->toDateString(),
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id'               => $this->tenantId,
            'production_order_id'     => $order->id,
            'sequence'                => 10,
            'operation_number'        => 'OP10',
            'name'                    => 'Cutting',
            'work_center_id'          => $this->workCenter->id,
            'machine_id'              => $this->machine->id,
            'status'                  => ProductionOrderOperation::STATUS_READY,
            'setup_time_planned'      => 10,
            'processing_time_planned' => 30,
            'total_time_planned'      => 40,
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id'           => $this->tenantId,
            'schedule_number'     => 'SCH-001',
            'production_order_id' => $order->id,
            'status'              => ProductionSchedule::STATUS_RELEASED,
        ]);

        $schedOp = ProductionScheduleOperation::create([
            'tenant_id'                     => $this->tenantId,
            'production_schedule_id'        => $schedule->id,
            'production_order_id'           => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'work_center_id'                => $this->workCenter->id,
            'machine_id'                    => $this->machine->id,
            'sequence'                      => 10,
            'priority'                      => 1,
            'planned_start'                 => now(),
            'planned_finish'                => now()->addHour(),
            'status'                        => ProductionScheduleOperation::STATUS_READY,
        ]);

        // Start operation -> Machine state should become Running
        $mesService = app(MesExecutionService::class);
        $mesService->startOperation($schedOp->id, $this->machine->id, $this->user->id);

        $this->machine->refresh();
        $this->assertEquals('Running', $this->machine->current_state);

        // Assert Operation Started event published to timeline
        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Operation Started',
            'severity'            => 'info',
            'event_source'        => 'MesExecutionService',
        ]);

        // Pause operation (e.g. for breakdown) -> Machine state should transition based on remarks
        $mesService->pauseOperation($schedOp->id, 'Breakdown: belt slip');

        $this->machine->refresh();
        $this->assertEquals('Breakdown', $this->machine->current_state);

        // Assert paused event is logged
        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Operation Paused',
            'severity'            => 'warning',
            'event_source'        => 'MesExecutionService',
        ]);

        // Resume operation
        $mesService->resumeOperation($schedOp->id);
        $this->machine->refresh();
        $this->assertEquals('Running', $this->machine->current_state);

        // Complete operation -> Machine state should revert back to Idle
        $mesService->completeOperation($schedOp->id, [
            'quantity_produced' => 10,
            'quantity_rejected' => 0,
            'quantity_scrapped' => 0,
            'setup_minutes'     => 10,
            'run_minutes'       => 35,
        ], $this->user->id);

        $this->machine->refresh();
        $this->assertEquals('Idle', $this->machine->current_state);

        // Assert completed event is logged on timeline
        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Operation Completed',
            'severity'            => 'success',
            'event_source'        => 'MesExecutionService',
        ]);
    }
}
