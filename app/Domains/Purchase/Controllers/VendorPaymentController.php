<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\VendorPaymentAllocation;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorPaymentController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    /**
     * List all Vendor Payments
     */
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = VendorPayment::where('tenant_id', $tenantId)
            ->with(['vendor', 'allocations.bill', 'purchaseOrder']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $payments = $query->latest()->paginate(15);

        return view('modules.purchase.payments.index', compact('payments'));
    }

    /**
     * Create Vendor Payment Form
     */
    public function create(Request $request)
    {
        $tenantId = require_tenant_id();
        $vendors = Vendor::where('tenant_id', $tenantId)->get();

        $selectedBill = null;
        $totalAdvancePaid = 0;
        $suggestedNetPayable = 0;

        if ($request->filled('bill_id')) {
            $selectedBill = VendorBill::where('tenant_id', $tenantId)
                ->with(['vendor', 'purchaseOrder'])
                ->find($request->bill_id);

            if ($selectedBill && $selectedBill->purchase_order_id) {
                $totalAdvancePaid = (float) \App\Domains\Purchase\Models\PurchaseAdvancePayment::where('tenant_id', $tenantId)
                    ->where('purchase_order_id', $selectedBill->purchase_order_id)
                    ->where('status', 'Posted')
                    ->sum('amount');

                $suggestedNetPayable = max(0, (float)$selectedBill->due_amount - $totalAdvancePaid);
            } elseif ($selectedBill) {
                $suggestedNetPayable = (float)$selectedBill->due_amount;
            }
        }

        return view('modules.purchase.payments.create', compact('vendors', 'selectedBill', 'totalAdvancePaid', 'suggestedNetPayable'));
    }

    /**
     * Store Vendor Payment & Multi-Bill Payment Allocation
     */
    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:Bank Transfer,Cheque,Cash,UPI',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'allocations' => 'nullable|array',
            'allocations.*.vendor_bill_id' => 'required|exists:vendor_bills,id',
            'allocations.*.allocated_amount' => 'required|numeric|min:0.01',
        ]);

        $count = VendorPayment::where('tenant_id', $tenantId)->count() + 1;
        $paymentNumber = 'VPAY-' . date('Y') . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($tenantId, $request, $paymentNumber, &$payment) {
            $payment = VendorPayment::create([
                'tenant_id' => $tenantId,
                'payment_number' => $paymentNumber,
                'vendor_id' => $request->vendor_id,
                'payment_type' => 'Bill Payment',
                'payment_method' => $request->payment_method,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'reference_number' => $request->reference_number,
                'status' => 'Posted',
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            $totalAdvanceAdjusted = 0;

            // Save Payment Allocations against Vendor Bills
            if (!empty($request->allocations)) {
                foreach ($request->allocations as $alloc) {
                    $billId = $alloc['vendor_bill_id'];
                    $allocAmt = (float) $alloc['allocated_amount'];

                    VendorPaymentAllocation::create([
                        'tenant_id' => $tenantId,
                        'vendor_payment_id' => $payment->id,
                        'vendor_bill_id' => $billId,
                        'allocated_amount' => $allocAmt,
                    ]);

                    // Update Vendor Bill due balances
                    $bill = VendorBill::where('tenant_id', $tenantId)->find($billId);
                    if ($bill) {
                        $advanceForPo = 0;
                        if ($bill->purchase_order_id) {
                            $advanceForPo = (float) \App\Domains\Purchase\Models\PurchaseAdvancePayment::where('tenant_id', $tenantId)
                                ->where('purchase_order_id', $bill->purchase_order_id)
                                ->where('status', 'Posted')
                                ->sum('amount');
                        }

                        $totalAdvanceAdjusted += $advanceForPo;
                        $totalSettledForBill = $allocAmt + $advanceForPo;
                        $newPaid = (float)$bill->paid_amount + $totalSettledForBill;
                        $newDue = max(0, (float)$bill->grand_total - $newPaid);
                        $newStatus = ($newDue <= 0.001) ? 'Paid' : 'Partially Paid';

                        $bill->update([
                            'paid_amount' => $newPaid,
                            'due_amount' => $newDue,
                            'status' => $newStatus,
                        ]);
                    }
                }
            }

            // Post directly to global Accounting JournalService (Dr: Accounts Payable 2000, Cr: Advance 1200, Cr: Bank 1010)
            try {
                $payableAccount = $this->accounts->findByCode('2000', $tenantId) 
                               ?? $this->accounts->findByCode('2100', $tenantId);

                $bankAccount = $this->accounts->findByCode('1010', $tenantId) 
                            ?? $this->accounts->findByCode('1020', $tenantId);

                $advanceAccount = $this->accounts->findByCode('1200', $tenantId) 
                               ?? $this->accounts->findByCode('1300', $tenantId);

                if ($payableAccount && $bankAccount) {
                    $bankAmt = (float)$payment->amount;
                    $totalPayableCleared = $bankAmt + $totalAdvanceAdjusted;
                    $vendorName = $payment->vendor?->name ?? 'Vendor';

                    $lines = [
                        [
                            'chart_of_account_id' => $payableAccount->id,
                            'debit' => $totalPayableCleared,
                            'description' => "Accounts Payable cleared via Payment {$paymentNumber}",
                        ]
                    ];

                    if ($totalAdvanceAdjusted > 0 && $advanceAccount) {
                        $lines[] = [
                            'chart_of_account_id' => $advanceAccount->id,
                            'credit' => $totalAdvanceAdjusted,
                            'description' => "Vendor Advance Settled for Payment {$paymentNumber}",
                        ];
                    }

                    if ($bankAmt > 0) {
                        $lines[] = [
                            'chart_of_account_id' => $bankAccount->id,
                            'credit' => $bankAmt,
                            'description' => "Bank Payment to Vendor {$vendorName}",
                        ];
                    }

                    $this->journals->post($lines, [
                        'tenant_id' => $tenantId,
                        'source' => 'purchase',
                        'reference_type' => VendorPayment::class,
                        'reference_id' => $payment->id,
                        'memo' => "Vendor Payment {$paymentNumber} to Vendor {$vendorName}",
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Accounting Journal Posting Exception on Vendor Payment: ' . $e->getMessage());
            }
        });

        return redirect()->route('purchase.payments.index')->with('success', "Vendor Payment {$payment->payment_number} of ₹" . number_format($request->amount, 2) . " posted successfully!");
    }

    /**
     * Show Payment Details
     */
    public function show($id)
    {
        $tenantId = require_tenant_id();

        $payment = VendorPayment::where('tenant_id', $tenantId)
            ->with(['vendor', 'allocations.bill', 'creator'])
            ->findOrFail($id);

        return view('modules.purchase.payments.show', compact('payment'));
    }
}
