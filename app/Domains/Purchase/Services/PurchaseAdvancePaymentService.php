<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Repositories\PurchaseAdvancePaymentRepository;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Models\Journal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PurchaseAdvancePaymentService
{
    public function __construct(
        protected PurchaseAdvancePaymentRepository $advanceRepo,
        protected JournalService $journalService,
        protected ChartOfAccountRepositoryInterface $accountRepo
    ) {}

    public function recordAdvancePayment(array $validated, int $tenantId): PurchaseAdvancePayment
    {
        return DB::transaction(function () use ($validated, $tenantId) {
            $advanceNumber = $this->advanceRepo->getNextAdvanceNumber($tenantId);

            $advance = $this->advanceRepo->create([
                'tenant_id' => $tenantId,
                'payment_number' => $advanceNumber,
                'payment_date' => $validated['payment_date'],
                'vendor_id' => $validated['vendor_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'Posted',
                'created_by' => auth()->id() ?: 1,
            ]);

            // Post Double-Entry Journal to Accounting Module
            $this->postAccountingJournal($advance, $tenantId);

            return $advance;
        });
    }

    protected function postAccountingJournal(PurchaseAdvancePayment $advance, int $tenantId): void
    {
        try {
            // Find Asset / Cash / Bank account based on payment method
            $bankCode = ($advance->payment_method === 'Cash') ? '1010' : '1020';
            $paymentAccount = $this->accountRepo->findByCode($bankCode, $tenantId);
            if (!$paymentAccount) {
                $paymentAccount = $this->accountRepo->getByType('asset')->first();
            }

            // Find Advance to Vendor / Accounts Payable liability or asset account
            $advanceAccount = $this->accountRepo->findByCode('1400', $tenantId)
                ?? $this->accountRepo->findByCode('2100', $tenantId)
                ?? $this->accountRepo->getByType('liability')->first()
                ?? $this->accountRepo->getByType('asset')->last();

            if ($paymentAccount && $advanceAccount) {
                $memo = "Vendor Advance Payment {$advance->advance_number}";
                if ($advance->purchaseOrder) {
                    $memo .= " for PO {$advance->purchaseOrder->purchase_order_number}";
                }

                $lines = [
                    [
                        'chart_of_account_id' => $advanceAccount->id,
                        'debit' => (float)$advance->amount,
                        'description' => "Advance to Vendor (" . ($advance->vendor?->name ?? 'Vendor #' . $advance->vendor_id) . ")",
                    ],
                    [
                        'chart_of_account_id' => $paymentAccount->id,
                        'credit' => (float)$advance->amount,
                        'description' => "Paid via {$advance->payment_method}" . ($advance->reference_number ? " Ref: {$advance->reference_number}" : ""),
                    ],
                ];

                $this->journalService->post($lines, [
                    'tenant_id' => $tenantId,
                    'journal_date' => $advance->payment_date,
                    'source' => Journal::SOURCE_PURCHASE,
                    'reference_type' => 'vendor_advance_payment',
                    'reference_id' => $advance->id,
                    'memo' => $memo,
                    'posted_by' => auth()->id() ?: 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('PurchaseAdvancePaymentService: Failed to auto-post accounting journal', [
                'advance_id' => $advance->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
