<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockAdjustmentItem;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Inventory\Models\StockTransferItem;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\InventoryNotificationService;
use App\Domains\Platform\Models\NotificationRule;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAllNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $storeManager;
    protected User $warehouseSupervisor;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Inventory Tenant',
            'slug' => 'inventory-tenant',
        ]);

        $this->storeManager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Manager',
            'email' => 'storemanager@test.com',
            'role' => 'Store Manager',
        ]);

        $this->warehouseSupervisor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Warehouse Supervisor',
            'email' => 'supervisor@test.com',
            'role' => 'Warehouse Supervisor',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-A',
        ]);

        $this->warehouseB = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Secondary Warehouse',
            'code' => 'WH-B',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Premium Brass Valve',
            'sku' => 'VALVE-BR-01',
            'type' => 'finished_good',
            'status' => 'active',
            'reorder_point' => 20.0,
            'cost_price' => 250.00,
        ]);
    }

    public function test_all_10_inventory_events_registered_in_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();
        $this->assertArrayHasKey('inventory', $events);
        $inventoryEvents = $events['inventory']['events'];

        $expectedKeys = [
            'inventory.stock.low',
            'inventory.stock.out_of_stock',
            'inventory.transfer.created',
            'inventory.transfer.dispatched',
            'inventory.transfer.received',
            'inventory.transfer.cancelled',
            'inventory.adjustment.pending',
            'inventory.adjustment.approved',
            'inventory.adjustment.cancelled',
            'inventory.batch.expiring',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $inventoryEvents, "Catalog missing inventory event: {$key}");
            $this->assertNotEmpty($inventoryEvents[$key]['label']);
            $this->assertNotEmpty($inventoryEvents[$key]['default_title']);
            $this->assertNotEmpty($inventoryEvents[$key]['default_body']);
        }
    }

    public function test_stock_low_and_out_of_stock_notifications(): void
    {
        $this->actingAs($this->storeManager);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Low Stock Rule',
            'event_key' => 'inventory.stock.low',
            'module' => 'inventory',
            'recipient_roles' => ['Store Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Low Stock Alert: {{item_name}}',
            'body_template' => 'Current: {{current_stock}}, Min: {{reorder_point}}',
            'action_route' => 'inventory.products.index',
            'is_active' => true,
        ]);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Out of Stock Rule',
            'event_key' => 'inventory.stock.out_of_stock',
            'module' => 'inventory',
            'recipient_roles' => ['Store Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Out of Stock: {{item_name}}',
            'body_template' => 'Zero stock in {{warehouse}}',
            'action_route' => 'inventory.products.index',
            'is_active' => true,
        ]);

        // 1. Trigger low stock alert
        InventoryNotificationService::notifyStockLow(
            product: $this->product,
            currentStock: 15.0,
            reorderPoint: 20.0,
            warehouseName: 'Main Warehouse'
        );

        $lowAlert = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Low Stock%')
            ->first();

        $this->assertNotNull($lowAlert);
        $this->assertStringContainsString('Premium Brass Valve', $lowAlert->title);

        // 2. Trigger Out of Stock alert
        InventoryNotificationService::notifyOutOfStock(
            product: $this->product,
            warehouseName: 'Main Warehouse'
        );

        $outAlert = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Out of Stock%')
            ->first();

        $this->assertNotNull($outAlert);
        $this->assertStringContainsString('Premium Brass Valve', $outAlert->title);
    }

    public function test_stock_transfer_lifecycle_notifications(): void
    {
        $this->actingAs($this->storeManager);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Transfer Created Rule',
            'event_key' => 'inventory.transfer.created',
            'module' => 'inventory',
            'recipient_roles' => ['Warehouse Supervisor'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Transfer Created: {{transfer_no}}',
            'body_template' => 'From {{from_warehouse}} to {{to_warehouse}}',
            'action_route' => 'inventory.transfers.show',
            'is_active' => true,
        ]);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Transfer Dispatched Rule',
            'event_key' => 'inventory.transfer.dispatched',
            'module' => 'inventory',
            'recipient_roles' => ['Warehouse Supervisor'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Transfer Dispatched: {{transfer_no}}',
            'body_template' => 'Dispatched by {{dispatched_by}}',
            'action_route' => 'inventory.transfers.show',
            'is_active' => true,
        ]);

        $transfer = StockTransfer::create([
            'tenant_id' => $this->tenant->id,
            'transfer_number' => 'TRF-TEST-001',
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
            'created_by' => $this->storeManager->id,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'received_quantity' => 0,
        ]);

        // 1. Transfer Created
        InventoryNotificationService::notifyTransferCreated($transfer);
        $createdNotif = Notification::where('user_id', $this->warehouseSupervisor->id)
            ->where('title', 'like', '%Transfer Created%')
            ->first();
        $this->assertNotNull($createdNotif);

        // 2. Transfer Dispatched (dispatched by Supervisor -> direct alert to creator Store Manager)
        $this->actingAs($this->warehouseSupervisor);
        InventoryNotificationService::notifyTransferDispatched($transfer);

        $dispatchedDirect = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Transfer Dispatched%')
            ->first();
        $this->assertNotNull($dispatchedDirect);

        // 3. Transfer Received (received by Supervisor -> direct alert to creator Store Manager)
        InventoryNotificationService::notifyTransferReceived($transfer);
        $receivedDirect = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Transfer Received%')
            ->first();
        $this->assertNotNull($receivedDirect);

        // 4. Transfer Cancelled
        InventoryNotificationService::notifyTransferCancelled($transfer, 'Cancelled due to truck delay');
        $cancelledDirect = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Transfer Cancelled%')
            ->first();
        $this->assertNotNull($cancelledDirect);
    }

    public function test_stock_adjustment_lifecycle_notifications(): void
    {
        $this->actingAs($this->warehouseSupervisor);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Adjustment Pending Rule',
            'event_key' => 'inventory.adjustment.pending',
            'module' => 'inventory',
            'recipient_roles' => ['Store Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Approval Needed: {{doc_no}}',
            'body_template' => 'Reason: {{reason}} by {{created_by}}',
            'action_route' => 'inventory.adjustments.show',
            'is_active' => true,
        ]);

        $adjustment = StockAdjustment::create([
            'tenant_id' => $this->tenant->id,
            'adjustment_number' => 'ADJ-TEST-001',
            'warehouse_id' => $this->warehouseA->id,
            'adjustment_date' => now()->toDateString(),
            'reason' => 'Stock Count Variance',
            'status' => 'Draft',
            'created_by' => $this->warehouseSupervisor->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'type' => 'Addition',
            'quantity' => 5,
            'unit_cost' => 250.00,
            'total_amount' => 1250.00,
        ]);

        // 1. Adjustment Pending -> Store Manager alerted
        InventoryNotificationService::notifyAdjustmentPending($adjustment);

        $pendingNotif = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Approval Needed%')
            ->first();
        $this->assertNotNull($pendingNotif);

        // 2. Adjustment Approved by Store Manager -> Supervisor (creator) alerted
        $this->actingAs($this->storeManager);
        InventoryNotificationService::notifyAdjustmentApproved($adjustment);

        $approvedNotif = Notification::where('user_id', $this->warehouseSupervisor->id)
            ->where('title', 'like', '%Adjustment Approved%')
            ->first();
        $this->assertNotNull($approvedNotif);

        // 3. Adjustment Cancelled -> Supervisor (creator) alerted
        InventoryNotificationService::notifyAdjustmentCancelled($adjustment, 'Damaged count rejected');

        $cancelledNotif = Notification::where('user_id', $this->warehouseSupervisor->id)
            ->where('title', 'like', '%Adjustment Cancelled%')
            ->first();
        $this->assertNotNull($cancelledNotif);
    }

    public function test_batch_expiring_notification_and_command(): void
    {
        $batch = Batch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouseA->id,
            'batch_number' => 'BATCH-2026-X1',
            'quantity' => 100,
            'available_qty' => 80,
            'manufacturing_date' => Carbon::now()->subMonths(3),
            'expiry_date' => Carbon::now()->addDays(15),
        ]);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Batch Expiring Rule',
            'event_key' => 'inventory.batch.expiring',
            'module' => 'inventory',
            'recipient_roles' => ['Store Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Batch Expiring Soon: {{item_name}} ({{batch_no}})',
            'body_template' => 'Expires in {{days_left}} days on {{expiry_date}}',
            'action_route' => 'inventory.batches.index',
            'is_active' => true,
        ]);

        // Execute console command
        $this->artisan('inventory:check-expiring-batches', [
            '--days' => 30,
            '--tenant' => $this->tenant->id,
        ])->assertExitCode(0);

        $expiryNotif = Notification::where('user_id', $this->storeManager->id)
            ->where('title', 'like', '%Batch Expiring Soon%')
            ->first();

        $this->assertNotNull($expiryNotif);
        $this->assertStringContainsString('BATCH-2026-X1', $expiryNotif->title);
    }
}

