<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment;
use App\Models\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MaintenanceWorkOrderJournalService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly SystemAccountService $systemAccounts,
        private readonly AccountResolverService $accountResolver,
        private readonly ?CurrencyService $currencyService = null,
        private readonly ?PostingFailureRecorder $failureRecorder = null
    ) {
    }

    /**
     * Posts a balanced double-entry General Ledger journal for a completed Maintenance Work Order.
     *
     * Accounts involved:
     * - Debit: 5530 — Repairs & Maintenance (for total eligible repair costs)
     * - Credit: Resolved Inventory account (for actual consumed spare parts from stock)
     * - Credit: 2015 — Creditors for Expenses (for external mechanic costs)
     * - Credit: 2070 — Outstanding Expenses (for accrued unpaid additional/overhead expenses)
     *
     * If total eligible cost is zero, no journal is posted and null is returned.
     * If a journal already exists for this work order, it is returned without duplicate posting.
     */
    public function postCompletionJournal(
        ProductionMaintenanceWorkOrder $workOrder,
        ?int $userId = null,
        ?float $fallbackExternalCost = null
    ): ?Journal
    {
        if ($workOrder->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
            throw new InvalidArgumentException("Cannot post a completion journal for a cancelled Work Order #{$workOrder->id}.");
        }

        $tenantId = (int) $workOrder->tenant_id;

        // Check if tenant has Chart of Accounts provisioned. If tenant has no Chart of Accounts
        // configured at all, record to PostingFailureRecorder and gracefully skip auto-posting
        // so that unprovisioned tenants or non-accounting test fixtures continue to complete.
        if (!ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            $message = "Tenant #{$tenantId} has no Chart of Accounts provisioned, skipping completion journal.";
            Log::warning("MaintenanceWorkOrderJournalService: {$message}", [
                'work_order_id' => $workOrder->id,
                'tenant_id' => $tenantId,
            ]);
            try {
                $recorder = $this->failureRecorder ?? app(PostingFailureRecorder::class);
                $recorder->record($tenantId, 'maintenance_work_order', $workOrder, $message);
            } catch (\Throwable) {
                // Ignore if recording table is not present
            }

            return null;
        }

        // 1. Idempotency guard: prevent duplicate journal creation on retries
        $existingJournals = $this->journalService->findByReference('maintenance_work_order', $workOrder->id);
        if ($existingJournals->isNotEmpty()) {
            return $existingJournals->first();
        }

        // 2. Resolve currency symbol for narration
        $currencySymbol = $this->resolveCurrencySymbol($workOrder);

        // 3. Process Inventory-issued spare parts consumed
        $inventoryCredits = [];
        $totalInventoryCost = 0.0;

        $spares = $workOrder->spares()
            ->where('issued_qty', '>', 0)
            ->get();

        foreach ($spares as $spare) {
            $cost = round((float) $spare->total_cost, 2);
            $issuedQty = (float) $spare->issued_qty;
            if ($cost <= 0.0 || $issuedQty <= 0.0) {
                continue;
            }

            $product = $spare->product ?? Product::withoutGlobalScopes()->find($spare->product_id);
            $invAccount = $this->resolveInventoryAccount($product, $tenantId);
            $accId = $invAccount->id;

            if (!isset($inventoryCredits[$accId])) {
                $inventoryCredits[$accId] = [
                    'account' => $invAccount,
                    'amount' => 0.0,
                    'descriptions' => [],
                ];
            }

            $inventoryCredits[$accId]['amount'] = round($inventoryCredits[$accId]['amount'] + $cost, 2);
            $productName = $product?->name ?? "Product #{$spare->product_id}";
            $inventoryCredits[$accId]['descriptions'][] = "{$productName} (qty: {$issuedQty})";
            $totalInventoryCost = round($totalInventoryCost + $cost, 2);
        }

        // 4. Process External Mechanic costs (strictly exclude internal salaried mechanics)
        $externalMechanicLines = [];
        $externalMechanicNarrationLines = [];
        $totalExternalCost = 0.0;

        $externalAssignments = $workOrder->assignments()
            ->where('assignment_type', ProductionMaintenanceWorkOrderAssignment::TYPE_EXTERNAL)
            ->get();

        $creditorsAccount = null;
        if ($externalAssignments->isNotEmpty()) {
            $creditorsAccount = $this->resolveCreditorsAccount($tenantId);

            foreach ($externalAssignments as $asn) {
                $workedHours = (float) $asn->worked_hours;
                $hourlyRate = (float) $asn->hourly_rate;
                $cost = round($hourlyRate * hours_to_decimal($workedHours), 2);

                if ($cost <= 0.0) {
                    continue;
                }

                $totalExternalCost = round($totalExternalCost + $cost, 2);
                $mechName = trim((string) $asn->technician_name) ?: "External Mechanic #{$asn->id}";
                $formattedCost = $currencySymbol . number_format($cost, fmod($cost, 1.0) == 0.0 ? 0 : 2);

                $externalMechanicLines[] = [
                    'chart_of_account_id' => $creditorsAccount->id,
                    'credit' => $cost,
                    'debit' => 0.0,
                    'description' => "External mechanic: {$mechName} ({$workedHours} hrs @ {$hourlyRate}/hr = {$formattedCost}) - WO #{$workOrder->work_order_number}",
                ];

                $externalMechanicNarrationLines[] = "{$mechName}: {$formattedCost}";
            }
        } elseif ($fallbackExternalCost !== null && round((float) $fallbackExternalCost, 2) > 0.0) {
            $cost = round((float) $fallbackExternalCost, 2);
            $creditorsAccount = $this->resolveCreditorsAccount($tenantId);
            $totalExternalCost = $cost;
            $formattedCost = $currencySymbol . number_format($cost, fmod($cost, 1.0) == 0.0 ? 0 : 2);

            $externalMechanicLines[] = [
                'chart_of_account_id' => $creditorsAccount->id,
                'credit' => $cost,
                'debit' => 0.0,
                'description' => "External mechanic repair cost ({$formattedCost}) - WO #{$workOrder->work_order_number}",
            ];

            $externalMechanicNarrationLines[] = "External Mechanic: {$formattedCost}";
        }

        // 5. Process Additional / Overhead Expenses (unpaid portion accrued to Outstanding Expenses)
        $additionalCost = round(max(0.0, (float) $workOrder->additional_cost), 2);
        $outstandingAccount = null;
        $additionalCostLine = null;

        if ($additionalCost > 0.0) {
            $outstandingAccount = $this->resolveOutstandingExpensesAccount($tenantId);
            $additionalCostLine = [
                'chart_of_account_id' => $outstandingAccount->id,
                'credit' => $additionalCost,
                'debit' => 0.0,
                'description' => "Additional/overhead expenses - WO #{$workOrder->work_order_number}",
            ];
        }

        // 6. Total eligible maintenance expense
        $totalEligibleExpense = round($totalInventoryCost + $totalExternalCost + $additionalCost, 2);

        // Guard: If there is zero eligible cost to post, do not create an empty or zero-value journal
        if ($totalEligibleExpense <= 0.0) {
            return null;
        }

        // 7. Resolve Repairs & Maintenance debit account
        $repairExpenseAccount = $this->resolveRepairExpenseAccount($tenantId);

        // 8. Build Journal Entry Lines
        $lines = [];

        // Debit: 5530 — Repairs & Maintenance
        $lines[] = [
            'chart_of_account_id' => $repairExpenseAccount->id,
            'debit' => $totalEligibleExpense,
            'credit' => 0.0,
            'description' => "Maintenance repair expense for WO #{$workOrder->work_order_number}",
        ];

        // Credits: Inventory
        foreach ($inventoryCredits as $accData) {
            $descList = implode(', ', $accData['descriptions']);
            $lines[] = [
                'chart_of_account_id' => $accData['account']->id,
                'credit' => $accData['amount'],
                'debit' => 0.0,
                'description' => "Spare parts consumed: {$descList} - WO #{$workOrder->work_order_number}",
            ];
        }

        // Credits: External Mechanics (Creditors for Expenses)
        foreach ($externalMechanicLines as $mechLine) {
            $lines[] = $mechLine;
        }

        // Credit: Additional Expenses (Outstanding Expenses)
        if ($additionalCostLine !== null) {
            $lines[] = $additionalCostLine;
        }

        // 9. Build Journal Narration / Memo
        $narrationParts = [];
        $narrationParts[] = "Maintenance WO: {$workOrder->work_order_number}";

        foreach ($externalMechanicNarrationLines as $mechNarration) {
            $narrationParts[] = $mechNarration;
        }

        if ((bool) $workOrder->external_parts_purchased) {
            $narrationParts[] = "Additional/overhead expenses include externally supplied repair parts not issued from inventory.";
        }

        $memo = implode("\n", $narrationParts);

        // 10. Post through JournalService
        $journalDate = $workOrder->actual_end ? Carbon::parse($workOrder->actual_end)->toDateString() : now()->toDateString();

        $meta = [
            'tenant_id' => $tenantId,
            'company_id' => $workOrder->company_id,
            'branch_id' => $workOrder->branch_id,
            'journal_date' => $journalDate,
            'source' => Journal::SOURCE_PRODUCTION,
            'reference_type' => 'maintenance_work_order',
            'reference_id' => $workOrder->id,
            'memo' => $memo,
            'posted_by' => $userId,
        ];

        return $this->journalService->post($lines, $meta);
    }

    /**
     * Resolves and validates 5530 Repairs & Maintenance.
     */
    public function resolveRepairExpenseAccount(int $tenantId): ChartOfAccount
    {
        $account = $this->systemAccounts->get('repairs_and_maintenance', $tenantId)
            ?? $this->accountResolver->resolveAccount(null, $tenantId, fallbackCode: '5530', fallbackType: ChartOfAccount::TYPE_EXPENSE);

        return $this->assertPostableAccount($account, 'Repairs & Maintenance Expense', '5530', $tenantId);
    }

    /**
     * Resolves and validates Inventory asset account (default 1200 or product-specific).
     */
    public function resolveInventoryAccount(?Product $product, int $tenantId): ChartOfAccount
    {
        $account = $this->accountResolver->resolveInventoryAccount($product, $tenantId)
            ?? $this->systemAccounts->get(SystemAccount::INVENTORY, $tenantId)
            ?? $this->accountResolver->resolveAccount(null, $tenantId, fallbackCode: '1200', fallbackType: ChartOfAccount::TYPE_ASSET);

        return $this->assertPostableAccount($account, 'Inventory Asset', '1200', $tenantId);
    }

    /**
     * Resolves and validates 2015 Creditors for Expenses for external mechanics.
     */
    public function resolveCreditorsAccount(int $tenantId): ChartOfAccount
    {
        $account = $this->systemAccounts->get('creditors_for_expenses', $tenantId)
            ?? $this->accountResolver->resolveAccount(null, $tenantId, fallbackCode: '2015', fallbackType: ChartOfAccount::TYPE_LIABILITY);

        return $this->assertPostableAccount($account, 'External Mechanic Payables (Creditors for Expenses)', '2015', $tenantId);
    }

    /**
     * Resolves and validates 2070 Outstanding Expenses for unpaid additional costs.
     */
    public function resolveOutstandingExpensesAccount(int $tenantId): ChartOfAccount
    {
        $account = $this->systemAccounts->get('outstanding_expenses', $tenantId)
            ?? $this->accountResolver->resolveAccount(null, $tenantId, fallbackCode: '2070', fallbackType: ChartOfAccount::TYPE_LIABILITY);

        return $this->assertPostableAccount($account, 'Outstanding Expenses (Additional Expenses)', '2070', $tenantId);
    }

    /**
     * Asserts that a resolved account exists, belongs to tenant scope, is active, and is not a group account.
     */
    private function assertPostableAccount(?ChartOfAccount $account, string $roleName, string $expectedCode, int $tenantId): ChartOfAccount
    {
        if ($account === null) {
            throw new InvalidArgumentException("Required account for {$roleName} (expected code {$expectedCode}) could not be resolved for tenant #{$tenantId}.");
        }

        if ($account->tenant_id !== null && (int) $account->tenant_id !== $tenantId) {
            throw new InvalidArgumentException("Account '{$account->name}' ({$account->code}) does not belong to tenant #{$tenantId}.");
        }

        if (!$account->is_active) {
            throw new InvalidArgumentException("Account '{$account->name}' ({$account->code}) for {$roleName} is inactive.");
        }

        $isGroup = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('parent_id', $account->id)
            ->exists();

        if ($isGroup) {
            throw new InvalidArgumentException("Cannot post directly to group account '{$account->name}' ({$account->code}) for {$roleName}.");
        }

        return $account;
    }

    private function resolveCurrencySymbol(ProductionMaintenanceWorkOrder $workOrder): string
    {
        try {
            if ($workOrder->company_id) {
                $currencyService = $this->currencyService ?? app(CurrencyService::class);
                $code = $currencyService->baseCurrencyFor($workOrder->company_id);
                $currency = Currency::where('code', $code)->first();
                if ($currency && !empty($currency->symbol)) {
                    return $currency->symbol;
                }
            }
        } catch (\Throwable) {
            // fallback
        }

        if (function_exists('active_currency_symbol')) {
            $sym = active_currency_symbol();
            if (!empty($sym)) {
                return $sym;
            }
        }

        return '₹';
    }
}
