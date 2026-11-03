<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\NotificationRule;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Services\PurchaseNotificationService;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseAllNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $purchaseManager;
    protected User $purchaseOfficer;
    protected User $accountant;
    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Procurement Tenant',
            'slug' => 'procurement-tenant',
        ]);

        $this->purchaseManager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Purchase Manager',
            'email' => 'purchasemanager@test.com',
            'role' => 'Purchase Manager',
        ]);

        $this->purchaseOfficer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Purchase Officer',
            'email' => 'officer@test.com',
            'role' => 'Purchase Executive',
        ]);

        $this->accountant = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant Staff',
            'email' => 'accountant@test.com',
            'role' => 'Accountant',
        ]);

        $this->vendor = Vendor::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tata Steel Industries',
            'email' => 'orders@tatasteel.com',
            'status' => 'active',
        ]);
    }

    public function test_all_16_purchase_events_registered_in_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();
        $this->assertArrayHasKey('purchase', $events);
        $purchaseEvents = $events['purchase']['events'];

        $expectedKeys = [
            'purchase.requisition.created',
            'purchase.requisition.approved',
            'purchase.requisition.rejected',
            'purchase.rfq.created',
            'purchase.rfq.confirmed',
            'purchase.order.created',
            'purchase.order.approved',
            'purchase.order.rejected',
            'purchase.grn.received',
            'purchase.grn.approved',
            'purchase.bill.created',
            'purchase.bill.hold_released',
            'purchase.payment.recorded',
            'purchase.advance.recorded',
            'purchase.return.created',
            'purchase.return.approved',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $purchaseEvents, "Catalog missing purchase event: {$key}");
            $this->assertNotEmpty($purchaseEvents[$key]['label']);
            $this->assertNotEmpty($purchaseEvents[$key]['default_title']);
            $this->assertNotEmpty($purchaseEvents[$key]['default_body']);
        }
    }

    public function test_purchase_requisition_lifecycle_notifications(): void
    {
        $this->actingAs($this->purchaseOfficer);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PR Created Rule',
            'event_key' => 'purchase.requisition.created',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'New PR: {{pr_no}}',
            'body_template' => 'Created by {{created_by}}',
            'action_route' => 'purchase.requisitions.show',
            'is_active' => true,
        ]);

        $requisition = PurchaseRequisition::create([
            'tenant_id' => $this->tenant->id,
            'requisition_number' => 'PR-2026-001',
            'requisition_date' => now()->toDateString(),
            'source_type' => 'direct',
            'status' => 'Draft',
            'requested_by' => $this->purchaseOfficer->id,
        ]);

        // 1. PR Created -> Purchase Manager gets alert
        PurchaseNotificationService::notifyRequisitionCreated($requisition);

        $createdNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%New PR%')
            ->first();
        $this->assertNotNull($createdNotif);

        // 2. PR Approved -> Requester (Officer) gets direct in-app alert
        $this->actingAs($this->purchaseManager);
        PurchaseNotificationService::notifyRequisitionApproved($requisition);

        $approvedNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%PR Approved%')
            ->first();
        $this->assertNotNull($approvedNotif);

        // 3. PR Rejected -> Requester gets direct in-app alert
        PurchaseNotificationService::notifyRequisitionRejected($requisition, 'Budget exceeded for Q3');

        $rejectedNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%PR Rejected%')
            ->first();
        $this->assertNotNull($rejectedNotif);
    }

    public function test_purchase_rfq_lifecycle_notifications(): void
    {
        $this->actingAs($this->purchaseOfficer);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'RFQ Rule',
            'event_key' => 'purchase.rfq.created',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'RFQ Created: {{rfq_no}}',
            'body_template' => 'RFQ for quotes',
            'action_route' => 'purchase.rfqs.show',
            'is_active' => true,
        ]);

        $rfq = PurchaseRfq::create([
            'tenant_id' => $this->tenant->id,
            'rfq_number' => 'RFQ-2026-001',
            'rfq_date' => now()->toDateString(),
            'status' => 'Draft',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        // 1. RFQ Created
        PurchaseNotificationService::notifyRfqCreated($rfq);

        $rfqNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%RFQ Created%')
            ->first();
        $this->assertNotNull($rfqNotif);

        // 2. RFQ Confirmed
        $this->actingAs($this->purchaseManager);
        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'RFQ Confirmed Rule',
            'event_key' => 'purchase.rfq.confirmed',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [$this->purchaseOfficer->id],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'RFQ Finalized: {{rfq_no}}',
            'body_template' => 'Ready for PO conversion',
            'action_route' => 'purchase.rfqs.show',
            'is_active' => true,
        ]);

        PurchaseNotificationService::notifyRfqConfirmed($rfq);

        $confirmedNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%RFQ Finalized%')
            ->first();
        $this->assertNotNull($confirmedNotif);
    }

    public function test_purchase_order_lifecycle_notifications(): void
    {
        $this->actingAs($this->purchaseOfficer);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'PO Created Rule',
            'event_key' => 'purchase.order.created',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'PO Created: {{po_no}}',
            'body_template' => 'Vendor: {{vendor_name}}',
            'action_route' => 'purchase.orders.show',
            'is_active' => true,
        ]);

        $order = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'po_number' => 'PO-2026-999',
            'purchase_order_number' => 'PO-2026-999',
            'vendor_id' => $this->vendor->id,
            'date' => now()->toDateString(),
            'grand_total' => 150000.00,
            'status' => 'Draft',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        // 1. PO Created -> Manager alerted
        PurchaseNotificationService::notifyOrderCreated($order);

        $poCreatedNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%PO Created%')
            ->first();
        $this->assertNotNull($poCreatedNotif);

        // 2. PO Approved by Manager -> Officer alerted
        $this->actingAs($this->purchaseManager);
        PurchaseNotificationService::notifyOrderApproved($order);

        $poApprovedNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%PO Approved%')
            ->first();
        $this->assertNotNull($poApprovedNotif);

        // 3. PO Rejected -> Officer alerted
        PurchaseNotificationService::notifyOrderRejected($order, 'Price mismatch with agreed contract');

        $poRejectedNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%PO Cancelled%')
            ->first();
        $this->assertNotNull($poRejectedNotif);
    }

    public function test_goods_receipt_note_lifecycle_notifications(): void
    {
        $order = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'po_number' => 'PO-2026-888',
            'purchase_order_number' => 'PO-2026-888',
            'vendor_id' => $this->vendor->id,
            'date' => now()->toDateString(),
            'grand_total' => 50000.00,
            'status' => 'Approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenant->id,
            'grn_number' => 'GRN-2026-001',
            'purchase_order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'received_date' => now()->toDateString(),
            'status' => 'Draft',
            'created_by' => $this->purchaseManager->id,
        ]);

        // 1. GRN Inward Received (Manager creates GRN -> PO creator Officer alerted)
        $this->actingAs($this->purchaseManager);
        PurchaseNotificationService::notifyGrnReceived($grn);

        $grnRecNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%GRN Received%')
            ->first();
        $this->assertNotNull($grnRecNotif);

        // 2. GRN Approved -> GRN creator alerted
        $this->actingAs($this->accountant);
        PurchaseNotificationService::notifyGrnApproved($grn);

        $grnAppNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%GRN Approved%')
            ->first();
        $this->assertNotNull($grnAppNotif);
    }

    public function test_vendor_bills_payments_advances_and_returns(): void
    {
        $this->actingAs($this->accountant);

        // 1. Vendor Bill Logged
        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bill Rule',
            'event_key' => 'purchase.bill.created',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Bill Logged: {{bill_no}}',
            'body_template' => 'Vendor: {{vendor_name}}',
            'action_route' => 'purchase.bills.show',
            'is_active' => true,
        ]);

        $bill = VendorBill::create([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-2026-100',
            'vendor_id' => $this->vendor->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 45000.00,
            'grand_total' => 45000.00,
            'status' => 'Unpaid',
            'created_by' => $this->accountant->id,
        ]);

        PurchaseNotificationService::notifyBillCreated($bill);

        $billNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%Bill Logged%')
            ->first();
        $this->assertNotNull($billNotif);

        // 2. Bill Hold Released
        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Hold Released Rule',
            'event_key' => 'purchase.bill.hold_released',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Hold Cleared: {{bill_no}}',
            'body_template' => 'Released for payment',
            'action_route' => 'purchase.bills.show',
            'is_active' => true,
        ]);

        PurchaseNotificationService::notifyBillHoldReleased($bill);

        $holdNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%Hold Cleared%')
            ->first();
        $this->assertNotNull($holdNotif);

        // 3. Vendor Payment Recorded
        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Payment Rule',
            'event_key' => 'purchase.payment.recorded',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Payment Paid: {{payment_no}}',
            'body_template' => 'Amount: {{amount}}',
            'action_route' => 'purchase.payments.show',
            'is_active' => true,
        ]);

        $payment = VendorPayment::create([
            'tenant_id' => $this->tenant->id,
            'payment_number' => 'PAY-2026-001',
            'vendor_id' => $this->vendor->id,
            'payment_date' => now()->toDateString(),
            'amount' => 45000.00,
            'payment_method' => 'NEFT',
            'status' => 'Paid',
            'created_by' => $this->accountant->id,
        ]);

        PurchaseNotificationService::notifyPaymentRecorded($payment);

        $paymentNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%Payment Paid%')
            ->first();
        $this->assertNotNull($paymentNotif);

        // 4. Purchase Advance Payment Recorded
        $order = PurchaseOrder::create([
            'tenant_id' => $this->tenant->id,
            'po_number' => 'PO-ADV-01',
            'purchase_order_number' => 'PO-ADV-01',
            'vendor_id' => $this->vendor->id,
            'date' => now()->toDateString(),
            'grand_total' => 80000.00,
            'status' => 'Approved',
            'created_by' => $this->purchaseOfficer->id,
        ]);

        $advance = PurchaseAdvancePayment::create([
            'tenant_id' => $this->tenant->id,
            'payment_number' => 'ADV-2026-001',
            'purchase_order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'payment_date' => now()->toDateString(),
            'amount' => 20000.00,
            'payment_method' => 'Bank Transfer',
            'status' => 'Paid',
            'created_by' => $this->accountant->id,
        ]);

        PurchaseNotificationService::notifyAdvanceRecorded($advance);

        $advanceNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%Advance Paid%')
            ->first();
        $this->assertNotNull($advanceNotif);

        // 5. Purchase Return (Debit Note) Lifecycle
        $return = PurchaseReturn::create([
            'tenant_id' => $this->tenant->id,
            'return_number' => 'RET-2026-001',
            'purchase_order_id' => $order->id,
            'vendor_id' => $this->vendor->id,
            'return_date' => now()->toDateString(),
            'total_amount' => 12000.00,
            'reason' => 'Damaged Goods',
            'status' => 'Pending',
        ]);

        NotificationRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Return Rule',
            'event_key' => 'purchase.return.created',
            'module' => 'purchase',
            'recipient_roles' => ['Purchase Manager'],
            'recipient_user_ids' => [],
            'notify_creator' => false,
            'notify_assigned_user' => false,
            'title_template' => 'Debit Note: {{return_no}}',
            'body_template' => 'Reason: {{reason}}',
            'action_route' => 'purchase.returns.show',
            'is_active' => true,
        ]);

        PurchaseNotificationService::notifyReturnCreated($return);

        $returnNotif = Notification::where('user_id', $this->purchaseManager->id)
            ->where('title', 'like', '%Debit Note%')
            ->first();
        $this->assertNotNull($returnNotif);

        // Return Approved -> Officer alerted
        PurchaseNotificationService::notifyReturnApproved($return);

        $returnAppNotif = Notification::where('user_id', $this->purchaseOfficer->id)
            ->where('title', 'like', '%Debit Note Approved%')
            ->first();
        $this->assertNotNull($returnAppNotif);
    }
}
