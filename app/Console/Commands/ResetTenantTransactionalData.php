<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dev/testing utility: wipes Sales, Purchase, Accounting (journals/vouchers) and the
 * Inventory movements they caused for one tenant+company, leaving master/config data
 * (Chart of Accounts, Fiscal Years, Periods, Cost Centers, Tax Rates, Customers, Vendors,
 * Products, Warehouses) untouched.
 */
class ResetTenantTransactionalData extends Command
{
    protected $signature = 'tenant:reset-transactions
        {tenantId : Tenant ID to scope the wipe to}
        {companyId : Company ID to scope the wipe to}
        {--dry-run : Only show row counts that would be deleted, no changes made}';

    protected $description = 'Delete Sales/Purchase/Accounting transactional data (and related stock movements) for one tenant+company';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenantId');
        $companyId = (int) $this->argument('companyId');
        $dryRun = (bool) $this->option('dry-run');

        $company = DB::table('companies')->where('id', $companyId)->where('tenant_id', $tenantId)->first();
        if (! $company) {
            $this->error("No company {$companyId} found for tenant {$tenantId}.");
            return self::FAILURE;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Scoping to tenant_id={$tenantId} company_id={$companyId} ({$company->company_name})");

        $scope = fn ($table) => DB::table($table)->where('tenant_id', $tenantId)->where('company_id', $companyId);

        // Order matters: children before parents.
        $plan = [
            // Sales
            'payment_allocations' => fn () => $scope('payment_allocations'),
            'customer_payments' => fn () => $scope('customer_payments'),
            'sales_return_items' => fn () => $scope('sales_return_items'),
            'sales_returns' => fn () => $scope('sales_returns'),
            'dispatch_order_items' => fn () => $scope('dispatch_order_items'),
            'dispatch_orders' => fn () => $scope('dispatch_orders'),
            'invoice_items' => fn () => $scope('invoice_items'),
            'invoices' => fn () => $scope('invoices'),
            'material_requirement_items' => fn () => $scope('material_requirement_items'),
            'material_requirements' => fn () => $scope('material_requirements'),
            'sales_order_items' => fn () => $scope('sales_order_items'),
            'sales_orders' => fn () => $scope('sales_orders'),

            // Purchase
            'landed_cost_expenses' => fn () => $scope('landed_cost_expenses'),
            'landed_cost_items' => fn () => $scope('landed_cost_items'),
            'landed_cost_receipts' => fn () => $scope('landed_cost_receipts'),
            'landed_cost_vouchers' => fn () => $scope('landed_cost_vouchers'),
            'vendor_payment_allocations' => fn () => $scope('vendor_payment_allocations'),
            'vendor_payments' => fn () => $scope('vendor_payments'),
            'purchase_return_items' => fn () => $scope('purchase_return_items'),
            'purchase_returns' => fn () => $scope('purchase_returns'),
            'vendor_bill_items' => fn () => $scope('vendor_bill_items'),
            'vendor_bills' => fn () => $scope('vendor_bills'),
            'goods_receipt_note_items' => fn () => $scope('goods_receipt_note_items'),
            'goods_receipt_notes' => fn () => $scope('goods_receipt_notes'),
            'purchase_advance_payments' => fn () => $scope('purchase_advance_payments'),
            'purchase_order_items' => fn () => $scope('purchase_order_items'),
            'purchase_orders' => fn () => $scope('purchase_orders'),
            'purchase_rfq_vendor_rates' => fn () => $scope('purchase_rfq_vendor_rates'),
            'purchase_rfq_vendors' => fn () => $scope('purchase_rfq_vendors'),
            'purchase_rfq_items' => fn () => $scope('purchase_rfq_items'),
            'purchase_rfqs' => fn () => $scope('purchase_rfqs'),
            'purchase_requisition_items' => fn () => $scope('purchase_requisition_items'),
            'purchase_requisitions' => fn () => $scope('purchase_requisitions'),
            'approval_reminders' => fn () => $scope('approval_reminders')
                ->whereIn('remindable_type', [
                    \App\Domains\Purchase\Models\PurchaseOrder::class,
                    \App\Domains\Purchase\Models\PurchaseRequisition::class,
                ]),

            // Inventory movements caused by the above (identified by reference_type; manual
            // Stock Adjustment / Transfer / Opening Stock / Manufacturing rows are left alone)
            'stock_reservations' => fn () => $scope('stock_reservations')
                ->whereIn('reference_type', ['DispatchOrder', 'Sales Order']),
            'serial_numbers' => fn () => DB::table('serial_numbers')
                ->where('tenant_id', $tenantId)->where('company_id', $companyId)
                ->where(function ($q) use ($tenantId, $companyId) {
                    $ids = $this->stockTransactionIdsToDelete($tenantId, $companyId);
                    $q->whereIn('stock_transaction_id_in', $ids)
                        ->orWhereIn('stock_transaction_id_out', $ids);
                }),
            'stock_transactions' => fn () => $scope('stock_transactions')
                ->whereIn('reference_type', ['DispatchOrder', 'Purchase Receipt', 'PurchaseOrder', 'GoodsReceiptNote', 'GRN', 'SalesOrder', 'SalesReturn', 'MaterialRequirement', 'DeliveryOrder']),

            // Accounting
            'journal_entries' => fn () => DB::table('journal_entries')
                ->whereIn('journal_id', $this->journalIdsToDelete($tenantId, $companyId)),
            'voucher_details' => fn () => DB::table('voucher_details')
                ->whereIn('journal_id', $this->journalIdsToDelete($tenantId, $companyId)),
            'journals' => fn () => $scope('journals'),
            'accounting_posting_failures' => fn () => $scope('accounting_posting_failures'),
        ];

        $counts = [];
        foreach ($plan as $label => $queryFactory) {
            $counts[$label] = $queryFactory()->count();
        }

        $this->table(['Table', 'Rows to delete'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values());

        if ($dryRun) {
            $this->comment('Dry run only — nothing deleted.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Proceed with permanent deletion of the rows above?', false)) {
            $this->comment('Aborted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenantId, $companyId, $scope) {
            // Null self-referencing reversal links before deleting journals.
            $scope('journals')->update(['reversed_journal_id' => null]);

            DB::table('journal_entries')->whereIn('journal_id', $this->journalIdsToDelete($tenantId, $companyId))->delete();
            DB::table('voucher_details')->whereIn('journal_id', $this->journalIdsToDelete($tenantId, $companyId))->delete();

            $ordered = [
                'payment_allocations', 'customer_payments',
                'sales_return_items', 'sales_returns',
                'dispatch_order_items', 'dispatch_orders',
                'invoice_items', 'invoices',
                'material_requirement_items', 'material_requirements',
                'sales_order_items', 'sales_orders',
                'landed_cost_expenses', 'landed_cost_items', 'landed_cost_receipts', 'landed_cost_vouchers',
                'vendor_payment_allocations', 'vendor_payments',
                'purchase_return_items', 'purchase_returns',
                'vendor_bill_items', 'vendor_bills',
                'goods_receipt_note_items', 'goods_receipt_notes',
                'purchase_advance_payments',
                'purchase_order_items', 'purchase_orders',
                'purchase_rfq_vendor_rates', 'purchase_rfq_vendors', 'purchase_rfq_items', 'purchase_rfqs',
                'purchase_requisition_items', 'purchase_requisitions',
            ];
            foreach ($ordered as $table) {
                $scope($table)->delete();
            }

            DB::table('approval_reminders')
                ->where('tenant_id', $tenantId)->where('company_id', $companyId)
                ->whereIn('remindable_type', [
                    \App\Domains\Purchase\Models\PurchaseOrder::class,
                    \App\Domains\Purchase\Models\PurchaseRequisition::class,
                ])->delete();

            $stIds = $this->stockTransactionIdsToDelete($tenantId, $companyId);
            DB::table('serial_numbers')
                ->where('tenant_id', $tenantId)->where('company_id', $companyId)
                ->where(function ($q) use ($stIds) {
                    $q->whereIn('stock_transaction_id_in', $stIds)
                        ->orWhereIn('stock_transaction_id_out', $stIds);
                })->delete();

            $scope('stock_reservations')->whereIn('reference_type', ['DispatchOrder', 'Sales Order'])->delete();
            $scope('stock_transactions')->whereIn('reference_type', ['DispatchOrder', 'Purchase Receipt', 'PurchaseOrder', 'GoodsReceiptNote', 'GRN', 'SalesOrder', 'SalesReturn', 'MaterialRequirement', 'DeliveryOrder'])->delete();

            $scope('journals')->delete();
            $scope('accounting_posting_failures')->delete();

            $this->recomputeStockBalances($tenantId, $companyId);
        });

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function journalIdsToDelete(int $tenantId, int $companyId): array
    {
        return DB::table('journals')->where('tenant_id', $tenantId)->where('company_id', $companyId)->pluck('id')->all();
    }

    private function stockTransactionIdsToDelete(int $tenantId, int $companyId): array
    {
        return DB::table('stock_transactions')
            ->where('tenant_id', $tenantId)->where('company_id', $companyId)
            ->whereIn('reference_type', ['DispatchOrder', 'Purchase Receipt', 'PurchaseOrder', 'GoodsReceiptNote', 'GRN', 'SalesOrder', 'SalesReturn', 'MaterialRequirement', 'DeliveryOrder'])
            ->pluck('id')->all();
    }

    private function recomputeStockBalances(int $tenantId, int $companyId): void
    {
        $rows = DB::table('product_warehouse_stocks')
            ->where('tenant_id', $tenantId)->where('company_id', $companyId)
            ->get(['id', 'product_id', 'warehouse_id']);

        foreach ($rows as $row) {
            $remaining = DB::table('stock_transactions')
                ->where('tenant_id', $tenantId)->where('company_id', $companyId)
                ->where('product_id', $row->product_id)->where('warehouse_id', $row->warehouse_id)
                ->selectRaw("SUM(CASE WHEN type = 'IN' THEN quantity ELSE -quantity END) as qty")
                ->value('qty') ?? 0;

            $reserved = DB::table('stock_reservations')
                ->where('tenant_id', $tenantId)->where('company_id', $companyId)
                ->where('product_id', $row->product_id)->where('warehouse_id', $row->warehouse_id)
                ->where('status', 'active')
                ->sum('reserved_qty');

            DB::table('product_warehouse_stocks')->where('id', $row->id)->update([
                'quantity' => $remaining,
                'reserved_qty' => $reserved,
                'available_qty' => $remaining - $reserved,
                'updated_at' => now(),
            ]);
        }
    }
}
