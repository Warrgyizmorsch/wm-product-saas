<?php

namespace App\Services;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces the FRS 2.3 requirement:
 * "Parties linked to transactions are never hard-deleted."
 *
 * Checks all financial, sales, purchasing, production, and CRM footprint
 * before any customer, vendor, or account can be deleted.
 */
class PartyDeletionGuard
{
    public function enabled(): bool
    {
        return (bool) config('party_verification.deletion_protection_enabled', true);
    }

    /**
     * Determine if a party (Customer, Vendor, or CrmAccount) has any linked
     * transactions, orders, billing, or operational history.
     */
    public function hasTransactions(Model $party): bool
    {
        return ! empty($this->transactionSummary($party));
    }

    /**
     * Returns an associative array of transaction types and counts attached to this party.
     *
     * @return array<string, int>
     */
    public function transactionSummary(Model $party): array
    {
        if ($party instanceof Customer) {
            return $this->customerTransactionSummary($party);
        }

        if ($party instanceof Vendor) {
            return $this->vendorTransactionSummary($party);
        }

        if ($party instanceof CrmAccount) {
            return $this->crmAccountTransactionSummary($party);
        }

        return [];
    }

    /**
     * @return array<string, int>
     */
    public function customerTransactionSummary(Customer $customer): array
    {
        $id = (int) $customer->id;
        $summary = [];

        $invoiceCount = Invoice::query()->withoutGlobalScopes()->where('customer_id', $id)->count();
        if ($invoiceCount > 0) {
            $summary['invoices'] = $invoiceCount;
        }

        $orderCount = SalesOrder::query()->withoutGlobalScopes()->where('customer_id', $id)->count();
        if ($orderCount > 0) {
            $summary['sales_orders'] = $orderCount;
        }

        $paymentCount = CustomerPayment::query()->withoutGlobalScopes()->where('customer_id', $id)->count();
        if ($paymentCount > 0) {
            $summary['payments'] = $paymentCount;
        }

        $returnCount = SalesReturn::query()->withoutGlobalScopes()->where('customer_id', $id)->count();
        if ($returnCount > 0) {
            $summary['sales_returns'] = $returnCount;
        }

        $dispatchCount = DispatchOrder::query()->withoutGlobalScopes()->where('customer_id', $id)->count();
        if ($dispatchCount > 0) {
            $summary['dispatch_orders'] = $dispatchCount;
        }

        // Quotations (direct customer link or through linked CRM account)
        $accountIds = CrmAccount::query()->withoutGlobalScopes()->where('customer_id', $id)->pluck('id');
        $quotationQuery = Quotation::query()->withoutGlobalScopes()->where(function ($q) use ($id, $accountIds) {
            $q->where('customer_id', $id);
            if ($accountIds->isNotEmpty()) {
                $q->orWhereIn('crm_account_id', $accountIds);
            }
        });
        $quoteCount = $quotationQuery->count();
        if ($quoteCount > 0) {
            $summary['quotations'] = $quoteCount;
        }

        if ($accountIds->isNotEmpty()) {
            $dealCount = CrmDeal::query()->withoutGlobalScopes()->whereIn('crm_account_id', $accountIds)->count();
            if ($dealCount > 0) {
                $summary['deals'] = $dealCount;
            }
        }

        if (abs((float) ($customer->opening_balance ?? 0)) > 0.001) {
            $summary['opening_balance'] = 1;
        }

        if (! empty($customer->merged_into_id)) {
            $summary['merged_into'] = 1;
        }

        $mergedSources = Customer::query()->withoutGlobalScopes()->where('merged_into_id', $id)->count();
        if ($mergedSources > 0) {
            $summary['merged_sources'] = $mergedSources;
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function vendorTransactionSummary(Vendor $vendor): array
    {
        $id = (int) $vendor->id;
        $summary = [];

        $poCount = PurchaseOrder::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($poCount > 0) {
            $summary['purchase_orders'] = $poCount;
        }

        $billCount = VendorBill::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($billCount > 0) {
            $summary['vendor_bills'] = $billCount;
        }

        $paymentCount = VendorPayment::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($paymentCount > 0) {
            $summary['vendor_payments'] = $paymentCount;
        }

        $advanceCount = PurchaseAdvancePayment::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($advanceCount > 0) {
            $summary['advance_payments'] = $advanceCount;
        }

        $grnCount = GoodsReceiptNote::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($grnCount > 0) {
            $summary['goods_receipt_notes'] = $grnCount;
        }

        $rfqCount = PurchaseRfqVendor::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($rfqCount > 0) {
            $summary['rfqs'] = $rfqCount;
        }

        $operationCount = ProductionOrderOperation::query()->withoutGlobalScopes()->where('vendor_id', $id)->count();
        if ($operationCount > 0) {
            $summary['production_operations'] = $operationCount;
        }

        if (abs((float) ($vendor->opening_balance ?? 0)) > 0.001) {
            $summary['opening_balance'] = 1;
        }

        if (! empty($vendor->merged_into_id)) {
            $summary['merged_into'] = 1;
        }

        $mergedSources = Vendor::query()->withoutGlobalScopes()->where('merged_into_id', $id)->count();
        if ($mergedSources > 0) {
            $summary['merged_sources'] = $mergedSources;
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function crmAccountTransactionSummary(CrmAccount $account): array
    {
        $summary = [];

        if ($account->customer_id && ($customer = $account->customer)) {
            $customerSummary = $this->customerTransactionSummary($customer);
            if (! empty($customerSummary)) {
                $summary['customer_transactions'] = array_sum($customerSummary);
            }
        }

        $id = (int) $account->id;
        $dealCount = CrmDeal::query()->withoutGlobalScopes()->where('crm_account_id', $id)->count();
        if ($dealCount > 0) {
            $summary['deals'] = $dealCount;
        }

        $quoteCount = Quotation::query()->withoutGlobalScopes()->where('crm_account_id', $id)->count();
        if ($quoteCount > 0) {
            $summary['quotations'] = $quoteCount;
        }

        return $summary;
    }

    /**
     * Human-readable blocked reason if deletion is forbidden.
     */
    public function blockedReason(Model $party): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $summary = $this->transactionSummary($party);
        if (empty($summary)) {
            return null;
        }

        $name = $party->name ?? ('Record #' . $party->getKey());
        $label = class_basename($party);

        $details = [];
        foreach ($summary as $key => $count) {
            $humanKey = str_replace('_', ' ', $key);
            $details[] = "{$count} {$humanKey}";
        }
        $detailList = implode(', ', $details);

        return "{$label} '{$name}' cannot be deleted because it is linked to active records ({$detailList}). Parties linked to transactions can never be hard-deleted. Please mark it as Inactive instead.";
    }

    /**
     * Asserts that a party can be safely deleted; throws PartyDeletionException if not.
     *
     * @throws PartyDeletionException
     */
    public function assertCanDelete(Model $party): void
    {
        $reason = $this->blockedReason($party);
        if ($reason !== null) {
            throw new PartyDeletionException($reason);
        }
    }
}
