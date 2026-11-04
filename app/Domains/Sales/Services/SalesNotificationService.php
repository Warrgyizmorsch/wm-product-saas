<?php

namespace App\Domains\Sales\Services;

use App\Domains\Platform\Services\NotificationRuleService;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class SalesNotificationService
{
    /**
     * 1. Trigger when a new Sales Order is created.
     */
    public static function notifyOrderCreated(SalesOrder $order): void
    {
        try {
            $customerName = $order->customer?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($order->total_amount ?? 0), 2);
            $createdBy = auth()->user()?->name ?: 'System';
            $actionUrl = route('sales.orders.show', $order->id);

            // Direct in-app + Pusher alert to assigned salesperson if created by someone else
            if (!empty($order->sales_person_id) && $order->sales_person_id !== auth()->id()) {
                NotificationService::send(
                    user: $order->sales_person_id,
                    title: "New Sales Order: {$order->sales_order_number}",
                    message: "Sales Order {$order->sales_order_number} for {$customerName} (₹{$amountFormatted}) has been created by {$createdBy}.",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'sales_order_created',
                    iconClass: 'feather-shopping-bag'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.order.created',
                data: [
                    'doc_no'           => $order->sales_order_number,
                    'customer_name'    => $customerName,
                    'amount'           => $amountFormatted,
                    'created_by'       => $createdBy,
                    'date'             => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $order->sales_person_id,
                    'exclude_user_ids' => (!empty($order->sales_person_id) && $order->sales_person_id !== auth()->id()) ? [$order->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.order.created): " . $e->getMessage());
        }
    }

    /**
     * 2. Trigger when a Sales Order is confirmed.
     */
    public static function notifyOrderConfirmed(SalesOrder $order): void
    {
        try {
            $customerName = $order->customer?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($order->total_amount ?? 0), 2);
            $confirmedBy = auth()->user()?->name ?: 'User';
            $actionUrl = route('sales.orders.show', $order->id);

            // Direct in-app + Pusher alert to salesperson
            if (!empty($order->sales_person_id)) {
                NotificationService::send(
                    user: $order->sales_person_id,
                    title: "Sales Order Confirmed: {$order->sales_order_number}",
                    message: "Sales Order {$order->sales_order_number} for {$customerName} (₹{$amountFormatted}) has been confirmed and released for fulfillment.",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'sales_order_confirmed',
                    iconClass: 'feather-check-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.order.confirmed',
                data: [
                    'doc_no'           => $order->sales_order_number,
                    'customer_name'    => $customerName,
                    'amount'           => $amountFormatted,
                    'confirmed_by'     => $confirmedBy,
                    'date'             => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $order->sales_person_id,
                    'exclude_user_ids' => !empty($order->sales_person_id) ? [$order->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.order.confirmed): " . $e->getMessage());
        }
    }

    /**
     * 3. Trigger when a Sales Order is cancelled.
     */
    public static function notifyOrderCancelled(SalesOrder $order, string $reason): void
    {
        try {
            $customerName = $order->customer?->name ?: 'Customer';
            $cancelledBy = auth()->user()?->name ?: 'User';
            $actionUrl = route('sales.orders.show', $order->id);

            if (!empty($order->sales_person_id)) {
                NotificationService::send(
                    user: $order->sales_person_id,
                    title: "❌ Sales Order Cancelled: {$order->sales_order_number}",
                    message: "Sales Order {$order->sales_order_number} for {$customerName} was cancelled by {$cancelledBy}. Reason: {$reason}",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'sales_order_cancelled',
                    iconClass: 'feather-x-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.order.cancelled',
                data: [
                    'doc_no'           => $order->sales_order_number,
                    'customer_name'    => $customerName,
                    'cancelled_by'     => $cancelledBy,
                    'reason'           => $reason,
                    'date'             => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $order->sales_person_id,
                    'exclude_user_ids' => !empty($order->sales_person_id) ? [$order->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.order.cancelled): " . $e->getMessage());
        }
    }

    /**
     * 4. Trigger when a Sales Order is short-closed.
     */
    public static function notifyOrderShortClosed(SalesOrder $order, string $reason): void
    {
        try {
            $customerName = $order->customer?->name ?: 'Customer';
            $closedBy = auth()->user()?->name ?: 'User';
            $actionUrl = route('sales.orders.show', $order->id);

            if (!empty($order->sales_person_id)) {
                NotificationService::send(
                    user: $order->sales_person_id,
                    title: "Sales Order Short-Closed: {$order->sales_order_number}",
                    message: "Sales Order {$order->sales_order_number} was short-closed by {$closedBy}. Reason: {$reason}",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'sales_order_short_closed',
                    iconClass: 'feather-slash'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.order.short_closed',
                data: [
                    'doc_no'           => $order->sales_order_number,
                    'customer_name'    => $customerName,
                    'closed_by'        => $closedBy,
                    'reason'           => $reason,
                    'date'             => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $order->sales_person_id,
                    'exclude_user_ids' => !empty($order->sales_person_id) ? [$order->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $order
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.order.short_closed): " . $e->getMessage());
        }
    }

    /**
     * 5. Trigger when a Dispatch Order is prepared.
     */
    public static function notifyDispatchCreated(DispatchOrder $dispatch): void
    {
        try {
            $customerName = $dispatch->customer?->name ?: 'Customer';
            $soNumber = $dispatch->salesOrder?->sales_order_number ?: 'N/A';
            $salesPersonId = $dispatch->salesOrder?->sales_person_id;
            $preparedBy = auth()->user()?->name ?: 'Store';
            $actionUrl = route('sales.dispatches.show', $dispatch->id);

            NotificationRuleService::trigger(
                eventKey: 'sales.dispatch.created',
                data: [
                    'doc_no'           => $dispatch->dispatch_number,
                    'customer_name'    => $customerName,
                    'so_no'            => $soNumber,
                    'prepared_by'      => $preparedBy,
                    'date'             => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $salesPersonId,
                ],
                actionUrl: $actionUrl,
                subjectModel: $dispatch
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.dispatch.created): " . $e->getMessage());
        }
    }

    /**
     * 6. Trigger when goods are Shipped out (Gate Outward).
     */
    public static function notifyDispatchShipped(DispatchOrder $dispatch): void
    {
        try {
            $customerName = $dispatch->customer?->name ?: 'Customer';
            $soNumber = $dispatch->salesOrder?->sales_order_number ?: 'N/A';
            $salesPersonId = $dispatch->salesOrder?->sales_person_id;
            $transporter = $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Transporter');
            $trackingNo = $dispatch->tracking_number ?: ($dispatch->lr_number ?: 'Pending');
            $actionUrl = route('sales.dispatches.show', $dispatch->id);

            // Direct in-app + Pusher alert to salesperson
            if (!empty($salesPersonId)) {
                NotificationService::send(
                    user: $salesPersonId,
                    title: "🚚 Order Dispatched: {$dispatch->dispatch_number}",
                    message: "Dispatch {$dispatch->dispatch_number} for {$customerName} (SO: {$soNumber}) is shipped via {$transporter} (LR: {$trackingNo}).",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'dispatch_shipped',
                    iconClass: 'feather-truck'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.dispatch.shipped',
                data: [
                    'doc_no'           => $dispatch->dispatch_number,
                    'customer_name'    => $customerName,
                    'transporter'      => $transporter,
                    'tracking_no'      => $trackingNo,
                    'so_no'            => $soNumber,
                    'date'             => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $salesPersonId,
                    'exclude_user_ids' => !empty($salesPersonId) ? [$salesPersonId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $dispatch
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.dispatch.shipped): " . $e->getMessage());
        }
    }

    /**
     * 7. Trigger when goods are Delivered / Proof of Delivery (POD) uploaded.
     */
    public static function notifyDispatchDelivered(DispatchOrder $dispatch): void
    {
        try {
            $customerName = $dispatch->customer?->name ?: 'Customer';
            $soNumber = $dispatch->salesOrder?->sales_order_number ?: 'N/A';
            $salesPersonId = $dispatch->salesOrder?->sales_person_id;
            $actionUrl = route('sales.dispatches.show', $dispatch->id);

            if (!empty($salesPersonId)) {
                NotificationService::send(
                    user: $salesPersonId,
                    title: "✅ Order Delivered: {$dispatch->dispatch_number}",
                    message: "Dispatch {$dispatch->dispatch_number} for {$customerName} (SO: {$soNumber}) has been delivered successfully.",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'dispatch_delivered',
                    iconClass: 'feather-check-square'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.dispatch.delivered',
                data: [
                    'doc_no'           => $dispatch->dispatch_number,
                    'customer_name'    => $customerName,
                    'so_no'            => $soNumber,
                    'delivered_date'   => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $salesPersonId,
                    'exclude_user_ids' => !empty($salesPersonId) ? [$salesPersonId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $dispatch
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.dispatch.delivered): " . $e->getMessage());
        }
    }

    /**
     * 8. Trigger when a Sales Invoice is created and posted.
     */
    public static function notifyInvoiceCreated(Invoice $invoice): void
    {
        try {
            $customerName = $invoice->customer?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($invoice->total_amount ?? 0), 2);
            $dueDate = $invoice->due_date ? (is_string($invoice->due_date) ? $invoice->due_date : $invoice->due_date->format('d M Y')) : 'Upon Receipt';
            $createdBy = auth()->user()?->name ?: 'Billing Desk';
            $salesPersonId = $invoice->salesOrder?->sales_person_id;
            $actionUrl = route('sales.invoices.show', $invoice->id);

            if (!empty($salesPersonId)) {
                NotificationService::send(
                    user: $salesPersonId,
                    title: "Invoice Generated: {$invoice->invoice_number}",
                    message: "Invoice {$invoice->invoice_number} for {$customerName} (₹{$amountFormatted}) generated. Due: {$dueDate}.",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'invoice_created',
                    iconClass: 'feather-file-text'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.invoice.created',
                data: [
                    'doc_no'           => $invoice->invoice_number,
                    'customer_name'    => $customerName,
                    'amount'           => $amountFormatted,
                    'due_date'         => $dueDate,
                    'created_by'       => $createdBy,
                    'assigned_user_id' => $salesPersonId,
                    'exclude_user_ids' => !empty($salesPersonId) ? [$salesPersonId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $invoice
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.invoice.created): " . $e->getMessage());
        }
    }

    /**
     * 9. Trigger when an Invoice is fully paid.
     */
    public static function notifyInvoicePaid(Invoice $invoice, ?CustomerPayment $payment = null): void
    {
        try {
            $customerName = $invoice->customer?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($invoice->total_amount ?? 0), 2);
            $salesPersonId = $invoice->salesOrder?->sales_person_id;
            $actionUrl = route('sales.invoices.show', $invoice->id);

            if (!empty($salesPersonId)) {
                NotificationService::send(
                    user: $salesPersonId,
                    title: "🎉 Invoice Paid in Full: {$invoice->invoice_number}",
                    message: "Invoice {$invoice->invoice_number} for {$customerName} (₹{$amountFormatted}) has been settled in full.",
                    actionUrl: $actionUrl,
                    module: 'sales',
                    type: 'invoice_paid',
                    iconClass: 'feather-dollar-sign'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'sales.invoice.paid',
                data: [
                    'doc_no'           => $invoice->invoice_number,
                    'customer_name'    => $customerName,
                    'amount'           => $amountFormatted,
                    'payment_date'     => now()->format('d M Y, h:i A'),
                    'assigned_user_id' => $salesPersonId,
                    'exclude_user_ids' => !empty($salesPersonId) ? [$salesPersonId] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $invoice
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.invoice.paid): " . $e->getMessage());
        }
    }

    /**
     * 10. Trigger when a Customer Payment receipt is recorded.
     */
    public static function notifyPaymentReceived(CustomerPayment $payment): void
    {
        try {
            $customerName = $payment->customer?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($payment->amount ?? 0), 2);
            $method = $payment->payment_method ?: 'Bank / Cash';
            $actionUrl = route('sales.payments.show', $payment->id);

            NotificationRuleService::trigger(
                eventKey: 'sales.payment.received',
                data: [
                    'doc_no'           => $payment->payment_number,
                    'customer_name'    => $customerName,
                    'amount'           => $amountFormatted,
                    'payment_method'   => $method,
                    'date'             => now()->format('d M Y, h:i A'),
                ],
                actionUrl: $actionUrl,
                subjectModel: $payment
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.payment.received): " . $e->getMessage());
        }
    }

    /**
     * 11. Trigger when a Sales Return request is logged.
     */
    public static function notifyReturnCreated(SalesReturn $return): void
    {
        try {
            $customerName = $return->customer?->name ?: 'Customer';
            $reason = $return->reason ?: 'Customer Return';
            $amountFormatted = number_format((float) ($return->total_refund_amount ?? 0), 2);
            $actionUrl = route('sales.returns.show', $return->id);

            NotificationRuleService::trigger(
                eventKey: 'sales.return.created',
                data: [
                    'doc_no'           => $return->return_number,
                    'customer_name'    => $customerName,
                    'reason'           => $reason,
                    'amount'           => $amountFormatted,
                    'date'             => now()->format('d M Y, h:i A'),
                ],
                actionUrl: $actionUrl,
                subjectModel: $return
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.return.created): " . $e->getMessage());
        }
    }

    /**
     * 12. Trigger when a Sales Return is approved and stock restored.
     */
    public static function notifyReturnApproved(SalesReturn $return): void
    {
        try {
            $customerName = $return->customer?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($return->total_refund_amount ?? 0), 2);
            $approvedBy = auth()->user()?->name ?: 'Management';
            $actionUrl = route('sales.returns.show', $return->id);

            NotificationRuleService::trigger(
                eventKey: 'sales.return.approved',
                data: [
                    'doc_no'           => $return->return_number,
                    'customer_name'    => $customerName,
                    'amount'           => $amountFormatted,
                    'approved_by'      => $approvedBy,
                    'date'             => now()->format('d M Y, h:i A'),
                ],
                actionUrl: $actionUrl,
                subjectModel: $return
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.return.approved): " . $e->getMessage());
        }
    }

    /**
     * 13. Trigger when E-Invoice IRN is generated.
     */
    public static function notifyEInvoiceGenerated(Invoice $invoice): void
    {
        try {
            $customerName = $invoice->customer?->name ?: 'Customer';
            $ackNo = $invoice->ack_no ?: 'Success';
            $irn = $invoice->irn ?: '';
            $actionUrl = route('sales.invoices.show', $invoice->id);

            NotificationRuleService::trigger(
                eventKey: 'sales.einvoice.generated',
                data: [
                    'doc_no'           => $invoice->invoice_number,
                    'customer_name'    => $customerName,
                    'ack_no'           => $ackNo,
                    'irn'              => $irn,
                ],
                actionUrl: $actionUrl,
                subjectModel: $invoice
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.einvoice.generated): " . $e->getMessage());
        }
    }

    /**
     * 14. Trigger when E-Way Bill is generated.
     */
    public static function notifyEWayBillGenerated(DispatchOrder|Invoice $document, string $ewayBillNo, ?string $validUntil = null): void
    {
        try {
            $docNo = $document instanceof DispatchOrder ? $document->dispatch_number : $document->invoice_number;
            $customerName = $document->customer?->name ?: 'Customer';
            $actionUrl = $document instanceof DispatchOrder ? route('sales.dispatches.show', $document->id) : route('sales.invoices.show', $document->id);

            NotificationRuleService::trigger(
                eventKey: 'sales.ewaybill.generated',
                data: [
                    'doc_no'           => $docNo,
                    'eway_bill_no'     => $ewayBillNo,
                    'valid_until'      => $validUntil ?: 'Standard validity',
                    'customer_name'    => $customerName,
                ],
                actionUrl: $actionUrl,
                subjectModel: $document
            );
        } catch (\Throwable $e) {
            Log::warning("Sales Notification failed (sales.ewaybill.generated): " . $e->getMessage());
        }
    }
}

