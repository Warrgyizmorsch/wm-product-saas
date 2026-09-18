<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionNotificationService;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $adminA;
    protected User $operatorA;
    protected User $supervisorA;
    protected User $qualityManagerA;
    protected User $maintenanceEngineerA;
    protected User $technicianA;
    protected User $storeManagerA;
    protected User $subcontractManagerA;

    protected User $userB;

    protected WorkCenter $workCenterA;
    protected Machine $machineA;
    protected Product $productA;
    protected Warehouse $warehouseA;
    protected ProductionOrder $orderA;
    protected ProductionOrderOperation $operationA;
    protected ProductionSchedule $scheduleA;
    protected ProductionScheduleOperation $scheduleOpA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admin.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->operatorA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Operator A',
            'email' => 'operator.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'mes_operator',
        ]);

        $this->supervisorA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Supervisor A',
            'email' => 'supervisor.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_supervisor',
        ]);

        $this->qualityManagerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'QC Manager A',
            'email' => 'qc.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'quality_manager',
        ]);

        $this->maintenanceEngineerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Maintenance Eng A',
            'email' => 'maint.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'maintenance_engineer',
        ]);

        $this->technicianA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Technician A',
            'email' => 'tech.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'maintenance_technician',
        ]);

        $this->storeManagerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Store Manager A',
            'email' => 'store.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'store_manager',
        ]);

        $this->subcontractManagerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Subcontract Manager A',
            'email' => 'subcon.a@example.com',
            'password' => bcrypt('password'),
            'role' => 'subcontract_manager',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'User B',
            'email' => 'user.b@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_supervisor',
        ]);

        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC Assembly',
            'code' => 'WC-ASSY',
            'status' => 'active',
        ]);

        $this->machineA = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'CNC Milling 01',
            'code' => 'CNC-01',
            'status' => 'active',
        ]);

        $this->productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Gearbox Housing',
            'sku' => 'GBX-001',
            'unit_of_measure' => 'PCS',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Main Store',
            'code' => 'WH-MAIN',
        ]);

        $this->orderA = ProductionOrder::create([
            'tenant_id' => $this->tenantA->id,
            'order_number' => 'PO-TEST-001',
            'product_id' => $this->productA->id,
            'quantity_ordered' => 50,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'priority' => 'medium',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'created_by' => $this->adminA->id,
        ]);

        $this->operationA = ProductionOrderOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $this->orderA->id,
            'operation_number' => 'OP-10',
            'name' => 'Rough Milling',
            'sequence' => 10,
            'work_center_id' => $this->workCenterA->id,
            'machine_id' => $this->machineA->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'target_produced_qty' => 50,
        ]);

        $this->scheduleA = ProductionSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'schedule_number' => 'SCH-TEST-001',
            'production_order_id' => $this->orderA->id,
            'status' => ProductionSchedule::STATUS_SCHEDULED,
            'planned_start' => now(),
            'planned_end' => now()->addDays(2),
        ]);

        $this->scheduleOpA = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenantA->id,
            'production_schedule_id' => $this->scheduleA->id,
            'production_order_id' => $this->orderA->id,
            'production_order_operation_id' => $this->operationA->id,
            'sequence' => 10,
            'work_center_id' => $this->workCenterA->id,
            'machine_id' => $this->machineA->id,
            'status' => ProductionScheduleOperation::STATUS_RUNNING,
            'planned_start' => now(),
            'planned_finish' => now()->addHours(4),
        ]);
    }

    /**
     * Test 1: Andon Alert Notification and Deduplication.
     */
    public function test_andon_alert_creates_production_notification_and_deduplicates(): void
    {
        $service = app(ProductionNotificationService::class);

        $service->notifyAndonAlert(
            $this->scheduleOpA,
            'Breakdown',
            'critical',
            'Spindle vibration high'
        );

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->supervisorA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_ANDON_ALERT,
            'icon_class' => 'feather-alert-triangle',
            'action_url' => route('production.mes.dashboard'),
        ]);

        $initialCount = Notification::where('type', ProductionNotificationService::TYPE_ANDON_ALERT)->count();
        $this->assertGreaterThanOrEqual(1, $initialCount);

        // Deduplication test: repeated call for same unread andon alert should NOT duplicate
        $service->notifyAndonAlert(
            $this->scheduleOpA,
            'Breakdown',
            'critical',
            'Spindle vibration high'
        );

        $this->assertEquals($initialCount, Notification::where('type', ProductionNotificationService::TYPE_ANDON_ALERT)->count());
    }

    /**
     * Test 2: Operator Assignment Notification & Direct Recipient Isolation.
     */
    public function test_operator_assignment_notifies_operator_and_enforces_tenant_isolation(): void
    {
        $service = app(ProductionNotificationService::class);

        // Tenant A operator assigned
        $service->notifyOperatorAssignment(
            $this->operatorA->id,
            $this->operationA,
            $this->adminA->id
        );

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->operatorA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_OPERATOR_ASSIGNMENT,
            'icon_class' => 'feather-user-check',
        ]);

        $currentCount = Notification::where('type', ProductionNotificationService::TYPE_OPERATOR_ASSIGNMENT)->count();

        // Repeat assignment for same operator & operation should deduplicate
        $service->notifyOperatorAssignment(
            $this->operatorA->id,
            $this->operationA,
            $this->adminA->id
        );
        $this->assertEquals($currentCount, Notification::where('type', ProductionNotificationService::TYPE_OPERATOR_ASSIGNMENT)->count());

        // Cross-tenant attempt: try to notify User B (Tenant B) for Tenant A operation -> must reject
        $service->notifyOperatorAssignment(
            $this->userB->id,
            $this->operationA,
            $this->adminA->id
        );

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->userB->id,
            'type' => ProductionNotificationService::TYPE_OPERATOR_ASSIGNMENT,
        ]);
    }

    /**
     * Test 3: Quality Hold Notification & Deduplication.
     */
    public function test_quality_hold_notifies_quality_team_and_deduplicates(): void
    {
        $service = app(ProductionNotificationService::class);

        $service->notifyQualityHold(
            $this->operationA,
            'Dimension tolerance out of spec by 0.2mm'
        );

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->qualityManagerA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_QUALITY_HOLD,
            'icon_class' => 'feather-alert-triangle',
            'action_url' => \Illuminate\Support\Facades\Route::has('production.quality.inspections.index')
                ? route('production.quality.inspections.index')
                : route('production.inspections.index'),
        ]);

        $count = Notification::where('type', ProductionNotificationService::TYPE_QUALITY_HOLD)->count();

        // Deduplicate second hold call
        $service->notifyQualityHold(
            $this->operationA,
            'Dimension tolerance out of spec by 0.2mm'
        );
        $this->assertEquals($count, Notification::where('type', ProductionNotificationService::TYPE_QUALITY_HOLD)->count());
    }

    /**
     * Test 4: NCR Created Notification — Exactly Once Per Record.
     */
    public function test_ncr_created_notifies_once_per_record(): void
    {
        $service = app(ProductionNotificationService::class);

        $ncr = ProductionNcr::create([
            'tenant_id' => $this->tenantA->id,
            'ncr_number' => 'NCR-TEST-001',
            'category' => 'process',
            'status' => 'open',
            'production_order_id' => $this->orderA->id,
            'production_order_operation_id' => $this->operationA->id,
            'description' => 'Defective surface finish',
        ]);

        $service->notifyNcrCreated($ncr);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->qualityManagerA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_NCR_CREATED,
            'action_url' => \Illuminate\Support\Facades\Route::has('production.quality.ncrs.show')
                ? route('production.quality.ncrs.show', ['ncr' => $ncr->id])
                : route('production.ncrs.show', ['ncr' => $ncr->id]),
        ]);

        $initialCount = Notification::where('type', ProductionNotificationService::TYPE_NCR_CREATED)->count();

        // Calling again on the exact same NCR must NOT create another notification
        $service->notifyNcrCreated($ncr);
        $this->assertEquals($initialCount, Notification::where('type', ProductionNotificationService::TYPE_NCR_CREATED)->count());
    }

    /**
     * Test 5: Machine Breakdown Notification.
     */
    public function test_machine_breakdown_notifies_maintenance_team_and_deduplicates(): void
    {
        $service = app(ProductionNotificationService::class);

        $wo = ProductionMaintenanceWorkOrder::create([
            'tenant_id' => $this->tenantA->id,
            'work_order_number' => 'WO-BD-001',
            'machine_id' => $this->machineA->id,
            'type' => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'priority' => ProductionMaintenanceWorkOrder::PRIORITY_HIGH,
            'status' => ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
        ]);

        $service->notifyMachineBreakdown($this->machineA, 'Hydraulic oil leakage', $wo);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->maintenanceEngineerA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_MACHINE_BREAKDOWN,
            'icon_class' => 'feather-tool',
            'action_url' => route('production.maintenance.dashboard'),
        ]);

        $count = Notification::where('type', ProductionNotificationService::TYPE_MACHINE_BREAKDOWN)->count();

        // Repeat breakdown report for same machine while active
        $service->notifyMachineBreakdown($this->machineA, 'Hydraulic oil leakage', $wo);
        $this->assertEquals($count, Notification::where('type', ProductionNotificationService::TYPE_MACHINE_BREAKDOWN)->count());
    }

    /**
     * Test 6: Maintenance Scheduled Notification.
     */
    public function test_maintenance_scheduled_notifies_assigned_technician(): void
    {
        $service = app(ProductionNotificationService::class);

        $wo = ProductionMaintenanceWorkOrder::create([
            'tenant_id' => $this->tenantA->id,
            'work_order_number' => 'WO-SCH-001',
            'machine_id' => $this->machineA->id,
            'type' => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'priority' => ProductionMaintenanceWorkOrder::PRIORITY_MEDIUM,
            'status' => ProductionMaintenanceWorkOrder::STATUS_SCHEDULED,
            'assigned_technician_id' => $this->technicianA->id,
            'planned_start' => now()->addDay(),
            'planned_end' => now()->addDay()->addHours(2),
        ]);

        $service->notifyMaintenanceScheduled($wo);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->technicianA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_MAINTENANCE_SCHEDULED,
            'icon_class' => 'feather-calendar',
            'action_url' => route('production.maintenance.work-orders.show', ['work_order' => $wo->id]),
        ]);

        $count = Notification::where('type', ProductionNotificationService::TYPE_MAINTENANCE_SCHEDULED)->count();

        // Repeat call should deduplicate
        $service->notifyMaintenanceScheduled($wo);
        $this->assertEquals($count, Notification::where('type', ProductionNotificationService::TYPE_MAINTENANCE_SCHEDULED)->count());
    }

    /**
     * Test 7: Production Schedule Released Notification.
     */
    public function test_schedule_released_notifies_production_supervisors(): void
    {
        $service = app(ProductionNotificationService::class);

        $service->notifyScheduleReleased($this->scheduleA);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->supervisorA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_SCHEDULE_RELEASED,
            'icon_class' => 'feather-layers',
            'action_url' => route('production.schedules.show', ['schedule' => $this->scheduleA->id]),
        ]);

        $count = Notification::where('type', ProductionNotificationService::TYPE_SCHEDULE_RELEASED)->count();

        // Duplicate release call
        $service->notifyScheduleReleased($this->scheduleA);
        $this->assertEquals($count, Notification::where('type', ProductionNotificationService::TYPE_SCHEDULE_RELEASED)->count());
    }

    /**
     * Test 8: Additional Material Requested Notification.
     */
    public function test_additional_material_requested_notifies_store_team(): void
    {
        $service = app(ProductionNotificationService::class);

        $service->notifyAdditionalMaterialRequested(
            $this->orderA,
            15.5,
            'WIP shrinkage on heat treatment',
            $this->supervisorA->id
        );

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->storeManagerA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_MATERIAL_REQUESTED,
            'icon_class' => 'feather-package',
            'action_url' => route('production.orders.show', ['order' => $this->orderA->id]),
        ]);
    }

    /**
     * Test 9: Subcontract Delivery Challan Dispatched Notification.
     */
    public function test_subcontract_challan_dispatched_notifies_subcontract_team(): void
    {
        $service = app(ProductionNotificationService::class);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Heat Treat Works Ltd',
            'code' => 'VEND-HTW-01',
            'status' => 'active',
        ]);

        $challan = DeliveryChallan::create([
            'tenant_id' => $this->tenantA->id,
            'challan_number' => 'DC-2026-0099',
            'challan_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'production_order_id' => $this->orderA->id,
            'production_order_operation_id' => $this->operationA->id,
            'status' => 'dispatched',
        ]);

        $service->notifySubcontractChallanDispatched($challan);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->subcontractManagerA->id,
            'module' => 'production',
            'type' => ProductionNotificationService::TYPE_SUBCONTRACT_DISPATCHED,
            'icon_class' => 'feather-truck',
            'action_url' => route('production.subcontract.delivery-challans.show', ['challan' => $challan->id]),
        ]);

        $count = Notification::where('type', ProductionNotificationService::TYPE_SUBCONTRACT_DISPATCHED)->count();

        // Duplicate dispatch notification prevented
        $service->notifySubcontractChallanDispatched($challan);
        $this->assertEquals($count, Notification::where('type', ProductionNotificationService::TYPE_SUBCONTRACT_DISPATCHED)->count());
    }

    /**
     * Test 10: Strict Multi-Tenant Isolation Assertion.
     *
     * An event occurring in Tenant A must NEVER create a notification record for any user in Tenant B.
     */
    public function test_strict_multi_tenant_isolation_prevents_cross_tenant_leakage(): void
    {
        $service = app(ProductionNotificationService::class);

        // Dispatch all 9 events in Tenant A
        $service->notifyAndonAlert($this->scheduleOpA, 'Material Shortage', 'warning', 'No blanks');
        $service->notifyOperatorAssignment($this->operatorA->id, $this->operationA, $this->adminA->id);
        $service->notifyQualityHold($this->operationA, 'Hold reason');

        $ncr = ProductionNcr::create([
            'tenant_id' => $this->tenantA->id,
            'ncr_number' => 'NCR-ISO-001',
            'category' => 'process',
            'status' => 'open',
            'description' => 'Isolation check',
        ]);
        $service->notifyNcrCreated($ncr);

        $wo = ProductionMaintenanceWorkOrder::create([
            'tenant_id' => $this->tenantA->id,
            'work_order_number' => 'WO-ISO-001',
            'machine_id' => $this->machineA->id,
            'type' => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'priority' => ProductionMaintenanceWorkOrder::PRIORITY_HIGH,
            'status' => ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS,
            'assigned_technician_id' => $this->technicianA->id,
        ]);
        $service->notifyMachineBreakdown($this->machineA, 'Motor burnt', $wo);
        $service->notifyMaintenanceScheduled($wo);
        $service->notifyScheduleReleased($this->scheduleA);
        $service->notifyAdditionalMaterialRequested($this->orderA, 5.0, 'Extra wire', $this->supervisorA->id);

        $vendor = Vendor::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Plating Co',
            'code' => 'VEND-ISO-01',
            'status' => 'active',
        ]);
        $challan = DeliveryChallan::create([
            'tenant_id' => $this->tenantA->id,
            'challan_number' => 'DC-ISO-01',
            'challan_date' => now()->toDateString(),
            'vendor_id' => $vendor->id,
            'status' => 'dispatched',
        ]);
        $service->notifySubcontractChallanDispatched($challan);

        // Assert: Tenant A users have notifications
        $this->assertGreaterThan(0, Notification::where('tenant_id', $this->tenantA->id)->count());

        // Assert: Tenant B user MUST have received ZERO notifications
        $tenantBNotifications = Notification::where('tenant_id', $this->tenantB->id)->count();
        $this->assertEquals(0, $tenantBNotifications, 'Cross-tenant leakage detected! Tenant B received notifications for Tenant A events.');

        $userBNotifications = Notification::where('user_id', $this->userB->id)->count();
        $this->assertEquals(0, $userBNotifications, 'Tenant B user received a notification meant for Tenant A.');
    }

    /**
     * Test 11: Header Notification API & Styling Verification.
     *
     * Verifies that /notifications/unread returns production notifications with:
     * - module: 'production'
     * - module_badge_class: 'bg-soft-warning text-warning'
     */
    public function test_header_unread_api_returns_production_notifications_with_warning_badge(): void
    {
        $service = app(ProductionNotificationService::class);
        $service->notifyScheduleReleased($this->scheduleA);

        $response = $this->actingAs($this->supervisorA)
            ->withHeaders(['X-Tenant' => $this->tenantA->slug])
            ->withSession(['tenant_slug' => $this->tenantA->slug])
            ->getJson('/notifications/unread');

        $response->assertOk();
        $response->assertJsonStructure([
            'unread_count',
            'notifications' => [
                '*' => [
                    'id',
                    'module',
                    'module_label',
                    'module_badge_class',
                    'title',
                    'message',
                    'action_url',
                    'icon_class',
                    'is_read',
                ]
            ]
        ]);

        $data = $response->json();
        $this->assertGreaterThan(0, $data['unread_count']);

        $productionNotif = collect($data['notifications'])->firstWhere('module', 'production');
        $this->assertNotNull($productionNotif, 'Header API did not return any production module notification');
        $this->assertEquals('bg-soft-warning text-warning', $productionNotif['module_badge_class']);
        $this->assertEquals('PRODUCTION', $productionNotif['module_label']);
    }
}
