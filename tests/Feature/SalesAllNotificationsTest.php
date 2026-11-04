<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Sales\Services\SalesNotificationService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesAllNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $manager;
    protected User $salesperson;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Sales Test Tenant',
            'slug' => 'sales-test-tenant',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Manager',
            'email' => 'salesmanager@test.com',
            'role' => 'Sales Manager',
        ]);

        $this->salesperson = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Rep One',
            'email' => 'salesrep@test.com',
            'role' => 'Sales Executive',
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Apex Global Ltd',
            'email' => 'contact@apex.com',
        ]);
    }

    public function test_all_14_sales_events_registered_in_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();
        $this->assertArrayHasKey('sales', $events);
        $salesEvents = $events['sales']['events'];

        $expectedKeys = [
            'sales.order.created',
            'sales.order.confirmed',
            'sales.order.cancelled',
            'sales.order.short_closed',
            'sales.dispatch.created',
            'sales.dispatch.shipped',
            'sales.dispatch.delivered',
            'sales.invoice.created',
            'sales.invoice.paid',
            'sales.payment.received',
            'sales.return.created',
            'sales.return.approved',
            'sales.einvoice.generated',
            'sales.ewaybill.generated',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $salesEvents, "Catalog missing sales event: {$key}");
            $this->assertTrue($salesEvents[$key]['notify_assigned_user'], "Event {$key} should have notify_assigned_user set to true");
        }
    }

    public function test_sales_order_lifecycle_notifications(): void
    {
        $this->actingAs($this->manager);

        $order = SalesOrder::create([
            'tenant_id'          => $this->tenant->id,
            'customer_id'        => $this->customer->id,
            'sales_person_id'    => $this->salesperson->id,
            'sales_order_number' => 'SO-2026-001',
            'order_date'         => now()->toDateString(),
            'status'             => 'Draft',
            'total_amount'       => 75000.00,
        ]);

        // 1. Order Created (Manager creates, assigned salesperson alerted)
        SalesNotificationService::notifyOrderCreated($order);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'sales_order_created',
        ]);

        // 2. Order Confirmed
        SalesNotificationService::notifyOrderConfirmed($order);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'sales_order_confirmed',
        ]);

        // 3. Order Cancelled
        SalesNotificationService::notifyOrderCancelled($order, 'Client requested cancellation');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'sales_order_cancelled',
        ]);
    }

    public function test_dispatch_lifecycle_notifications(): void
    {
        $this->actingAs($this->manager);

        $order = SalesOrder::create([
            'tenant_id'          => $this->tenant->id,
            'customer_id'        => $this->customer->id,
            'sales_person_id'    => $this->salesperson->id,
            'sales_order_number' => 'SO-2026-002',
            'order_date'         => now()->toDateString(),
            'status'             => 'Confirmed',
            'total_amount'       => 50000.00,
        ]);

        $mr = \App\Domains\Sales\Models\MaterialRequirement::create([
            'tenant_id'          => $this->tenant->id,
            'sales_order_id'     => $order->id,
            'requirement_number' => 'MR-2026-001',
            'requirement_date'   => now()->toDateString(),
            'status'             => 'Pending',
        ]);

        $dispatch = DispatchOrder::create([
            'tenant_id'               => $this->tenant->id,
            'sales_order_id'          => $order->id,
            'material_requirement_id' => $mr->id,
            'customer_id'             => $this->customer->id,
            'dispatch_number'         => 'DO-2026-001',
            'dispatch_date'           => now()->toDateString(),
            'status'                  => 'Pending',
        ]);

        // 1. Dispatch Prepared
        SalesNotificationService::notifyDispatchCreated($dispatch);

        // 2. Dispatch Shipped
        SalesNotificationService::notifyDispatchShipped($dispatch);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'dispatch_shipped',
        ]);

        // 3. Dispatch Delivered
        SalesNotificationService::notifyDispatchDelivered($dispatch);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'dispatch_delivered',
        ]);
    }

    public function test_invoice_and_payment_notifications(): void
    {
        $this->actingAs($this->manager);

        $order = SalesOrder::create([
            'tenant_id'          => $this->tenant->id,
            'customer_id'        => $this->customer->id,
            'sales_person_id'    => $this->salesperson->id,
            'sales_order_number' => 'SO-2026-003',
            'order_date'         => now()->toDateString(),
            'status'             => 'Confirmed',
            'total_amount'       => 100000.00,
        ]);

        $invoice = Invoice::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'sales_order_id' => $order->id,
            'invoice_number' => 'INV-2026-001',
            'invoice_date'   => now()->toDateString(),
            'due_date'       => now()->addDays(30)->toDateString(),
            'status'         => 'Posted',
            'total_amount'   => 100000.00,
            'balance_due'    => 100000.00,
        ]);

        // 1. Invoice Created
        SalesNotificationService::notifyInvoiceCreated($invoice);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'invoice_created',
        ]);

        // 2. Payment Received
        $payment = CustomerPayment::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'payment_number' => 'RCPT-2026-001',
            'payment_date'   => now()->toDateString(),
            'amount'         => 100000.00,
            'payment_method' => 'NEFT',
        ]);

        SalesNotificationService::notifyPaymentReceived($payment);

        // 3. Invoice Paid in Full
        SalesNotificationService::notifyInvoicePaid($invoice, $payment);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesperson->id,
            'module'  => 'sales',
            'type'    => 'invoice_paid',
        ]);
    }

    public function test_sales_return_lifecycle_notifications(): void
    {
        $this->actingAs($this->manager);

        $return = SalesReturn::create([
            'tenant_id'           => $this->tenant->id,
            'customer_id'         => $this->customer->id,
            'return_number'       => 'RET-2026-001',
            'return_date'         => now()->toDateString(),
            'status'              => 'Pending',
            'total_refund_amount' => 15000.00,
            'reason'              => 'Defective packaging',
        ]);

        // 1. Return Created
        SalesNotificationService::notifyReturnCreated($return);

        // 2. Return Approved
        SalesNotificationService::notifyReturnApproved($return);

        $this->assertDatabaseHas('notifications', [
            'module' => 'sales',
            'type'   => 'alert',
        ]);
    }
}
