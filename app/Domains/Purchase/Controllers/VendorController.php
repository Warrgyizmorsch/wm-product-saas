<?php

namespace App\Domains\Purchase\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $totalCount = Vendor::where('tenant_id', $tenantId)->count();
        $activeCount = Vendor::where('tenant_id', $tenantId)->whereIn('status', ['active', 'Active'])->count();
        $inactiveCount = Vendor::where('tenant_id', $tenantId)->whereIn('status', ['inactive', 'Inactive'])->count();

        // Calculate total outstanding payable across all vendors
        $totalOutstandingPayable = (float) VendorBill::where('tenant_id', $tenantId)
            ->whereIn('status', ['posted', 'unpaid', 'partially_paid', 'approved'])
            ->sum('due_amount');

        $query = Vendor::query()->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        $status = strtolower($request->input('status', 'all'));
        if ($status !== 'all') {
            $query->whereIn('status', [$status, ucfirst($status)]);
        }

        $sort = $request->input('sort_by', 'created_at');
        $direction = $request->input('sort_order', 'desc');
        $query->orderBy($sort, $direction);

        $vendors = $query->paginate(15)->withQueryString();

        return view('modules.purchase.vendors.index', compact(
            'vendors',
            'totalCount',
            'activeCount',
            'inactiveCount',
            'totalOutstandingPayable'
        ));
    }

    public function create(): View
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;
        $nextCount = Vendor::where('tenant_id', $tenantId)->count() + 1;
        $autoCode = 'VEND-' . str_pad((string)$nextCount, 4, '0', STR_PAD_LEFT);
        $paymentTerms = \App\Domains\Platform\Models\PaymentTerm::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('due_days', 'asc')
            ->get();

        return view('modules.purchase.vendors.create', compact('autoCode', 'paymentTerms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->where(fn ($q) => $q->where('tenant_id', $tenantId))
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:active,inactive,Active,Inactive'],
        ]);

        if (empty($validated['code'])) {
            $nextCount = Vendor::where('tenant_id', $tenantId)->count() + 1;
            $validated['code'] = 'VEND-' . str_pad((string)$nextCount, 4, '0', STR_PAD_LEFT);
        }

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = strtolower($validated['status']);
        $validated['opening_balance'] = $validated['opening_balance'] ?? 0.00;

        $vendor = Vendor::create($validated);

        return redirect()
            ->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' created successfully.");
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
        ]);

        $nextCount = Vendor::where('tenant_id', $tenantId)->count() + 1;
        $validated['code'] = 'VEND-' . str_pad((string)$nextCount, 4, '0', STR_PAD_LEFT);
        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'active';

        $vendor = Vendor::create($validated);

        return response()->json([
            'id' => $vendor->id,
            'name' => $vendor->name . ($vendor->code ? " ({$vendor->code})" : ''),
            'code' => $vendor->code,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'gstin' => $vendor->gstin,
            'address' => $vendor->address,
        ]);
    }

    public function show(Vendor $vendor): View
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $bills = VendorBill::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->latest()
            ->get();

        $payments = VendorPayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->with('allocations.bill')
            ->latest()
            ->get();

        $purchaseOrders = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->with('items')
            ->latest()
            ->get();

        $totalBilled = (float) $bills->where('status', '!=', 'cancelled')->sum(fn ($b) => $b->total_amount);
        $totalPaid = (float) $bills->where('status', '!=', 'cancelled')->sum(fn ($b) => $b->amount_paid);
        if ($totalPaid == 0 && $payments->isNotEmpty()) {
            $totalPaid = (float) $payments->where('status', 'completed')->sum('amount');
        }

        $netOutstanding = $totalBilled - $totalPaid;
        $outstandingPayable = max(0, $netOutstanding);
        $vendorAdvanceCredit = $netOutstanding < 0 ? abs($netOutstanding) : 0;

        $overdueAmount = (float) $bills->whereIn('status', ['posted', 'unpaid', 'partially_paid'])
            ->filter(fn ($b) => $b->due_date && \Carbon\Carbon::parse($b->due_date)->isPast())
            ->sum(fn ($b) => $b->balance_due);

        // Build Ledger Entries Array for Account Payable Statement
        $ledgerEntries = collect();

        foreach ($bills as $bill) {
            $ledgerEntries->push([
                'date' => $bill->bill_date ?? $bill->created_at,
                'reference' => 'Bill #' . ($bill->bill_number ?? $bill->id),
                'type' => 'Vendor Bill',
                'description' => 'Challan/Inv #' . ($bill->vendor_invoice_number ?? 'N/A'),
                'credit' => (float)$bill->total_amount, // Credit increases liability/payable
                'debit' => 0.00,
                'status' => $bill->status,
                'url' => route('purchase.bills.show', $bill->id),
            ]);
        }

        foreach ($payments as $pmt) {
            $ledgerEntries->push([
                'date' => $pmt->payment_date ?? $pmt->created_at,
                'reference' => 'Payment #' . ($pmt->payment_number ?? $pmt->id),
                'type' => 'Vendor Payment',
                'description' => 'Payment Method: ' . ucfirst($pmt->payment_method ?? 'Bank/Cash'),
                'credit' => 0.00,
                'debit' => (float)$pmt->amount, // Debit decreases liability/payable
                'status' => $pmt->status,
                'url' => route('purchase.payments.show', $pmt->id),
            ]);
        }

        $ledgerEntries = $ledgerEntries->sortBy('date')->values();

        // Compute Running Payable Balance
        $runningBalance = (float)($vendor->opening_balance ?? 0.00);
        $ledgerWithBalance = $ledgerEntries->map(function ($entry) use (&$runningBalance) {
            $runningBalance += ($entry['credit'] - $entry['debit']);
            $entry['running_balance'] = $runningBalance;
            return $entry;
        });

        return view('modules.purchase.vendors.show', compact(
            'vendor',
            'bills',
            'payments',
            'purchaseOrders',
            'totalBilled',
            'totalPaid',
            'outstandingPayable',
            'vendorAdvanceCredit',
            'overdueAmount',
            'ledgerWithBalance'
        ));
    }

    public function edit(Vendor $vendor): View
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;
        $paymentTerms = \App\Domains\Platform\Models\PaymentTerm::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('due_days', 'asc')
            ->get();

        return view('modules.purchase.vendors.edit', compact('vendor', 'paymentTerms'));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->ignore($vendor->id)->where(fn ($q) => $q->where('tenant_id', $tenantId))
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'gstin' => ['nullable', 'string', 'max:30'],
            'pan' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:active,inactive,Active,Inactive'],
        ]);

        $validated['status'] = strtolower($validated['status']);
        $vendor->update($validated);

        return redirect()
            ->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' updated successfully.");
    }

    public function toggleStatus(Request $request, Vendor $vendor): RedirectResponse
    {
        $newStatus = strtolower($vendor->status) === 'active' ? 'inactive' : 'active';
        $vendor->update(['status' => $newStatus]);

        $statusLabel = ucfirst($newStatus);

        return redirect()
            ->back()
            ->with('success', "Vendor '{$vendor->name}' marked as {$statusLabel} successfully.");
    }
}
