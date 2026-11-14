<?php

namespace Tests\Feature\Production;

use App\Domains\Production\DTO\MachineDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Repositories\MachineRepository;
use App\Domains\Production\Services\MachineService;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class MachineSectionOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private WorkCenter $workCenterA1;
    private WorkCenter $workCenterA2;
    private WorkCenter $workCenterB;
    private WorkCenterSection $sectionA1;
    private WorkCenterSection $sectionA2;
    private WorkCenterSection $sectionB;
    private MachineService $machineService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name'   => 'Tenant A',
            'slug'   => 'tenant-a',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'User A',
            'email'     => 'usera@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->tenantB = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->workCenterA1 = WorkCenter::create([
            'tenant_id'             => $this->tenantA->id,
            'name'                  => 'WC Alpha',
            'code'                  => 'WC-A1',
            'capacity_per_hour'     => 10,
            'efficiency_percentage' => 100,
            'status'                => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->workCenterA2 = WorkCenter::create([
            'tenant_id'             => $this->tenantA->id,
            'name'                  => 'WC Beta',
            'code'                  => 'WC-A2',
            'capacity_per_hour'     => 10,
            'efficiency_percentage' => 100,
            'status'                => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->workCenterB = WorkCenter::create([
            'tenant_id'             => $this->tenantB->id,
            'name'                  => 'WC Tenant B',
            'code'                  => 'WC-B1',
            'capacity_per_hour'     => 10,
            'efficiency_percentage' => 100,
            'status'                => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->sectionA1 = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'Section Alpha 1',
            'code'           => 'SEC-A1',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $this->sectionA2 = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA2->id,
            'name'           => 'Section Beta 1',
            'code'           => 'SEC-A2',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $this->sectionB = WorkCenterSection::create([
            'tenant_id'      => $this->tenantB->id,
            'work_center_id' => $this->workCenterB->id,
            'name'           => 'Section Tenant B',
            'code'           => 'SEC-B1',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $this->machineService = new MachineService(new MachineRepository());
    }

    public function test_production_machines_table_does_not_have_work_center_id_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('production_machines', 'work_center_id'),
            'production_machines table must NOT have a work_center_id column.'
        );
        $this->assertTrue(
            Schema::hasColumn('production_machines', 'section_id'),
            'production_machines table must have a section_id column.'
        );
    }

    public function test_machine_with_section_derives_work_center(): void
    {
        $machine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'CNC Lathe 1',
            'code'       => 'M-CNC-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $this->assertNotNull($machine->section);
        $this->assertEquals($this->sectionA1->id, $machine->section->id);

        // Derived Work Center via HasOneThrough relationship
        $this->assertNotNull($machine->workCenter);
        $this->assertEquals($this->workCenterA1->id, $machine->workCenter->id);
        $this->assertEquals('WC-A1', $machine->workCenter->code);
    }

    public function test_machine_without_section_has_null_work_center(): void
    {
        $machine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => null,
            'name'       => 'Floating Drill',
            'code'       => 'M-DRILL-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $this->assertNull($machine->section);
        $this->assertNull($machine->workCenter);
    }

    public function test_machine_section_reassignment_updates_derived_work_center(): void
    {
        $machine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Milling Machine',
            'code'       => 'M-MILL-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $this->assertEquals($this->workCenterA1->id, $machine->workCenter->id);

        // Reassign to Section A2 (which belongs to Work Center A2)
        $machine->update(['section_id' => $this->sectionA2->id]);
        $machine = $machine->fresh();

        $this->assertEquals($this->sectionA2->id, $machine->section_id);
        $this->assertEquals($this->workCenterA2->id, $machine->workCenter->id);
    }

    public function test_clearing_machine_section_removes_derived_work_center(): void
    {
        $machine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Press Machine',
            'code'       => 'M-PRESS-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $this->assertNotNull($machine->workCenter);

        // Clear section assignment
        $machine->update(['section_id' => null]);
        $machine = $machine->fresh();

        $this->assertNull($machine->section_id);
        $this->assertNull($machine->workCenter);
    }

    public function test_work_center_machines_relation_includes_only_section_assigned_machines(): void
    {
        // Assigned to Section A1 (under WC A1)
        $m1 = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Machine 1',
            'code'       => 'M-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        // Assigned to Section A2 (under WC A2)
        $m2 = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA2->id,
            'name'       => 'Machine 2',
            'code'       => 'M-2',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        // Unassigned machine in Tenant A
        $mUnassigned = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => null,
            'name'       => 'Machine Unassigned',
            'code'       => 'M-UN',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $wc1Machines = $this->workCenterA1->machines()->pluck('production_machines.id')->toArray();
        $this->assertContains($m1->id, $wc1Machines);
        $this->assertNotContains($m2->id, $wc1Machines);
        $this->assertNotContains($mUnassigned->id, $wc1Machines);

        $wc2Machines = $this->workCenterA2->machines()->pluck('production_machines.id')->toArray();
        $this->assertContains($m2->id, $wc2Machines);
        $this->assertNotContains($m1->id, $wc2Machines);
        $this->assertNotContains($mUnassigned->id, $wc2Machines);
    }

    public function test_work_center_active_machines_filters_status_without_ambiguity(): void
    {
        $activeMachine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Active Machine',
            'code'       => 'M-ACT',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $inactiveMachine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Inactive Machine',
            'code'       => 'M-INACT',
            'status'     => Machine::STATUS_INACTIVE,
        ]);

        $activeIds = $this->workCenterA1->activeMachines()->pluck('production_machines.id')->toArray();

        $this->assertContains($activeMachine->id, $activeIds);
        $this->assertNotContains($inactiveMachine->id, $activeIds);
    }

    public function test_cross_tenant_section_assignment_is_rejected_by_service(): void
    {
        $dto = MachineDTO::fromArray([
            'name'       => 'Cross Tenant Machine',
            'code'       => 'M-CROSS',
            'section_id' => $this->sectionB->id, // Belongs to Tenant B
            'status'     => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid section selected.');

        // Attempt creation under Tenant A with Tenant B's section
        $this->machineService->create($dto, $this->tenantA->id);
    }

    public function test_machine_service_creates_and_updates_machine_without_work_center_id(): void
    {
        $dto = MachineDTO::fromArray([
            'name'       => 'Service Machine',
            'code'       => 'M-SVC',
            'section_id' => $this->sectionA1->id,
            'status'     => 'active',
        ]);

        $machine = $this->machineService->create($dto, $this->tenantA->id);
        $this->assertDatabaseHas('production_machines', [
            'id'         => $machine->id,
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'code'       => 'M-SVC',
        ]);

        // Update to clear section
        $updateDto = MachineDTO::fromArray([
            'name'       => 'Service Machine Updated',
            'code'       => 'M-SVC',
            'section_id' => null,
            'status'     => 'active',
        ]);

        $updated = $this->machineService->update($machine->id, $updateDto);
        $this->assertNull($updated->section_id);
        $this->assertNull($updated->workCenter);
    }

    public function test_scheduling_service_validates_against_section_derived_work_center(): void
    {
        $schedulingService = app(SchedulingService::class);

        $assignedMachine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Assigned Machine',
            'code'       => 'M-SCHED-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $unassignedMachine = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => null,
            'name'       => 'Unassigned Machine',
            'code'       => 'M-SCHED-2',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        // Valid: machine belongs to Section A1 which belongs to Work Center A1
        $resValid = $schedulingService->validateMachineForScheduling($assignedMachine, $this->workCenterA1->id);
        $this->assertTrue($resValid['valid']);

        // Invalid: machine belongs to Section A1 (under WC A1), tested against WC A2
        $resWrongWc = $schedulingService->validateMachineForScheduling($assignedMachine, $this->workCenterA2->id);
        $this->assertFalse($resWrongWc['valid']);

        // Invalid: unassigned machine has no operational Work Center
        $resUnassigned = $schedulingService->validateMachineForScheduling($unassignedMachine, $this->workCenterA1->id);
        $this->assertFalse($resUnassigned['valid']);
    }

    public function test_maintenance_work_order_logs_downtime_with_derived_or_null_work_center(): void
    {
        $mwoService = app(MaintenanceWorkOrderService::class);

        // Case 1: Machine with Section derives work center on downtime creation
        $mAssigned = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => $this->sectionA1->id,
            'name'       => 'Maintained Machine',
            'code'       => 'M-MAINT-1',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $woAssigned = ProductionMaintenanceWorkOrder::create([
            'tenant_id'          => $this->tenantA->id,
            'work_order_number'  => 'MWO-TEST-001',
            'machine_id'         => $mAssigned->id,
            'type'               => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'priority'           => ProductionMaintenanceWorkOrder::PRIORITY_MEDIUM,
            'problem_description' => 'Routine service',
            'created_by'          => $this->userA->id,
        ]);

        $mwoService->scheduleWorkOrder(
            $woAssigned->id,
            $this->tenantA->id,
            now()->addDay()->toDateTimeString(),
            now()->addDay()->addHours(2)->toDateTimeString(),
            null,
            $this->userA->id
        );

        $this->assertDatabaseHas('production_machine_downtimes', [
            'machine_id'     => $mAssigned->id,
            'work_center_id' => $this->workCenterA1->id,
        ]);

        // Case 2: Machine without Section logs downtime with null work center cleanly
        $mUnassigned = Machine::create([
            'tenant_id'  => $this->tenantA->id,
            'section_id' => null,
            'name'       => 'Unassigned Maintained Machine',
            'code'       => 'M-MAINT-2',
            'status'     => Machine::STATUS_ACTIVE,
        ]);

        $woUnassigned = ProductionMaintenanceWorkOrder::create([
            'tenant_id'          => $this->tenantA->id,
            'work_order_number'  => 'MWO-TEST-002',
            'machine_id'         => $mUnassigned->id,
            'type'               => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'priority'           => ProductionMaintenanceWorkOrder::PRIORITY_HIGH,
            'status'             => ProductionMaintenanceWorkOrder::STATUS_DRAFT,
            'problem_description' => 'Emergency inspection',
            'created_by'         => $this->userA->id,
        ]);

        $mwoService->scheduleWorkOrder(
            $woUnassigned->id,
            $this->tenantA->id,
            now()->addDay()->toDateTimeString(),
            now()->addDay()->addHours(2)->toDateTimeString(),
            null,
            $this->userA->id
        );

        $this->assertDatabaseHas('production_machine_downtimes', [
            'machine_id'     => $mUnassigned->id,
            'work_center_id' => null,
        ]);
    }
}
