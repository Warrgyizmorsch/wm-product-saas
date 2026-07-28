<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\VendorPaymentAllocation;
use App\Domains\Purchase\Repositories\VendorPaymentRepository;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Models\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorPaymentService
{
    public function __construct(
        protected VendorPaymentRepository $paymentRepo,
        protected VendorBillRepository $billRepo,
        protected JournalService $journalService,
        protected ChartOfAccountRepositoryInterface $accountRepo
    ) {}

    public function recordPayment(array $validated, int $tenantId): VendorPayment
    {
        $billId = $validated['vendor_bill_id'] ?? $validated['allocations'][0]['vendor_bill_id'] ?? null;
        $bill = $billId ? $this->billRepo->find($billId) : null;
        $paymentType = $bill ? 'Bill Payment' : 'Advance';

        return DB::transaction(function () use ($validated, $bill, $paymentType, $tenantId) {
            $paymentNumber = $this->paymentRepo->getNextPaymentNumber($tenantId);

            $payment = $this->paymentRepo->create([
                'tenant_id'         => $tenantId,
                'payment_number'   => $paymentNumber,
                'vendor_id'         => $validated['vendor_id'],
                'purchase_order_id' => $bill?->purchase_order_id,
                'payment_type'     => $paymentType,
                'payment_method'   => $validated['payment_method'],
                'payment_date'     => $validated['payment_date'],
                'amount'           => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? null,
                'status'           => 'Posted',
                'notes'            => $validated['notes'] ?? null,
                'created_by'       => auth()->id() ?: 1,
            ]);

            if ($bill) {
                $allocatedAmount = (float)($validated['allocations'][0]['allocated_amount'] ?? $validated['amount']);

                VendorPaymentAllocation::create([
                    'tenant_id'         => $tenantId,
                    'vendor_payment_id' => $payment->id,
                    'vendor_bill_id'    => $bill->id,
                    'allocated_amount'  => $allocatedAmount,
                ]);

                $newPaid = (float)($bill->paid_amount ?? 0) + $allocatedAmount;
                $billTotal = (float)($bill->grand_total ?: $bill->total_amount);
                $newDue = max(0.0, $billTotal - $newPaid);
                $status = ($newDue <= 0.001) ? 'Paid' : 'Partially Paid';

                $this->billRepo->update($bill, [
                    'paid_amount' => $newPaid,
                    'due_amount'  => $newDue,
                    'status'      => $status,
                ]);
            }

            // Post Double-Entry General Journal to Accounting Module
            $this->postAccountingJournal($payment, $bill, $tenantId);

            return $payment;
        });
    }

    protected function postAccountingJournal(VendorPayment $payment, ?VendorBill $bill, int $tenantId): void
    {
        try {
            // Find Asset / Cash / Bank account based on payment method
            $bankCode = ($payment->payment_method === 'Cash') ? '1010' : '1020';
            $paymentAccount = $this->accountRepo->findByCode($bankCode, $tenantId);
            if (!$paymentAccount) {
                $paymentAccount = $this->accountRepo->getByType('asset')->first();
            }

            // Accounts Payable or Advance to Vendor
            $payableAccount = $this->accountRepo->findByCode('2100', $tenantId)
                ?? $this->accountRepo->findByCode('1400', $tenantId)
                ?? $this->accountRepo->getByType('liability')->first()
                ?? $this->accountRepo->getByType('asset')->last();

            if ($paymentAccount && $payableAccount) {
                $vendorName = $payment->vendor?->name ?? ('Vendor #' . $payment->vendor_id);
                $memo = $bill 
                    ? "Vendor Payment {$payment->payment_number} for Bill {$bill->bill_number}"
                    : "Direct Vendor Payment / Advance {$payment->payment_number}";

                $lines = [
                    [
                        'chart_of_account_id' => $payableAccount->id,
                        'debit'               => (float)$payment->amount,
                        'description'         => "Payment to Vendor ({$vendorName})" . ($bill ? " for Bill {$bill->bill_number}" : " (Advance)"),
                    ],
                    [
                        'chart_of_account_id' => $paymentAccount->id,
                        'credit'              => (float)$payment->amount,
                        'description'         => "Paid via {$payment->payment_method}" . ($payment->reference_number ? " Ref: {$payment->reference_number}" : ""),
                    ],
                ];

                $this->journalService->post($lines, [
                    'tenant_id'      => $tenantId,
                    'journal_date'   => $payment->payment_date,
                    'source'         => Journal::SOURCE_PURCHASE,
                    'reference_type' => 'vendor_payment',
                    'reference_id'   => $payment->id,
                    'memo'           => $memo,
                    'posted_by'      => auth()->id() ?: 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('VendorPaymentService: Failed to auto-post accounting journal', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
