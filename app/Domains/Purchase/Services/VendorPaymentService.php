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
        $paymentType = $bill ? 'Bill Payment' : 'Advance';

        $payment = DB::transaction(function () use ($validated, $bill, $paymentType, $tenantId) {
            $paymentNumber = $this->paymentRepo->getNextPaymentNumber($tenantId);

            $allocatedAmount = $bill ? (float)($validated['allocations'][0]['allocated_amount'] ?? $validated['amount']) : (float)$validated['amount'];
            $actualPaymentAmount = $bill ? $allocatedAmount : (float)$validated['amount'];

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
                $billTotal = (float)($bill->grand_total ?: $bill->total_amount);
                $newDue = max(0.0, $billTotal - $newPaid);
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
}
