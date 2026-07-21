<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorBillController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    /**
     * List all Vendor Bills
     */
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = VendorBill::where('tenant_id', $tenantId)
            ->with(['vendor', 'goodsReceiptNote', 'purchaseOrder']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                  ->orWhere('vendor_invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bills = $query->latest()->paginate(15);

        return view('modules.purchase.bills.index', compact('bills'));
    }

    /**
     * Create Bill Screen - Strictly Requires an Approved Goods Receipt Note (GRN)
     */
    public function create(Request $request)
    {
        $tenantId = require_tenant_id();

        if (!$request->filled('grn_id')) {
            return redirect()->route('purchase.grns.index')->with('error', 'Vendor Bill can ONLY be generated from an Approved Goods Receipt Note (GRN). Please select an Approved GRN.');
        }

        $selectedGrn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->with(['items.product', 'vendor', 'purchaseOrder'])
            ->findOrFail($request->grn_id);

        $vendors = Vendor::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.bills.create', compact('vendors', 'selectedGrn'));
    }

    /**
     * Store Vendor Bill generated from Approved GRN and post Journal Entry
     */
    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $request->validate([
            'goods_receipt_note_id' => 'required|exists:goods_receipt_notes,id',
            'vendor_id' => 'required|exists:vendors,id',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date',
            'vendor_invoice_number' => 'nullable|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_rate' => 'required|numeric|min:0',
        ]);

        $grn = GoodsReceiptNote::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->findOrFail($request->goods_receipt_note_id);

        $count = VendorBill::where('tenant_id', $tenantId)->count() + 1;
        $billNumber = 'BILL-' . date('Y') . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($tenantId, $request, $grn, $billNumber, &$bill) {
            $subtotal = 0;
            foreach ($request->items as $itemData) {
                $qty = (float) $itemData['quantity'];
                $rate = (float) $itemData['unit_rate'];
                $subtotal += ($qty * $rate);
            }

            $taxAmount = (float) ($request->tax_amount ?? 0);
            $grandTotal = $subtotal + $taxAmount;

            $bill = VendorBill::create([
                'tenant_id' => $tenantId,
                'bill_number' => $billNumber,
                'vendor_invoice_number' => $request->vendor_invoice_number,
                'goods_receipt_note_id' => $grn->id,
                'purchase_order_id' => $grn->purchase_order_id,
                'vendor_id' => $request->vendor_id,
                'bill_date' => $request->bill_date,
                'due_date' => $request->due_date ?: date('Y-m-d', strtotime('+30 days')),
                'status' => 'Posted',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => 0,
                'due_amount' => $grandTotal,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($request->items as $itemData) {
                $qty = (float) $itemData['quantity'];
                $rate = (float) $itemData['unit_rate'];
                $lineTotal = $qty * $rate;

                VendorBillItem::create([
                    'tenant_id' => $tenantId,
                    'vendor_bill_id' => $bill->id,
                    'product_id' => $itemData['product_id'] ?? null,
                    'goods_receipt_note_item_id' => $itemData['goods_receipt_note_item_id'] ?? null,
                    'quantity' => $qty,
                    'unit_rate' => $rate,
                    'total_amount' => $lineTotal,
                ]);
            }

            // Post directly to global Accounting JournalService (Dr: GRNI 2100, Cr: Accounts Payable 2000)
            try {
                $grniAccount = $this->accounts->findByCode('2100', $tenantId) 
                            ?? $this->accounts->findByCode('1400', $tenantId);

                $payableAccount = $this->accounts->findByCode('2000', $tenantId) 
                               ?? $this->accounts->findByCode('2100', $tenantId);

                if ($grniAccount && $payableAccount) {
                    $grnNo = $grn->grn_number;
                    $invNo = $bill->vendor_invoice_number ?: $bill->bill_number;

                    $this->journals->post([
                        [
                            'chart_of_account_id' => $grniAccount->id,
                            'debit' => $grandTotal,
                            'description' => "Clear GRNI liability for Bill {$bill->bill_number} (GRN: {$grnNo})",
                        ],
                        [
                            'chart_of_account_id' => $payableAccount->id,
                            'credit' => $grandTotal,
                            'description' => "Accounts Payable Vendor Invoice {$invNo}",
                        ],
                    ], [
                        'tenant_id' => $tenantId,
                        'source' => 'purchase',
                        'reference_type' => VendorBill::class,
                        'reference_id' => $bill->id,
                        'memo' => "Vendor Bill {$bill->bill_number} against GRN {$grnNo}",
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Accounting Journal Posting Exception on Vendor Bill: ' . $e->getMessage());
            }
        });

        return redirect()->route('purchase.bills.show', $bill->id)->with('success', "Vendor Bill {$bill->bill_number} posted from GRN {$grn->grn_number} successfully!");
    }

    /**
     * Show Vendor Bill Details & Allocations
     */
    public function show($id)
    {
        $tenantId = require_tenant_id();

        $bill = VendorBill::where('tenant_id', $tenantId)
            ->with(['vendor', 'goodsReceiptNote', 'purchaseOrder', 'items.product', 'allocations.payment', 'creator'])
            ->findOrFail($id);

        return view('modules.purchase.bills.show', compact('bill'));
    }
}
