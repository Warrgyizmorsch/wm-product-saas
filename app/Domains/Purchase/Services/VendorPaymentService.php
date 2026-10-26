<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\VendorPaymentAllocation;
use App\Domains\Purchase\Repositories\VendorPaymentRepository;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Purchase\Events\VendorPaymentRecorded;
use Illuminate\Support\Facades\DB;

class VendorPaymentService
{
    public function __construct(
        protected VendorPaymentRepository $paymentRepo,
        protected VendorBillRepository $billRepo
    ) {}

    public function recordPayment(array $validated, int $tenantId): VendorPayment
    {
        $billId = $validated['vendor_bill_id'] ?? $validated['allocations'][0]['vendor_bill_id'] ?? null;
        $bill = $billId ? $this->billRepo->find($billId) : null;
        $paymentType = 'Advance';
        if ($bill) {
            $paymentType = 'Bill Payment';
        } else {
            // Check if vendor has unsettled Opening Balance
            $vendor = \App\Domains\Inventory\Models\Vendor::find($validated['vendor_id']);
            // Only an amount owed to the vendor can be settled; an advance opening is not.
            $openingBal = (float) ($vendor?->signedOpeningBalance() ?? 0);
            if ($openingBal > 0) {
                $unallocatedPaid = (float) VendorPayment::where('tenant_id', $tenantId)
                    ->where('vendor_id', $validated['vendor_id'])
                    ->whereIn('payment_type', ['Opening Balance', 'Advance'])
                    ->whereDoesntHave('allocations')
                    ->where('status', '!=', 'cancelled')
                    ->sum('amount');
                $remainingOpening = max(0.0, $openingBal - $unallocatedPaid);
                if ($remainingOpening > 0.001) {
                    $paymentType = 'Opening Balance';
                }
            }
        }

        if ($bill?->isOnHold()) {
            throw new \InvalidArgumentException("Bill {$bill->bill_number} is on hold for a PO/GRN mismatch and cannot be paid until it is released.");
        }

        $payment = DB::transaction(function () use ($validated, $bill, $paymentType, $tenantId) {
            $paymentNumber = $this->paymentRepo->getNextPaymentNumber($tenantId);

            $allocatedAmount = $bill ? convert_to_base((float)($validated['allocations'][0]['allocated_amount'] ?? $validated['amount'])) : convert_to_base((float)$validated['amount']);
            $actualPaymentAmount = $bill ? $allocatedAmount : convert_to_base((float)$validated['amount']);

            $payment = $this->paymentRepo->create([
                'tenant_id'         => $tenantId,
                'payment_number'   => $paymentNumber,
                'vendor_id'         => $validated['vendor_id'],
                'purchase_order_id' => $bill?->purchase_order_id,
                'payment_type'     => $paymentType,
                'payment_method'   => $validated['payment_method'],
                'payment_date'     => $validated['payment_date'],
                'amount'           => $actualPaymentAmount,
                'reference_number' => $validated['reference_number'] ?? null,
                'status'           => 'Posted',
                'notes'            => $validated['notes'] ?? null,
                'created_by'       => auth()->id() ?: 1,
            ]);

            if ($bill) {
                VendorPaymentAllocation::create([
                    'tenant_id'         => $tenantId,
                    'vendor_payment_id' => $payment->id,
                    'vendor_bill_id'    => $bill->id,
                    'allocated_amount'  => $allocatedAmount,
                ]);

                $newPaid = (float)($bill->paid_amount ?? 0) + $allocatedAmount;
                $newDue = $bill->dueAfterPaying($newPaid);
                $status = ($newDue <= 0.001) ? 'Paid' : 'Partially Paid';

                $this->billRepo->update($bill, [
                    'paid_amount' => $newPaid,
                    'due_amount'  => $newDue,
                    'status'      => $status,
                ]);
            }

            return $payment;
        });

        event(new VendorPaymentRecorded($payment));

        return $payment;
    }

    /** Bill statuses that can still be paid. */
    public const OPEN_BILL_STATUSES = ['Posted', 'Unpaid', 'Approved', 'Partially Paid', 'Overdue'];

