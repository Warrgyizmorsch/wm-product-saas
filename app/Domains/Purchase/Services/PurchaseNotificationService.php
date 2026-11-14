<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Platform\Services\NotificationRuleService;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class PurchaseNotificationService
{
    /**
     * 1. Trigger when a new Purchase Requisition (PR) is created.
     */
    public static function notifyRequisitionCreated(PurchaseRequisition $requisition): void
    {
        try {
            $prNo = $requisition->requisition_number ?: "PR-{$requisition->id}";
            $createdBy = auth()->user()?->name ?: 'Requester';
            $sourceType = ucfirst(str_replace('_', ' ', $requisition->source_type ?? 'direct'));
            $actionUrl = route('purchase.requisitions.show', $requisition->id);
            $requesterId = $requisition->requested_by ?: $requisition->created_by;

            NotificationRuleService::trigger(
                eventKey: 'purchase.requisition.created',
                data: [
                    'pr_no'              => $prNo,
                    'source_type'        => $sourceType,
                    'created_by'         => $createdBy,
                    'date'               => now()->format('d M Y, h:i A'),
                    'created_by_user_id' => $requesterId,
                    'assigned_user_id'   => $requesterId,
                ],
                actionUrl: $actionUrl,
                subjectModel: $requisition
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.requisition.created): " . $e->getMessage());
        }
    }

    /**
     * 2. Trigger when a Purchase Requisition is approved.
     */
    public static function notifyRequisitionApproved(PurchaseRequisition $requisition): void
    {
        try {
            $prNo = $requisition->requisition_number ?: "PR-{$requisition->id}";
            $approvedBy = auth()->user()?->name ?: 'Approver';
            $sourceType = ucfirst(str_replace('_', ' ', $requisition->source_type ?? 'direct'));
            $actionUrl = route('purchase.requisitions.show', $requisition->id);
            $requesterId = $requisition->requested_by ?: $requisition->created_by;

            // Direct in-app alert to PR requester
            if (!empty($requesterId)) {
                NotificationService::send(
                    user: $requesterId,
                    title: "PR Approved: {$prNo}",
                    message: "Purchase Requisition {$prNo} for {$sourceType} has been approved by {$approvedBy}.",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_requisition_approved',
                    iconClass: 'feather-check-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.requisition.approved',
                data: [
                    'pr_no'              => $prNo,
                    'source_type'        => $sourceType,
                    'approved_by'        => $approvedBy,
                    'created_by_user_id' => $requesterId,
                    'assigned_user_id'   => $requesterId,
                    'exclude_user_ids'   => !empty($requesterId) ? [$requesterId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $requisition
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.requisition.approved): " . $e->getMessage());
        }
    }

    /**
     * 3. Trigger when a Purchase Requisition is rejected.
     */
    public static function notifyRequisitionRejected(PurchaseRequisition $requisition, ?string $reason = null): void
    {
        try {
            $prNo = $requisition->requisition_number ?: "PR-{$requisition->id}";
            $rejectedBy = auth()->user()?->name ?: 'Manager';
            $rejectionReason = $reason ?? ($requisition->rejection_reason ?? 'Not approved');
            $actionUrl = route('purchase.requisitions.show', $requisition->id);
            $requesterId = $requisition->requested_by ?: $requisition->created_by;

            // Direct in-app alert to PR requester
            if (!empty($requesterId)) {
                NotificationService::send(
                    user: $requesterId,
                    title: "PR Rejected: {$prNo}",
                    message: "Purchase Requisition {$prNo} was rejected by {$rejectedBy} (Reason: {$rejectionReason}).",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_requisition_rejected',
                    iconClass: 'feather-x-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.requisition.rejected',
                data: [
                    'pr_no'              => $prNo,
                    'rejected_by'        => $rejectedBy,
                    'reason'             => $rejectionReason,
                    'created_by_user_id' => $requesterId,
                    'assigned_user_id'   => $requesterId,
                    'exclude_user_ids'   => !empty($requesterId) ? [$requesterId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $requisition
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.requisition.rejected): " . $e->getMessage());
        }
    }

    /**
     * 4. Trigger when an RFQ is created.
     */
    public static function notifyRfqCreated(PurchaseRfq $rfq): void
    {
        try {
            $rfqNo = $rfq->rfq_number ?: "RFQ-{$rfq->id}";
            $createdBy = auth()->user()?->name ?: 'Purchase Officer';
            $actionUrl = route('purchase.rfqs.show', $rfq->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.rfq.created',
                data: [
                    'rfq_no'             => $rfqNo,
                    'created_by'         => $createdBy,
                    'date'               => now()->format('d M Y, h:i A'),
                    'created_by_user_id' => $rfq->created_by,
                    'assigned_user_id'   => $rfq->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $rfq
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.rfq.created): " . $e->getMessage());
        }
    }

    /**
     * 5. Trigger when an RFQ is confirmed / finalized.
     */
    public static function notifyRfqConfirmed(PurchaseRfq $rfq): void
    {
        try {
            $rfqNo = $rfq->rfq_number ?: "RFQ-{$rfq->id}";
            $confirmedBy = auth()->user()?->name ?: 'Purchase Manager';
            $actionUrl = route('purchase.rfqs.show', $rfq->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.rfq.confirmed',
                data: [
                    'rfq_no'             => $rfqNo,
                    'confirmed_by'       => $confirmedBy,
                    'created_by_user_id' => $rfq->created_by,
                    'assigned_user_id'   => $rfq->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $rfq
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.rfq.confirmed): " . $e->getMessage());
        }
    }

    /**
     * 6. Trigger when a Purchase Order is created.
     */
    public static function notifyOrderCreated(PurchaseOrder $order): void
    {
        try {
            $order->loadMissing(['vendor']);
            $poNo = $order->purchase_order_number ?: ($order->po_number ?: "PO-{$order->id}");
            $vendorName = $order->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($order->grand_total ?? $order->total_amount ?? 0), 2);
            $createdBy = auth()->user()?->name ?: 'Purchase Officer';
            $actionUrl = route('purchase.orders.show', $order->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.order.created',
                data: [
                    'po_no'              => $poNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'created_by'         => $createdBy,
                    'created_by_user_id' => $order->created_by,
                    'assigned_user_id'   => $order->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.order.created): " . $e->getMessage());
        }
    }

    /**
     * 7. Trigger when a Purchase Order is approved.
     */
    public static function notifyOrderApproved(PurchaseOrder $order): void
    {
        try {
            $order->loadMissing(['vendor']);
            $poNo = $order->purchase_order_number ?: ($order->po_number ?: "PO-{$order->id}");
            $vendorName = $order->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($order->grand_total ?? $order->total_amount ?? 0), 2);
            $approvedBy = auth()->user()?->name ?: 'Approver';
            $actionUrl = route('purchase.orders.show', $order->id);

            // Direct in-app alert to PO creator if approved by another user
            if (!empty($order->created_by) && $order->created_by !== auth()->id()) {
                NotificationService::send(
                    user: $order->created_by,
                    title: "PO Approved: {$poNo}",
                    message: "Purchase Order {$poNo} for {$vendorName} (₹{$amountFormatted}) has been approved by {$approvedBy}.",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_order_approved',
                    iconClass: 'feather-check-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.order.approved',
                data: [
                    'po_no'              => $poNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'approved_by'        => $approvedBy,
                    'created_by_user_id' => $order->created_by,
                    'assigned_user_id'   => $order->created_by,
                    'exclude_user_ids'   => (!empty($order->created_by) && $order->created_by !== auth()->id()) ? [$order->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.order.approved): " . $e->getMessage());
        }
    }

    /**
     * 8. Trigger when a Purchase Order is rejected / cancelled.
     */
    public static function notifyOrderRejected(PurchaseOrder $order, ?string $reason = null): void
    {
        try {
            $order->loadMissing(['vendor']);
            $poNo = $order->purchase_order_number ?: ($order->po_number ?: "PO-{$order->id}");
            $vendorName = $order->vendor?->name ?: 'Vendor';
            $rejectedBy = auth()->user()?->name ?: 'Manager';
            $rejectionReason = $reason ?? ($order->rejection_reason ?? 'Cancelled');
            $actionUrl = route('purchase.orders.show', $order->id);

            // Direct in-app alert to PO creator
            if (!empty($order->created_by) && $order->created_by !== auth()->id()) {
                NotificationService::send(
                    user: $order->created_by,
                    title: "PO Cancelled: {$poNo}",
                    message: "Purchase Order {$poNo} for {$vendorName} was cancelled by {$rejectedBy} (Reason: {$rejectionReason}).",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_order_rejected',
                    iconClass: 'feather-x-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.order.rejected',
                data: [
                    'po_no'              => $poNo,
                    'vendor_name'        => $vendorName,
                    'rejected_by'        => $rejectedBy,
                    'reason'             => $rejectionReason,
                    'created_by_user_id' => $order->created_by,
                    'assigned_user_id'   => $order->created_by,
                    'exclude_user_ids'   => (!empty($order->created_by) && $order->created_by !== auth()->id()) ? [$order->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.order.rejected): " . $e->getMessage());
        }
    }

    /**
     * 9. Trigger when a Goods Receipt Note (GRN) is inward received.
     */
    public static function notifyGrnReceived(GoodsReceiptNote $grn): void
    {
        try {
            $grn->loadMissing(['vendor', 'warehouse', 'purchaseOrder']);
            $grnNo = $grn->grn_number ?: "GRN-{$grn->id}";
            $vendorName = $grn->vendor?->name ?: 'Vendor';
            $whName = $grn->warehouse?->name ?: 'Main Warehouse';
            $poNo = $grn->purchaseOrder?->purchase_order_number ?: ($grn->purchaseOrder?->po_number ?: 'Direct Inward');
            $actionUrl = route('purchase.grns.show', $grn->id);

            // Alert the creator of the original PO that material has arrived at store
            $poCreatorId = $grn->purchaseOrder?->created_by;
            if (!empty($poCreatorId) && $poCreatorId !== auth()->id()) {
                NotificationService::send(
                    user: $poCreatorId,
                    title: "GRN Received: {$grnNo}",
                    message: "Material for PO {$poNo} from {$vendorName} has arrived and inwarded at {$whName} ({$grnNo}).",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_grn_received',
                    iconClass: 'feather-package'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.grn.received',
                data: [
                    'grn_no'             => $grnNo,
                    'vendor_name'        => $vendorName,
                    'warehouse'          => $whName,
                    'po_no'              => $poNo,
                    'created_by_user_id' => $grn->created_by,
                    'assigned_user_id'   => $poCreatorId ?? $grn->created_by,
                    'exclude_user_ids'   => (!empty($poCreatorId) && $poCreatorId !== auth()->id()) ? [$poCreatorId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $grn
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.grn.received): " . $e->getMessage());
        }
    }

    /**
     * 10. Trigger when a GRN is approved & verified.
     */
    public static function notifyGrnApproved(GoodsReceiptNote $grn): void
    {
        try {
            $grn->loadMissing(['vendor', 'purchaseOrder']);
            $grnNo = $grn->grn_number ?: "GRN-{$grn->id}";
            $vendorName = $grn->vendor?->name ?: 'Vendor';
            $poNo = $grn->purchaseOrder?->purchase_order_number ?: ($grn->purchaseOrder?->po_number ?: 'PO');
            $approvedBy = auth()->user()?->name ?: 'Quality Inspector';
            $actionUrl = route('purchase.grns.show', $grn->id);

            // Alert GRN creator that their GRN passed inspection & approved
            if (!empty($grn->created_by)) {
                NotificationService::send(
                    user: $grn->created_by,
                    title: "GRN Approved: {$grnNo}",
                    message: "Goods Receipt {$grnNo} for {$vendorName} (PO: {$poNo}) has been approved by {$approvedBy}.",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_grn_approved',
                    iconClass: 'feather-check-square'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.grn.approved',
                data: [
                    'grn_no'             => $grnNo,
                    'vendor_name'        => $vendorName,
                    'po_no'              => $poNo,
                    'approved_by'        => $approvedBy,
                    'created_by_user_id' => $grn->created_by,
                    'assigned_user_id'   => $grn->created_by,
                    'exclude_user_ids'   => !empty($grn->created_by) ? [$grn->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $grn
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.grn.approved): " . $e->getMessage());
        }
    }

    /**
     * 11. Trigger when a Vendor Bill is logged.
     */
    public static function notifyBillCreated(VendorBill $bill): void
    {
        try {
            $bill->loadMissing(['vendor']);
            $billNo = $bill->bill_number ?: ($bill->vendor_bill_number ?: "BILL-{$bill->id}");
            $vendorName = $bill->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($bill->grand_total ?? $bill->total_amount ?? 0), 2);
            $dueDateFormatted = $bill->due_date ? (is_string($bill->due_date) ? $bill->due_date : $bill->due_date->format('d M Y')) : 'N/A';
            $actionUrl = route('purchase.bills.show', $bill->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.bill.created',
                data: [
                    'bill_no'            => $billNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'due_date'           => $dueDateFormatted,
                    'created_by_user_id' => $bill->created_by,
                    'assigned_user_id'   => $bill->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $bill
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.bill.created): " . $e->getMessage());
        }
    }

    /**
     * 12. Trigger when 3-way match exception hold is released on a Bill.
     */
    public static function notifyBillHoldReleased(VendorBill $bill): void
    {
        try {
            $bill->loadMissing(['vendor']);
            $billNo = $bill->bill_number ?: ($bill->vendor_bill_number ?: "BILL-{$bill->id}");
            $vendorName = $bill->vendor?->name ?: 'Vendor';
            $releasedBy = auth()->user()?->name ?: 'Manager';
            $actionUrl = route('purchase.bills.show', $bill->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.bill.hold_released',
                data: [
                    'bill_no'            => $billNo,
                    'vendor_name'        => $vendorName,
                    'released_by'        => $releasedBy,
                    'created_by_user_id' => $bill->created_by,
                    'assigned_user_id'   => $bill->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $bill
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.bill.hold_released): " . $e->getMessage());
        }
    }

    /**
     * 13. Trigger when a Vendor Payment is recorded.
     */
    public static function notifyPaymentRecorded(VendorPayment $payment): void
    {
        try {
            $payment->loadMissing(['vendor']);
            $paymentNo = $payment->payment_number ?: "PAY-{$payment->id}";
            $vendorName = $payment->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($payment->amount ?? 0), 2);
            $paymentMethod = $payment->payment_method ?: 'Bank Transfer';
            $actionUrl = route('purchase.payments.show', $payment->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.payment.recorded',
                data: [
                    'payment_no'         => $paymentNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'payment_method'     => $paymentMethod,
                    'created_by_user_id' => $payment->created_by,
                    'assigned_user_id'   => $payment->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $payment
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.payment.recorded): " . $e->getMessage());
        }
    }

    /**
     * 14. Trigger when a Vendor Advance Payment is recorded.
     */
    public static function notifyAdvanceRecorded(PurchaseAdvancePayment $advance): void
    {
        try {
            $advance->loadMissing(['vendor', 'purchaseOrder']);
            $advanceNo = $advance->advance_number ?: "ADV-{$advance->id}";
            $vendorName = $advance->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($advance->amount ?? 0), 2);
            $poNo = $advance->purchaseOrder?->purchase_order_number ?: ($advance->purchaseOrder?->po_number ?: 'General PO');
            $paymentMethod = $advance->payment_method ?: 'Bank Transfer';
            $actionUrl = route('purchase.advances.show', $advance->id);

            // Alert the creator of the PO that advance has been disbursed
            $poCreatorId = $advance->purchaseOrder?->created_by;
            if (!empty($poCreatorId) && $poCreatorId !== auth()->id()) {
                NotificationService::send(
                    user: $poCreatorId,
                    title: "Advance Paid: {$advanceNo}",
                    message: "Advance Payment of ₹{$amountFormatted} paid to {$vendorName} for PO {$poNo}.",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_advance_recorded',
                    iconClass: 'feather-arrow-up-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.advance.recorded',
                data: [
                    'advance_no'         => $advanceNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'po_no'              => $poNo,
                    'payment_method'     => $paymentMethod,
                    'created_by_user_id' => $advance->created_by,
                    'assigned_user_id'   => $poCreatorId ?? $advance->created_by,
                    'exclude_user_ids'   => (!empty($poCreatorId) && $poCreatorId !== auth()->id()) ? [$poCreatorId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $advance
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.advance.recorded): " . $e->getMessage());
        }
    }

    /**
     * 15. Trigger when a Purchase Return / Debit Note is created.
     */
    public static function notifyReturnCreated(PurchaseReturn $return): void
    {
        try {
            $return->loadMissing(['vendor']);
            $returnNo = $return->return_number ?: "RET-{$return->id}";
            $vendorName = $return->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($return->total_amount ?? 0), 2);
            $reason = $return->reason ?: 'Material Returned';
            $createdBy = auth()->user()?->name ?: 'Storekeeper';
            $actionUrl = route('purchase.returns.show', $return->id);

            NotificationRuleService::trigger(
                eventKey: 'purchase.return.created',
                data: [
                    'return_no'          => $returnNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'reason'             => $reason,
                    'created_by'         => $createdBy,
                    'created_by_user_id' => $return->created_by,
                    'assigned_user_id'   => $return->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $return
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.return.created): " . $e->getMessage());
        }
    }

    /**
     * 16. Trigger when a Purchase Return / Debit Note is approved.
     */
    public static function notifyReturnApproved(PurchaseReturn $return, ?int $recipientUserId = null): void
    {
        try {
            $return->loadMissing(['vendor', 'purchaseOrder']);
            $returnNo = $return->return_number ?: "RET-{$return->id}";
            $vendorName = $return->vendor?->name ?: 'Vendor';
            $amountFormatted = number_format((float) ($return->total_amount ?? 0), 2);
            $approvedBy = auth()->user()?->name ?: 'Manager';
            $actionUrl = route('purchase.returns.show', $return->id);
            $targetUserId = $recipientUserId ?? ($return->purchaseOrder?->created_by ?? null);

            // Direct in-app alert to return requester or PO creator
            if (!empty($targetUserId) && $targetUserId !== auth()->id()) {
                NotificationService::send(
                    user: $targetUserId,
                    title: "Debit Note Approved: {$returnNo}",
                    message: "Purchase Return {$returnNo} for {$vendorName} (₹{$amountFormatted}) has been approved by {$approvedBy}.",
                    actionUrl: $actionUrl,
                    module: 'purchase',
                    type: 'purchase_return_approved',
                    iconClass: 'feather-check-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'purchase.return.approved',
                data: [
                    'return_no'          => $returnNo,
                    'vendor_name'        => $vendorName,
                    'amount'             => $amountFormatted,
                    'approved_by'        => $approvedBy,
                    'assigned_user_id'   => $targetUserId,
                    'exclude_user_ids'   => (!empty($targetUserId) && $targetUserId !== auth()->id()) ? [$targetUserId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $return
            );
        } catch (\Throwable $e) {
            Log::warning("Purchase Notification failed (purchase.return.approved): " . $e->getMessage());
        }
    }
}
