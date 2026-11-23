<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Services\CustomerOpeningBalanceService;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Projects\Models\Project;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorDocument;
use App\Domains\Purchase\Models\VendorItem;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\BillSettlement;
use App\Domains\Accounting\Models\JournalEntry;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Models\PartyVerificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * FRS 2.3 / Task C-18: Party Merge Tool.
 *
 * Safely merges duplicate customer or vendor records into a primary surviving record
 * in a single atomic database transaction. All operational documents, orders, invoices,
 * payments, branches, contacts, CRM opportunities, and accounting ledgers are re-parented
 * to the target party without breaking tax snapshots, E-Invoice IRNs, or audit trails.
 *
 * The duplicate source record is permanently archived as 'inactive' with reason 'duplicate',
 * marked with merged_into_id, and prohibited from creating new business via PartyTransactionGuard.
 */
class PartyMergeService
{
    public const CUSTOMER = 'customer';
    public const VENDOR = 'vendor';

    /**
     * Compute a pre-merge impact summary showing counts and values of all records
     * that will be moved from Source to Target.
     *
     * @return array<string, mixed>
     */
    public function preview(string $partyType, int $sourceId, int $targetId, ?int $tenantId = null): array
    {
        $tenantId ??= tenant_id() ?? current_tenant_id();

        [$source, $target] = $this->resolveAndValidateParties($partyType, $sourceId, $targetId, $tenantId);

        $counts = [
            'party_type' => $partyType,
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'gstin' => $source->gstin,
                'email' => $source->email,
                'phone' => $source->phone,
                'status' => $source->status,
                'opening_balance' => (float) ($source->opening_balance ?? 0),
            ],
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
                'gstin' => $target->gstin,
                'email' => $target->email,
                'phone' => $target->phone,
                'status' => $target->status,
                'opening_balance' => (float) ($target->opening_balance ?? 0),
            ],
            'branches_count' => 0,
            'contacts_count' => 0,
            'accounting_entries_count' => 0,
            'documents' => [],
        ];

        if (Schema::hasTable('party_branches')) {
            $counts['branches_count'] = PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $source->id)
                ->count();
        }

        if (Schema::hasTable('contacts')) {
            $counts['contacts_count'] = PartyContact::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $source->id)
                ->count();
        }

        if (Schema::hasTable('journal_entries')) {
            $counts['accounting_entries_count'] = JournalEntry::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $source->id)
                ->count();
        }

        if ($partyType === self::CUSTOMER) {
            $invoices = Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $source->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount, COALESCE(SUM(balance_due), 0) as balance_due')
                ->first();

            $orders = SalesOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $source->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')
                ->first();

            $payments = CustomerPayment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $source->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount')
                ->first();

            $dealsCount = 0;
            $quotationsCount = 0;
            $sourceAccountIds = CrmAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $source->id)
                ->pluck('id');

            if ($sourceAccountIds->isNotEmpty()) {
                $dealsCount = CrmDeal::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('crm_account_id', $sourceAccountIds)
                    ->count();

                $quotationsCount = Quotation::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('crm_account_id', $sourceAccountIds)
                    ->count();
            }

            $counts['documents'] = [
                'invoices_count' => (int) ($invoices->count ?? 0),
                'invoices_total' => (float) ($invoices->total_amount ?? 0),
                'invoices_open_balance' => (float) ($invoices->balance_due ?? 0),
                'sales_orders_count' => (int) ($orders->count ?? 0),
                'sales_orders_total' => (float) ($orders->total_amount ?? 0),
                'payments_count' => (int) ($payments->count ?? 0),
                'payments_total' => (float) ($payments->total_amount ?? 0),
                'deals_count' => $dealsCount,
                'quotations_count' => $quotationsCount,
            ];
        } else {
            $bills = VendorBill::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')
                ->first();

            $orders = PurchaseOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total_amount')
                ->first();

            $payments = VendorPayment::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total_amount')
                ->first();

            $grnsCount = GoodsReceiptNote::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->count();

            $counts['documents'] = [
                'bills_count' => (int) ($bills->count ?? 0),
                'bills_total' => (float) ($bills->total_amount ?? 0),
                'purchase_orders_count' => (int) ($orders->count ?? 0),
                'purchase_orders_total' => (float) ($orders->total_amount ?? 0),
                'payments_count' => (int) ($payments->count ?? 0),
                'payments_total' => (float) ($payments->total_amount ?? 0),
                'grns_count' => $grnsCount,
            ];
        }

        // Check for legal entity PAN/GSTIN alignment
        $sourcePan = $this->extractPan((string) ($source->gstin ?? ''), (string) ($source->pan ?? ''));
        $targetPan = $this->extractPan((string) ($target->gstin ?? ''), (string) ($target->pan ?? ''));
        $panMismatch = false;
        $mismatchWarning = null;

        if ($sourcePan !== '' && $targetPan !== '' && $sourcePan !== $targetPan) {
            $panMismatch = true;
            $mismatchWarning = "Legal entity mismatch: Source PAN ({$sourcePan}) differs from Target PAN ({$targetPan}). Merging will combine distinct legal entities.";
        }

        $counts['legal_entity_check'] = [
            'source_pan' => $sourcePan,
            'target_pan' => $targetPan,
            'pan_mismatch' => $panMismatch,
            'warning' => $mismatchWarning,
        ];

        return $counts;
    }

    /**
     * Extract 10-character PAN from PAN field or GSTIN.
     */
    private function extractPan(string $gstin, string $pan): string
    {
        $pan = strtoupper(trim($pan));
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            return $pan;
        }

        $gstin = strtoupper(trim($gstin));
        if (strlen($gstin) >= 12) {
            $candidate = substr($gstin, 2, 10);
            if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Execute the atomic merge of Source party into Target party.
     *
     * @param  array<string, mixed>  $options
     * @return array{source_id: int, target_id: int, moved_counts: array<string, int>}
     */
    public function merge(string $partyType, int $sourceId, int $targetId, User $user, array $options = []): array
    {
        $tenantId = (int) ($user->tenant_id ?? tenant_id() ?? 0);

        if ($tenantId === 0) {
            throw ValidationException::withMessages(['target_id' => 'The tenant could not be resolved for this merge.']);
        }

        return DB::transaction(function () use ($partyType, $sourceId, $targetId, $user, $tenantId, $options): array {
            // Lock both rows and validate inside the transaction so two merges cannot interleave.
            [$source, $target] = $this->resolveAndValidateParties($partyType, $sourceId, $targetId, $tenantId, true);

            $movedCounts = [];

            if ($partyType === self::CUSTOMER) {
                $movedCounts = $this->mergeCustomerRecords($source, $target, $tenantId);
            } else {
                $movedCounts = $this->mergeVendorRecords($source, $target, $tenantId);
            }

            // Re-parent multi-branch locations and unified contacts
            $movedCounts['branches'] = $this->reparentBranches($partyType, (int) $source->id, (int) $target->id, $tenantId);
            $movedCounts['contacts'] = $this->reparentContacts($partyType, (int) $source->id, (int) $target->id, $tenantId);

            // Re-parent Accounting double-entry records
            $movedCounts['accounting'] = $this->reparentAccounting($partyType, (int) $source->id, (int) $target->id, $tenantId);

            // Consolidate opening balance
            $this->consolidateOpeningBalance($source, $target);

            // Archive the source record permanently without firing saved hooks that would re-create HO branches
            $targetName = $target->name ?: (ucfirst($partyType) . " #{$target->id}");
            $sourceStatusBefore = $source->status;
            $reason = trim((string) ($options['reason'] ?? ''));
            $reasonSuffix = $reason !== '' ? " Reason: {$reason}" : '';
            $sourceModelClass = $partyType === self::CUSTOMER ? Customer::class : Vendor::class;
            $sourceModelClass::withoutEvents(function () use ($source, $target, $targetName, $user, $partyType, $reasonSuffix) {
                $source->update([
                    'status' => 'inactive',
                    'inactive_reason' => 'duplicate',
                    'inactive_note' => "Merged into " . ucfirst($partyType) . " #{$target->id} ({$targetName}) by {$user->name}.{$reasonSuffix}",
                    'inactivated_by' => $user->id,
                    'inactivated_at' => now(),
                    'merged_into_id' => $target->id,
                    'merged_at' => now(),
                    'merged_by' => $user->id,
                ]);
            });

            // Append immutable audit log entries (FRS 2.3)
            PartyVerificationLog::create([
                'tenant_id' => $tenantId,
                'party_type' => $partyType,
                'party_id' => $source->id,
                'action' => 'merged',
                'actor_id' => $user->id,
                'from_status' => $sourceStatusBefore,
                'to_status' => 'inactive',
                'remarks' => "Merged into " . ucfirst($partyType) . " #{$target->id} ({$targetName}).{$reasonSuffix}",
                'changes' => [
                    'merged_into_id' => $target->id,
                    'moved_records' => $movedCounts,
                ],
            ]);

            PartyVerificationLog::create([
                'tenant_id' => $tenantId,
                'party_type' => $partyType,
                'party_id' => $target->id,
                'action' => 'merged_from',
                'actor_id' => $user->id,
                'from_status' => $target->status,
                'to_status' => $target->status,
                'remarks' => "Adopted records and branches from duplicate " . ucfirst($partyType) . " #{$source->id} ({$source->name})",
                'changes' => [
                    'merged_from_id' => $source->id,
                    'moved_records' => $movedCounts,
                ],
            ]);

            // Invalidate caches
            Cache::forget("tenant_{$tenantId}_dup_graph");
            Cache::forget("tenant_{$tenantId}_lead_status_counts");

            return [
                'source_id' => (int) $source->id,
                'target_id' => (int) $target->id,
                'moved_counts' => $movedCounts,
            ];
        });
    }

    /**
     * Relink Customer transactions and CRM objects from source to target.
     *
     * @return array<string, int>
     */
    private function mergeCustomerRecords(Customer $source, Customer $target, int $tenantId): array
    {
        $counts = [];

        $counts['invoices'] = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $source->id)
            ->update(['customer_id' => $target->id]);

        $counts['sales_orders'] = SalesOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $source->id)
            ->update(['customer_id' => $target->id]);

        $counts['payments'] = CustomerPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $source->id)
            ->update(['customer_id' => $target->id]);

        $counts['dispatch_orders'] = DispatchOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $source->id)
            ->update(['customer_id' => $target->id]);

        $counts['sales_returns'] = SalesReturn::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $source->id)
            ->update(['customer_id' => $target->id]);

        if (Schema::hasTable('projects')) {
            $counts['projects'] = Project::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $source->id)
                ->update(['customer_id' => $target->id]);
        }

        if (Schema::hasTable('credit_notes')) {
            $counts['credit_notes'] = DB::table('credit_notes')
                ->where('tenant_id', $tenantId)
                ->where('customer_id', $source->id)
                ->update(['customer_id' => $target->id]);
        }

        // Re-parent CRM Deal, Quotation, and Lead associations across ALL source CRM accounts (FRS 2.3)
        $sourceAccounts = CrmAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $source->id)
            ->get();

        if ($sourceAccounts->isNotEmpty()) {
            $targetAccount = $target->crmAccount;

            if (! $targetAccount) {
                // Target has no CRM account: adopt the first source CRM account as the target's primary CRM account
                $primarySourceAccount = $sourceAccounts->shift();
                $primarySourceAccount->update(['customer_id' => $target->id]);
                $targetAccount = $primarySourceAccount;

                // Record existing deals, quotations, and leads from this account
                $counts['crm_deals'] = ($counts['crm_deals'] ?? 0) + CrmDeal::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_account_id', $targetAccount->id)
                    ->count();

                $counts['quotations'] = ($counts['quotations'] ?? 0) + Quotation::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_account_id', $targetAccount->id)
                    ->count();

                $counts['leads'] = ($counts['leads'] ?? 0) + Lead::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_account_id', $targetAccount->id)
                    ->count();
            }

            // Consolidate every remaining source CRM account into the target master account
            foreach ($sourceAccounts as $account) {
                if ($account->id === $targetAccount->id) {
                    continue;
                }

                $counts['crm_deals'] = ($counts['crm_deals'] ?? 0) + CrmDeal::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_account_id', $account->id)
                    ->update(['crm_account_id' => $targetAccount->id]);

                $counts['quotations'] = ($counts['quotations'] ?? 0) + Quotation::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_account_id', $account->id)
                    ->update(['crm_account_id' => $targetAccount->id]);

                $counts['leads'] = ($counts['leads'] ?? 0) + Lead::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('crm_account_id', $account->id)
                    ->update(['crm_account_id' => $targetAccount->id]);

                // Soft-delete redundant source CRM account to avoid duplicate active accounts
                $account->delete();
            }
        }

        return $counts;
    }

    /**
     * Relink Vendor procurement documents from source to target.
     *
     * @return array<string, int>
     */
    private function mergeVendorRecords(Vendor $source, Vendor $target, int $tenantId): array
    {
        $counts = [];

        $counts['vendor_bills'] = VendorBill::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['purchase_orders'] = PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['vendor_payments'] = VendorPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['advance_payments'] = PurchaseAdvancePayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['goods_receipt_notes'] = GoodsReceiptNote::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['purchase_returns'] = PurchaseReturn::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['purchase_rfq_vendors'] = PurchaseRfqVendor::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        if (Schema::hasTable('production_order_operations')) {
            $counts['production_operations'] = ProductionOrderOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->update(['vendor_id' => $target->id]);
        }

        if (Schema::hasTable('delivery_challans')) {
            $counts['delivery_challans'] = DeliveryChallan::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->update(['vendor_id' => $target->id]);
        }

        if (Schema::hasTable('debit_notes')) {
            $counts['debit_notes'] = DB::table('debit_notes')
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $source->id)
                ->update(['vendor_id' => $target->id]);
        }

        // Other tables that point at a vendor: RFQ quotes, landed-cost expenses, transporters,
        // assets and the product's preferred vendor.
        foreach ([
            'purchase_rfq_item_vendors' => 'vendor_id',
            'landed_cost_expenses' => 'vendor_id',
            'transporters' => 'vendor_id',
            'assets' => 'vendor_id',
            'products' => 'preferred_vendor_id',
        ] as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $counts[$table] = DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where($column, $source->id)
                    ->update([$column => $target->id]);
            }
        }

        // Vendor catalog and compliance documents (C-19) stay with the surviving vendor.
        $counts['vendor_documents'] = VendorDocument::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->update(['vendor_id' => $target->id]);

        $counts['vendor_items'] = $this->reparentVendorItems($source, $target, $tenantId);

        return $counts;
    }

    /**
     * Move the source vendor's supplied-item mappings to the target. (vendor, product) is unique:
     * where the target already supplies the same product its own mapping and rates win, and the
     * source's row stays behind (soft-deleted) with its price history. A deleted target mapping
     * is replaced by the source's live one.
     */
    private function reparentVendorItems(Vendor $source, Vendor $target, int $tenantId): int
    {
        $moved = 0;

        $sourceItems = VendorItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('vendor_id', $source->id)
            ->get();

        foreach ($sourceItems as $item) {
            if ($item->trashed()) {
                continue;
            }

            $existing = VendorItem::withoutGlobalScopes()->withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('vendor_id', $target->id)
                ->where('product_id', $item->product_id)
                ->first();

            if ($existing && ! $existing->trashed()) {
                $item->delete();

                continue;
            }

            $existing?->forceDelete();

            $item->update(['vendor_id' => $target->id]);
            $moved++;
        }

        return $moved;
    }

    /**
     * Adopt source branches under target party (FRS 2.3 / C-17).
     * Any Head Office branch from source is converted to branch_type='branch' and is_head_office=false,
     * ensuring target retains its own primary head office while preserving the duplicate's location.
     * The branch code is guaranteed unique under target to prevent key violation on party_branches_party_code_unique.
     */
    private function reparentBranches(string $partyType, int $sourceId, int $targetId, int $tenantId): int
    {
        if (! Schema::hasTable('party_branches')) {
            return 0;
        }

        $sourceBranches = PartyBranch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('party_type', $partyType)
            ->where('party_id', $sourceId)
            ->get();

        $count = 0;
        foreach ($sourceBranches as $branch) {
            $newCode = $this->uniqueBranchCodeForTarget($partyType, $targetId, $tenantId, (string) $branch->code);

            $update = [
                'party_id' => $targetId,
                'code' => $newCode,
                'is_head_office' => false,
                'is_default_billing' => false,
                'is_default_shipping' => false,
            ];

            if ($branch->is_head_office || $branch->branch_type === 'head_office') {
                $update['branch_type'] = 'branch';
                if ($branch->name === 'Head Office' || empty($branch->name)) {
                    $update['name'] = $branch->city ? "{$branch->city} Branch" : "Branch ({$newCode})";
                }
            }

            $branch->update($update);
            $count++;
        }

        return $count;
    }

    /**
     * Resolve a unique branch code for the target party to prevent 1062 duplicate key violation
     * on unique constraint: party_branches_party_code_unique [party_type, party_id, code].
     */
    private function uniqueBranchCodeForTarget(string $partyType, int $targetId, int $tenantId, string $currentCode): string
    {
        // 'HO' belongs solely to the target's primary head office
        $candidate = ($currentCode === 'HO' || $currentCode === '') ? null : $currentCode;

        if ($candidate !== null) {
            $exists = PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $targetId)
                ->where('code', $candidate)
                ->exists();
            if (! $exists) {
                return $candidate;
            }
        }

        // Generate next available BR-xx code under target
        $num = PartyBranch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('party_type', $partyType)
            ->where('party_id', $targetId)
            ->count() + 1;

        do {
            $code = 'BR-' . str_pad((string) $num, 2, '0', STR_PAD_LEFT);
            $exists = PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $targetId)
                ->where('code', $code)
                ->exists();
            $num++;
        } while ($exists);

        return $code;
    }

    /**
     * Move source contacts under target party (FRS 2.3 / C-17).
     * Primary flags are cleared if target already has an established primary contact.
     */
    private function reparentContacts(string $partyType, int $sourceId, int $targetId, int $tenantId): int
    {
        if (! Schema::hasTable('contacts')) {
            return 0;
        }

        $targetHasPrimary = PartyContact::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('party_type', $partyType)
            ->where('party_id', $targetId)
            ->where('is_primary', true)
            ->exists();

        $sourceContacts = PartyContact::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('party_type', $partyType)
            ->where('party_id', $sourceId)
            ->get();

        $count = 0;
        foreach ($sourceContacts as $contact) {
            $isPrimary = $contact->is_primary && ! $targetHasPrimary;
            if ($isPrimary) {
                $targetHasPrimary = true;
            }

            $contact->update([
                'party_id' => $targetId,
                'is_primary' => $isPrimary,
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Re-parent all double-entry journal entries, bill references, and settlements to Target.
     */
    private function reparentAccounting(string $partyType, int $sourceId, int $targetId, int $tenantId): int
    {
        $count = 0;

        if (Schema::hasTable('journal_entries')) {
            $count += JournalEntry::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $sourceId)
                ->update(['party_id' => $targetId]);
        }

        if (Schema::hasTable('bill_references')) {
            $count += BillReference::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $sourceId)
                ->update(['party_id' => $targetId]);
        }

        if (Schema::hasTable('bill_allocations')) {
            $count += BillAllocation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $sourceId)
                ->update(['party_id' => $targetId]);
        }

        if (Schema::hasTable('bill_settlements')) {
            $count += BillSettlement::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $sourceId)
                ->update(['party_id' => $targetId]);
        }

        return $count;
    }

    /**
     * Consolidate opening balance onto target, and clear from source.
     */
    private function consolidateOpeningBalance(Customer|Vendor $source, Customer|Vendor $target): void
    {
        $sourceSigned = $source->signedOpeningBalance();
        if (abs($sourceSigned) < 0.0001) {
            return;
        }

        $targetSigned = $target->signedOpeningBalance();
        $net = $targetSigned + $sourceSigned;

        $targetType = $target instanceof Customer
            ? ($net >= 0 ? 'debit' : 'credit')
            : ($net >= 0 ? 'credit' : 'debit');

        $target->update([
            'opening_balance' => abs($net),
            'opening_balance_type' => $targetType,
        ]);

        $source->update([
            'opening_balance' => 0.00,
        ]);

        if ($target instanceof Customer) {
            app(CustomerOpeningBalanceService::class)->trySync($target);
        }
    }

    /**
     * Resolve models and perform pre-flight validation.
     *
     * @return array{0: Customer|Vendor, 1: Customer|Vendor}
     */
    private function resolveAndValidateParties(string $partyType, int $sourceId, int $targetId, int $tenantId, bool $lock = false): array
    {
        if ($sourceId === $targetId) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot merge a party into itself. Please select a different surviving record.',
            ]);
        }

        $modelClass = $partyType === self::CUSTOMER ? Customer::class : Vendor::class;

        // Rows are locked in id order so two opposite merges cannot deadlock each other.
        $query = $modelClass::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', [$sourceId, $targetId])
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $parties = $query->get()->keyBy('id');
        $source = $parties->get($sourceId);
        $target = $parties->get($targetId);

        if (! $source || ! $target) {
            throw ValidationException::withMessages([
                'target_id' => 'One or both of the specified party records could not be found.',
            ]);
        }

        if ($source->isMerged()) {
            throw ValidationException::withMessages([
                'source_id' => "This record has already been merged into {$partyType} #{$source->merged_into_id}.",
            ]);
        }

        if ($target->isMerged()) {
            $message = $target->merged_into_id === $source->id
                ? 'Circular merge detected: the selected target was previously merged into this source record.'
                : "The selected target has itself been merged into {$partyType} #{$target->merged_into_id}. Choose that surviving record instead.";

            throw ValidationException::withMessages(['target_id' => $message]);
        }

        return [$source, $target];
    }
}
