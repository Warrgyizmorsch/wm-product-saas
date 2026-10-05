<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Events\CustomerPaymentReceived;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\PaymentAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Recording a customer receipt against one or more open invoices — used by
 * other modules (bank reconciliation) that need Sales to do its own
 * bookkeeping: allocation rows, invoice paid/balance/status, and the
 * CustomerPaymentReceived event that Accounting posts the journal from.
 */
class CustomerPaymentService
{
    /** Invoice statuses that can still receive money. */
    public const OPEN_STATUSES = ['Posted', 'Sent', 'Partially Paid', 'Partial', 'Overdue'];

    /**
     * @return Collection<int, Invoice> oldest first
     */
    public function openInvoices(int $customerId): Collection
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('balance_due', '>', 0)
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get(['id', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'balance_due', 'status']);
    }

    /**
     * @param array{
     *     tenant_id: int, company_id?: ?int, branch_id?: ?int, customer_id: int,
     *     payment_date: string, payment_method: string, amount: float,
     *     reference_no?: ?string, notes?: ?string, bank_account_id?: ?int,
     *     allocations?: array<int, float>
     * } $data allocations: invoice id => amount; anything left over stays on account
     */
    public function receive(array $data): CustomerPayment
    {
        $amount = round((float) $data['amount'], 2);
        $allocations = array_filter(array_map(fn ($value) => round((float) $value, 2), $data['allocations'] ?? []), fn ($value) => $value > 0);

        if ($amount <= 0) {
            throw new InvalidArgumentException('A receipt must be for a positive amount.');
        }

        if ((int) round(array_sum($allocations) * 100) > (int) round($amount * 100)) {
            throw new InvalidArgumentException('Amounts applied to invoices are more than the amount received.');
        }

        return DB::transaction(function () use ($data, $amount, $allocations) {
            $invoices = Invoice::query()->whereIn('id', array_keys($allocations))->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $invoiceId => $apply) {
                $invoice = $invoices[$invoiceId] ?? null;

                if ($invoice === null || (int) $invoice->customer_id !== (int) $data['customer_id']) {
                    throw new InvalidArgumentException("Invoice #{$invoiceId} does not belong to this customer.");
                }

                if ((int) round($apply * 100) > (int) round((float) $invoice->balance_due * 100)) {
                    throw new InvalidArgumentException("Invoice {$invoice->invoice_number} only has " . number_format((float) $invoice->balance_due, 2) . ' outstanding.');
                }
            }

            $payment = CustomerPayment::create([
                'tenant_id' => $data['tenant_id'],
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'payment_number' => $this->nextNumber(),
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'amount' => $amount,
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'Posted',
            ]);

            foreach ($allocations as $invoiceId => $apply) {
                $invoice = $invoices[$invoiceId];

                PaymentAllocation::create([
                    'tenant_id' => $data['tenant_id'],
                    'company_id' => $payment->company_id,
                    'branch_id' => $payment->branch_id,
                    'customer_payment_id' => $payment->id,
                    'sales_order_id' => $invoice->sales_order_id,
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => $apply,
                ]);

                $invoice->amount_paid = round((float) $invoice->amount_paid + $apply, 2);
                $invoice->balance_due = max(0, round((float) $invoice->total_amount - $invoice->amount_paid, 2));
                $invoice->status = $invoice->balance_due <= 0 ? 'Paid' : 'Partially Paid';
                $invoice->save();
            }

            event(new CustomerPaymentReceived($payment));

            return $payment;
        });
    }

    /** Same numbering as the Payments screen: PAY-0001, PAY-0002, … */
    private function nextNumber(): string
    {
        $latest = CustomerPayment::query()->where('payment_number', 'like', 'PAY-%')->latest('id')->value('payment_number');
        $next = $latest ? ((int) preg_replace('/\D/', '', substr($latest, 4))) + 1 : 1;

        do {
            $number = 'PAY-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (CustomerPayment::withoutGlobalScopes()->where('payment_number', $number)->exists());

        return $number;
    }
}