    /**
     * @return \Illuminate\Support\Collection<int, \App\Domains\Purchase\Models\VendorBill> oldest first
     */
    public function openBills(int $vendorId): \Illuminate\Support\Collection
    {
        return \App\Domains\Purchase\Models\VendorBill::query()
            ->where('vendor_id', $vendorId)
            ->whereIn('status', self::OPEN_BILL_STATUSES)
            ->where('due_amount', '>', 0)
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get(['id', 'bill_number', 'vendor_invoice_number', 'bill_date', 'due_date', 'grand_total', 'total_amount', 'due_amount', 'status']);
    }

    /**
     * Pay one or more open bills of a vendor in one payment — used by bank
     * reconciliation when one bank debit settles several bills. Anything not
     * applied to a bill is an advance to the vendor.
     *
     * @param array{
     *     tenant_id: int, company_id?: ?int, branch_id?: ?int, vendor_id: int,
     *     payment_date: string, payment_method: string, amount: float,
     *     reference_number?: ?string, notes?: ?string, bank_account_id?: ?int,
     *     allocations?: array<int, float>
     * } $data allocations: bill id => amount
     */
    public function payBills(array $data): VendorPayment
    {
        $amount = round((float) $data['amount'], 4);
        $allocations = array_filter(array_map(fn ($value) => round((float) $value, 4), $data['allocations'] ?? []), fn ($value) => $value > 0);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('A payment must be for a positive amount.');
        }

        if ((int) round(array_sum($allocations) * 10000) > (int) round($amount * 10000)) {
            throw new \InvalidArgumentException('Amounts applied to bills are more than the amount paid.');
        }

        $payment = DB::transaction(function () use ($data, $amount, $allocations) {
            $bills = \App\Domains\Purchase\Models\VendorBill::query()->whereIn('id', array_keys($allocations))->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $billId => $apply) {
                $bill = $bills[$billId] ?? null;

                if ($bill === null || (int) $bill->vendor_id !== (int) $data['vendor_id']) {
                    throw new \InvalidArgumentException("Bill #{$billId} does not belong to this vendor.");
                }

                if ($bill->isOnHold()) {
                    throw new \InvalidArgumentException("Bill {$bill->bill_number} is on hold for a PO/GRN mismatch and cannot be paid until it is released.");
                }

                if ((int) round($apply * 10000) > (int) round((float) $bill->due_amount * 10000)) {
                    throw new \InvalidArgumentException("Bill {$bill->bill_number} only has " . number_format((float) $bill->due_amount, 4) . ' due.');
                }
            }

            $payment = $this->paymentRepo->create([
                'tenant_id' => $data['tenant_id'],
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'payment_number' => $this->paymentRepo->getNextPaymentNumber($data['tenant_id']),
                'vendor_id' => $data['vendor_id'],
                // Applied to bills → Accounts Payable (any remainder stays as a debit
                // balance on the vendor); nothing applied → Advance to Suppliers.
                'payment_type' => $allocations !== [] ? 'Bill Payment' : 'Advance',
                'payment_method' => $data['payment_method'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'status' => 'Posted',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($allocations as $billId => $apply) {
                $bill = $bills[$billId];

                VendorPaymentAllocation::create([
                    'tenant_id' => $data['tenant_id'],
                    'company_id' => $payment->company_id,
                    'branch_id' => $payment->branch_id,
                    'vendor_payment_id' => $payment->id,
                    'vendor_bill_id' => $bill->id,
                    'allocated_amount' => $apply,
                ]);

                $paid = round((float) ($bill->paid_amount ?? 0) + $apply, 4);
                $due = $bill->dueAfterPaying($paid);

                $this->billRepo->update($bill, [
                    'paid_amount' => $paid,
                    'due_amount' => $due,
                    'status' => $due <= 0.0001 ? 'Paid' : 'Partially Paid',
                ]);
            }

            return $payment;
        });

        event(new VendorPaymentRecorded($payment));

        return $payment;
    }
}
