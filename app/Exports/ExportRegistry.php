<?php

namespace App\Exports;

class ExportRegistry
{
    /**
     * Get all available column definitions (key => Label) for a given export type.
     */
    public static function getColumnsForType(string $type): array
    {
        $normalizedType = str_replace('_', '-', $type);

        return match ($normalizedType) {
            'boms' => BomExport::availableColumns(),
            'routings' => RoutingExport::availableColumns(),
            'work-centers' => WorkCenterExport::availableColumns(),
            'machines' => MachineExport::availableColumns(),
            'orders' => ProductionOrderExport::availableColumns(),
            'plans' => ProductionPlanExport::availableColumns(),
            'wip' => ProductionWipExport::availableColumns(),
            'schedules' => ProductionScheduleExport::availableColumns(),
            'deals' => DealExport::availableColumns(),
            'accounts' => AccountExport::availableColumns(),
            'customers' => CustomerExport::availableColumns(),
            'invoices' => InvoiceExport::availableColumns(),
            'sales-orders' => SalesOrderExport::availableColumns(),
            'quotations' => QuotationExport::availableColumns(),
            'dispatches' => DispatchOrderExport::availableColumns(),
            'customer-payments' => CustomerPaymentExport::availableColumns(),
            'sales-returns' => SalesReturnExport::availableColumns(),
            // Purchase
            'vendors' => VendorExport::availableColumns(),
            'purchase-requisitions' => PurchaseRequisitionExport::availableColumns(),
            'purchase-rfqs' => PurchaseRfqExport::availableColumns(),
            'purchase-orders' => PurchaseOrderExport::availableColumns(),
            'grns' => GoodsReceiptNoteExport::availableColumns(),
            'vendor-bills' => VendorBillExport::availableColumns(),
            'vendor-payments' => VendorPaymentExport::availableColumns(),
            'purchase-returns' => PurchaseReturnExport::availableColumns(),
            // Inventory
            'products' => ProductExport::availableColumns(),
            'warehouses' => WarehouseExport::availableColumns(),
            'stock-transfers' => StockTransferExport::availableColumns(),
            'stock-adjustments' => StockAdjustmentExport::availableColumns(),
            'stock-transactions' => StockTransactionExport::availableColumns(),
            'material-requests' => MaterialRequestExport::availableColumns(),
            'batches' => BatchExport::availableColumns(),
            default => [],
        };
    }

    /**
     * Get human-friendly title for the modal.
     */
    public static function getTitleForType(string $type): string
    {
        $normalizedType = str_replace('_', '-', $type);

        return match ($normalizedType) {
            'boms' => 'Bill of Materials (BOM)',
            'routings' => 'Production Routings',
            'work-centers' => 'Work Centers',
            'machines' => 'Production Machines',
            'orders' => 'Production Orders',
            'plans' => 'Production Plans',
            'wip' => 'Work-In-Progress (WIP)',
            'schedules' => 'Production Schedules',
            'deals' => 'CRM Deals & Opportunities',
            'accounts' => 'Company Accounts',
            'customers' => 'Customer Directory',
            'invoices' => 'Sales Invoices & E-Invoices',
            'sales-orders' => 'Sales Orders',
            'quotations' => 'Quotations & Proformas',
            'dispatches' => 'Dispatch Orders & Challans',
            'customer-payments' => 'Customer Payment Receipts',
            'sales-returns' => 'Sales Returns & Credit Notes',
            // Purchase
            'vendors' => 'Vendors & Suppliers Directory',
            'purchase-requisitions' => 'Purchase Requisitions (PR)',
            'purchase-rfqs' => 'Requests for Quotation (RFQ)',
            'purchase-orders' => 'Purchase Orders (PO)',
            'grns' => 'Goods Receipt Notes (GRN)',
            'vendor-bills' => 'Vendor Bills & Invoices',
            'vendor-payments' => 'Vendor Payment Vouchers',
            'purchase-returns' => 'Purchase Returns & Debit Notes',
            // Inventory
            'products' => 'Products & Item Master',
            'warehouses' => 'Warehouses & Locations',
            'stock-transfers' => 'Inter-Warehouse Stock Transfers',
            'stock-adjustments' => 'Physical Stock Adjustments',
            'stock-transactions' => 'Stock Movement Ledger',
            'material-requests' => 'Production Material Requisition Slips',
            'batches' => 'Batches & Expiry Tracking',
            default => ucfirst(str_replace('-', ' ', $type)),
        };
    }
}
