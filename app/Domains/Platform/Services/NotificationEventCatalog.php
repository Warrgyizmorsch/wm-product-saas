<?php

namespace App\Domains\Platform\Services;

class NotificationEventCatalog
{
    /**
     * Get all supported ERP Events grouped by module.
     */
    public static function getEvents(): array
    {
        return [
            'crm' => [
                'label' => 'CRM & Lead Management',
                'events' => [
                    'crm.lead.created' => [
                        'label' => 'New Lead Created',
                        'description' => 'Triggered when a new lead is captured or imported.',
                        'icon' => 'feather-user-plus',
                        'default_roles' => ['Sales Manager', 'CRM Executive'],
                        'default_title' => 'New Lead Created: {{company_name}}',
                        'default_body' => 'Lead {{company_name}} (Contact: {{contact_person}}) created with source {{source}}.',
                        'action_route' => 'crm.leads.index',
                        'variables' => ['company_name', 'contact_person', 'source', 'expected_amount', 'created_by'],
                    ],
                    'crm.deal.won' => [
                        'label' => 'Deal Won',
                        'description' => 'Triggered when a sales deal is successfully marked as Won.',
                        'icon' => 'feather-award',
                        'default_roles' => ['Sales Manager', 'Sales Head', 'Accountant'],
                        'default_title' => '🎉 Deal Won: {{deal_title}}',
                        'default_body' => 'Deal {{deal_title}} for {{customer_name}} amounting to {{amount}} has been marked as WON.',
                        'action_route' => 'crm.deals.index',
                        'variables' => ['deal_title', 'customer_name', 'amount', 'won_by'],
                    ],
                    'crm.quotation.created' => [
                        'label' => 'CRM Quotation Created',
                        'description' => 'Triggered when a new quotation is issued to a lead or customer.',
                        'icon' => 'feather-file',
                        'default_roles' => ['Sales Manager'],
                        'default_title' => 'Quotation Created: {{quotation_no}}',
                        'default_body' => 'Quotation {{quotation_no}} for {{customer_name}} amounting to {{amount}} has been created.',
                        'action_route' => 'crm.quotations.index',
                        'variables' => ['quotation_no', 'customer_name', 'amount', 'created_by'],
                    ],
                ],
            ],
            'sales' => [
                'label' => 'Sales & Revenue',
                'events' => [
                    'sales.order.created' => [
                        'label' => 'Sales Order Created',
                        'description' => 'Triggered when a draft/new sales order is created.',
                        'icon' => 'feather-shopping-bag',
                        'default_roles' => ['Sales Manager', 'Store Keeper'],
                        'default_title' => 'New Sales Order Created: {{doc_no}}',
                        'default_body' => 'Sales Order {{doc_no}} for {{customer_name}} amounting to {{amount}} has been created.',
                        'action_route' => 'sales.orders.index',
                        'variables' => ['doc_no', 'customer_name', 'amount', 'created_by', 'date'],
                    ],
                    'sales.order.confirmed' => [
                        'label' => 'Sales Order Confirmed',
                        'description' => 'Triggered when a sales order is confirmed for fulfillment.',
                        'icon' => 'feather-check-circle',
                        'default_roles' => ['Store Manager', 'Warehouse Supervisor', 'Accountant'],
                        'default_title' => 'Sales Order Confirmed: {{doc_no}}',
                        'default_body' => 'Sales Order {{doc_no}} for {{customer_name}} ({{amount}}) is confirmed. Ready for material check & dispatch.',
                        'action_route' => 'sales.orders.index',
                        'variables' => ['doc_no', 'customer_name', 'amount', 'confirmed_by', 'date'],
                    ],
                    'sales.invoice.created' => [
                        'label' => 'Sales Invoice Generated',
                        'description' => 'Triggered when a sales invoice is posted/generated.',
                        'icon' => 'feather-file-text',
                        'default_roles' => ['Accountant', 'Sales Head'],
                        'default_title' => 'Invoice Generated: {{doc_no}}',
                        'default_body' => 'Invoice {{doc_no}} for {{customer_name}} with amount {{amount}} has been generated. Due: {{due_date}}.',
                        'action_route' => 'sales.invoices.index',
                        'variables' => ['doc_no', 'customer_name', 'amount', 'due_date', 'created_by'],
                    ],
                    'sales.payment.received' => [
                        'label' => 'Customer Payment Received',
                        'description' => 'Triggered when a customer payment receipt is recorded.',
                        'icon' => 'feather-dollar-sign',
                        'default_roles' => ['Accountant', 'Sales Manager'],
                        'default_title' => 'Payment Received: {{amount}} from {{customer_name}}',
                        'default_body' => 'Payment receipt {{doc_no}} of {{amount}} received from {{customer_name}} via {{payment_method}}.',
                        'action_route' => 'sales.payments.index',
                        'variables' => ['doc_no', 'customer_name', 'amount', 'payment_method', 'date'],
                    ],
                    'sales.dispatch.shipped' => [
                        'label' => 'Dispatch Order Shipped',
                        'description' => 'Triggered when a dispatch order leaves warehouse.',
                        'icon' => 'feather-truck',
                        'default_roles' => ['Sales Manager', 'Customer Support'],
                        'default_title' => 'Order Dispatched: {{doc_no}}',
                        'default_body' => 'Dispatch {{doc_no}} for {{customer_name}} has been shipped via {{transporter}} (LR: {{tracking_no}}).',
                        'action_route' => 'inventory.dispatches.index',
                        'variables' => ['doc_no', 'customer_name', 'transporter', 'tracking_no', 'date'],
                    ],
                    'sales.return.created' => [
                        'label' => 'Sales Return Requested',
                        'description' => 'Triggered when a customer sales return is submitted.',
                        'icon' => 'feather-rotate-ccw',
                        'default_roles' => ['Store Manager', 'Quality Inspector', 'Accountant'],
                        'default_title' => 'Sales Return Logged: {{doc_no}}',
                        'default_body' => 'Sales Return {{doc_no}} from {{customer_name}} for reason: {{reason}} is awaiting inspection.',
                        'action_route' => 'sales.returns.index',
                        'variables' => ['doc_no', 'customer_name', 'reason', 'date'],
                    ],
                ],
            ],
            'inventory' => [
                'label' => 'Store & Inventory',
                'events' => [
                    'inventory.stock.low' => [
                        'label' => 'Low Stock Alert (Below Reorder Point)',
                        'description' => 'Triggered when an item stock drops below its reorder point.',
                        'icon' => 'feather-alert-triangle',
                        'default_roles' => ['Store Manager', 'Purchase Manager'],
                        'default_title' => '⚠️ Low Stock Alert: {{item_name}}',
                        'default_body' => 'Item {{item_name}} (SKU: {{sku}}) is below reorder level. Current Stock: {{current_stock}} {{uom}}, Reorder Level: {{reorder_point}} {{uom}}.',
                        'action_route' => 'inventory.products.index',
                        'variables' => ['item_name', 'sku', 'current_stock', 'reorder_point', 'uom'],
                    ],
                    'inventory.transfer.dispatched' => [
                        'label' => 'Stock Transfer Dispatched',
                        'description' => 'Triggered when an inter-warehouse stock transfer is shipped.',
                        'icon' => 'feather-repeat',
                        'default_roles' => ['Store Manager', 'Warehouse Supervisor'],
                        'default_title' => 'Stock Transfer Dispatched: {{transfer_no}}',
                        'default_body' => 'Stock Transfer {{transfer_no}} dispatched from {{from_warehouse}} to {{to_warehouse}}.',
                        'action_route' => 'inventory.transfers.index',
                        'variables' => ['transfer_no', 'from_warehouse', 'to_warehouse', 'items_count'],
                    ],
                    'inventory.transfer.received' => [
                        'label' => 'Stock Transfer Received',
                        'description' => 'Triggered when destination warehouse receives transferred stock.',
                        'icon' => 'feather-check-square',
                        'default_roles' => ['Store Manager'],
                        'default_title' => 'Stock Transfer Received: {{transfer_no}}',
                        'default_body' => 'Stock Transfer {{transfer_no}} has been fully received at {{to_warehouse}} by {{received_by}}.',
                        'action_route' => 'inventory.transfers.index',
                        'variables' => ['transfer_no', 'from_warehouse', 'to_warehouse', 'received_by'],
                    ],
                    'inventory.adjustment.pending' => [
                        'label' => 'Stock Adjustment Approval Pending',
                        'description' => 'Triggered when a physical inventory count adjustment needs approval.',
                        'icon' => 'feather-sliders',
                        'default_roles' => ['Store Manager', 'Admin'],
                        'default_title' => 'Stock Adjustment Approval Required: {{doc_no}}',
                        'default_body' => 'Stock Adjustment {{doc_no}} for warehouse {{warehouse}} submitted by {{created_by}} is pending approval.',
                        'action_route' => 'inventory.adjustments.index',
                        'variables' => ['doc_no', 'warehouse', 'reason', 'created_by'],
                    ],
                    'inventory.batch.expiring' => [
                        'label' => 'Batch Expiring Alert (FEFO)',
                        'description' => 'Triggered when a stock batch is nearing expiration.',
                        'icon' => 'feather-clock',
                        'default_roles' => ['Store Manager', 'Quality Manager'],
                        'default_title' => '⏰ Batch Expiring Soon: {{item_name}} (Batch: {{batch_no}})',
                        'default_body' => 'Batch {{batch_no}} of {{item_name}} (Qty: {{quantity}}) will expire on {{expiry_date}}.',
                        'action_route' => 'inventory.batches.index',
                        'variables' => ['item_name', 'batch_no', 'expiry_date', 'quantity'],
                    ],
                ],
            ],
            'purchase' => [
                'label' => 'Purchase & Procurement',
                'events' => [
                    'purchase.order.created' => [
                        'label' => 'Purchase Order Created',
                        'description' => 'Triggered when a new Purchase Order is created.',
                        'icon' => 'feather-file-plus',
                        'default_roles' => ['Purchase Head', 'Finance Manager'],
                        'default_title' => 'PO Created: {{po_no}} for {{vendor_name}}',
                        'default_body' => 'Purchase Order {{po_no}} for vendor {{vendor_name}} with amount {{amount}} has been created.',
                        'action_route' => 'purchase.orders.index',
                        'variables' => ['po_no', 'vendor_name', 'amount', 'created_by'],
                    ],
                    'purchase.order.approved' => [
                        'label' => 'Purchase Order Approved',
                        'description' => 'Triggered when a PO receives management approval.',
                        'icon' => 'feather-check-circle',
                        'default_roles' => ['Purchase Executive', 'Store Keeper'],
                        'default_title' => 'PO Approved: {{po_no}}',
                        'default_body' => 'PO {{po_no}} for {{vendor_name}} ({{amount}}) has been approved by {{approved_by}}.',
                        'action_route' => 'purchase.orders.index',
                        'variables' => ['po_no', 'vendor_name', 'amount', 'approved_by'],
                    ],
                    'purchase.grn.received' => [
                        'label' => 'Goods Receipt Note (GRN) Completed',
                        'description' => 'Triggered when vendor goods arrive at warehouse and GRN is posted.',
                        'icon' => 'feather-package',
                        'default_roles' => ['Purchase Executive', 'Accountant'],
                        'default_title' => 'GRN Inward Completed: {{grn_no}}',
                        'default_body' => 'Goods Receipt {{grn_no}} for PO {{po_no}} from vendor {{vendor_name}} received at warehouse {{warehouse}}.',
                        'action_route' => 'grns.index',
                        'variables' => ['grn_no', 'vendor_name', 'warehouse', 'po_no'],
                    ],
                    'purchase.bill.created' => [
                        'label' => 'Vendor Bill Logged',
                        'description' => 'Triggered when a vendor bill/invoice is recorded for payment.',
                        'icon' => 'feather-credit-card',
                        'default_roles' => ['Accountant', 'Finance Head'],
                        'default_title' => 'Vendor Bill Logged: {{bill_no}}',
                        'default_body' => 'Vendor Bill {{bill_no}} for {{vendor_name}} ({{amount}}) logged. Payment Due: {{due_date}}.',
                        'action_route' => 'purchase.bills.index',
                        'variables' => ['bill_no', 'vendor_name', 'amount', 'due_date'],
                    ],
                ],
            ],
            'production' => [
                'label' => 'Production & Manufacturing',
                'events' => [
                    'production.order.created' => [
                        'label' => 'Production Order Created',
                        'description' => 'Triggered when a manufacturing production order is released.',
                        'icon' => 'feather-layers',
                        'default_roles' => ['Plant Head', 'Production Supervisor', 'Store Manager'],
                        'default_title' => 'Production Order Released: {{order_no}}',
                        'default_body' => 'Production Order {{order_no}} for {{product_name}} (Qty: {{quantity}}) released. Target: {{target_date}}.',
                        'action_route' => 'production.orders.index',
                        'variables' => ['order_no', 'product_name', 'quantity', 'target_date'],
                    ],
                    'production.job.completed' => [
                        'label' => 'Production Job Completed',
                        'description' => 'Triggered when a job card/work order is finished.',
                        'icon' => 'feather-check',
                        'default_roles' => ['Production Manager', 'Store Manager'],
                        'default_title' => 'Job Card Finished: {{job_no}}',
                        'default_body' => 'Job {{job_no}} for {{product_name}} completed by {{operator_name}}. Output: {{produced_qty}}.',
                        'action_route' => 'production.orders.index',
                        'variables' => ['job_no', 'product_name', 'produced_qty', 'operator_name'],
                    ],
                    'production.qc.failed' => [
                        'label' => 'Quality Inspection Failed (NCR)',
                        'description' => 'Triggered when a QC inspection fails and creates an NCR.',
                        'icon' => 'feather-alert-octagon',
                        'default_roles' => ['Quality Head', 'Plant Manager'],
                        'default_title' => '🔴 QC Inspection Failed: {{inspection_no}}',
                        'default_body' => 'QC Inspection {{inspection_no}} failed for {{item_name}}. Defect: {{defect_type}}, Rejected Qty: {{rejected_qty}}.',
                        'action_route' => 'production.ncrs.index',
                        'variables' => ['inspection_no', 'item_name', 'defect_type', 'rejected_qty'],
                    ],
                ],
            ],
            'hrms' => [
                'label' => 'HRMS & Employee Self-Service',
                'events' => [
                    'hrms.leave.applied' => [
                        'label' => 'Leave Application Submitted',
                        'description' => 'Triggered when an employee applies for leave.',
                        'icon' => 'feather-calendar',
                        'default_roles' => ['HR Manager', 'Department Head'],
                        'default_title' => 'Leave Application: {{employee_name}}',
                        'default_body' => '{{employee_name}} applied for {{days}} day(s) {{leave_type}} from {{from_date}} to {{to_date}}.',
                        'action_route' => 'hrms.leaves.index',
                        'variables' => ['employee_name', 'leave_type', 'from_date', 'to_date', 'days'],
                    ],
                    'hrms.expense.submitted' => [
                        'label' => 'Expense Claim Submitted',
                        'description' => 'Triggered when an employee files a travel/expense claim.',
                        'icon' => 'feather-navigation',
                        'default_roles' => ['HR Manager', 'Accountant'],
                        'default_title' => 'Expense Claim: {{claim_no}} by {{employee_name}}',
                        'default_body' => 'Expense Claim {{claim_no}} for {{purpose}} (Amount: {{amount}}) submitted by {{employee_name}}.',
                        'action_route' => 'hrms.travel-expense.index',
                        'variables' => ['claim_no', 'employee_name', 'amount', 'purpose'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Flat map of event_key => details
     */
    public static function getEventDetails(string $eventKey): ?array
    {
        foreach (self::getEvents() as $moduleKey => $module) {
            if (isset($module['events'][$eventKey])) {
                $details = $module['events'][$eventKey];
                $details['module'] = $moduleKey;
                return $details;
            }
        }
        return null;
    }
}
