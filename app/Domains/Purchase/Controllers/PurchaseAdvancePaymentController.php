<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseAdvancePaymentController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    /**
     * Store PO Advance Payment and invoke global Accounting Journal Service
     */
    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'vendor_id' => 'required|exists:vendors,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:Bank Transfer,Cheque,Cash,UPI',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $po = PurchaseOrder::where('tenant_id', $tenantId)->findOrFail($request->purchase_order_id);

        $currentAdvance = (float)$po->total_advance_paid;
        $newAdvance = (float)$request->amount;
        $maxAllowed = (float)$po->grand_total;

        if (($currentAdvance + $newAdvance) > $maxAllowed) {
            $rem = max(0, $maxAllowed - $currentAdvance);
            return redirect()->back()->withInput()->with('error', "Advance payment cannot exceed remaining balance (Max allowed: ₹" . number_format($rem, 2) . ").");
        }

        $count = PurchaseAdvancePayment::where('tenant_id', $tenantId)->count() + 1;
        $paymentNumber = 'VPAY-ADV-' . date('Y') . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($tenantId, $request, $po, $paymentNumber) {
            $payment = PurchaseAdvancePayment::create([
                'tenant_id' => $tenantId,
                'purchase_order_id' => $po->id,
                'vendor_id' => $request->vendor_id,
                'payment_number' => $paymentNumber,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number,
                'status' => 'Posted',
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Direct call to global Accounting JournalService (Dr: Advance to Vendor 1200, Cr: Bank 1010)
            try {
                $advanceAccount = $this->accounts->findByCode('1200', $tenantId) 
                               ?? $this->accounts->findByCode('1300', $tenantId);

                $bankAccount = $this->accounts->findByCode('1010', $tenantId) 
                            ?? $this->accounts->findByCode('1020', $tenantId);

                if ($advanceAccount && $bankAccount) {
                    $amt = (float)$payment->amount;
                    $vendorName = $payment->vendor?->name ?? 'Vendor';
                    
                    $this->journals->post([
                        [
                            'chart_of_account_id' => $advanceAccount->id,
                            'debit' => $amt,
                            'description' => "Advance to Vendor for PO {$po->purchase_order_number}",
                        ],
                        [
                            'chart_of_account_id' => $bankAccount->id,
                            'credit' => $amt,
                            'description' => "Bank Payment for Vendor Advance {$paymentNumber}",
                        ],
                    ], [
                        'tenant_id' => $tenantId,
                        'source' => 'purchase',
                        'reference_type' => PurchaseAdvancePayment::class,
                        'reference_id' => $payment->id,
                        'memo' => "Advance Payment {$paymentNumber} to Vendor {$vendorName}",
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Accounting Journal Posting Exception on PO Advance: ' . $e->getMessage());
            }
        });

        return redirect()->back()->with('success', "Advance Payment of ₹" . number_format($request->amount, 2) . " registered successfully for PO {$po->purchase_order_number}!");
    }
}
