<?php

namespace App\Domains\Purchase\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use App\Exports\VendorExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\TaxIdentityService;

class VendorController extends Controller
{
    /**
     * Export Vendors to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorizePurchase('purchase.vendors.view');
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;

        return Excel::download(
            new VendorExport($tenantId, $request->all()),
            'vendors_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function index(Request $request): View
    {
        $this->authorizePurchase('purchase.vendors.view');

        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $totalCount = Vendor::where('tenant_id', $tenantId)->count();
        $activeCount = Vendor::where('tenant_id', $tenantId)->whereIn('status', ['active', 'Active'])->count();
        $inactiveCount = Vendor::where('tenant_id', $tenantId)->whereIn('status', ['inactive', 'Inactive'])->count();

        // Calculate total outstanding payable across all vendors (normalized in base USD in DB, convert to active currency at fetch time)
        $rawOutstandingPayableUSD = (float) VendorBill::where('tenant_id', $tenantId)
            ->whereIn('status', ['posted', 'unpaid', 'partially_paid', 'approved'])
            ->sum('due_amount');

        // With Accounting, the books' figure: opening dues, credit purchases and
        // vouchers count too, so it adds up to the suppliers' own pages.
        $partyAccount = app(\App\Domains\Accounting\Services\PartyAccountService::class);
        if ($partyAccount->available()) {
            $rawOutstandingPayableUSD = $partyAccount->totalOutstanding('vendor');
        }

        $totalOutstandingPayable = function_exists('convert_from_base')
            ? convert_from_base($rawOutstandingPayableUSD)
            : $rawOutstandingPayableUSD;

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
        $this->authorizePurchase('purchase.vendors.create');

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
        $this->authorizePurchase('purchase.vendors.create');

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();
        $companyId = company_id() ?? auth()->user()?->company_id;
        $branchId = branch_id() ?? auth()->user()?->branch_id;

        $uniqueScope = $this->scopeUniqueVendor((int) $tenantId, $companyId, $branchId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vendors', 'name')->where($uniqueScope),
            ],
            'company_name' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->where($uniqueScope),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'gstin' => TaxIdentityService::gstinRules(false),
            'pan' => TaxIdentityService::panRules(false),
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'status' => ['required', 'string', 'in:active,inactive,Active,Inactive'],
            ...\App\Domains\Purchase\Services\VendorBillTdsService::vendorRules(),
        ], [
            'email.email'          => 'The email field must be a valid email address.',
            'phone.regex'          => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'            => 'Phone number cannot exceed 20 digits.',
            'account_number.regex' => 'Bank account number must contain digits/numbers only.',
            'tds_section_id.required_if' => 'Pick the TDS section to deduct under.',
        ]);

        if (empty($validated['code'])) {
            $counter = Vendor::where('tenant_id', $tenantId)->count() + 1;
            do {
                $candidateCode = 'VEND-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
                $exists = Vendor::where('tenant_id', $tenantId)
                    ->where('code', $candidateCode)
                    ->exists();
                $counter++;
            } while ($exists);
            $validated['code'] = $candidateCode;
        }

        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = $companyId;
        $validated['branch_id'] = $branchId;
        $validated['status'] = strtolower($validated['status']);
        $validated['opening_balance'] = isset($validated['opening_balance'])
            ? convert_to_base((float) $validated['opening_balance'])
            : 0.00;

        $vendor = Vendor::create($validated);

        $openingWarning = app(\App\Domains\Purchase\Services\VendorOpeningBalanceService::class)->trySync($vendor);

        return redirect()
            ->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' created successfully.")
            ->with('warning', $openingWarning);
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;
        $companyId = company_id() ?? auth()->user()?->company_id;
        $branchId = branch_id() ?? auth()->user()?->branch_id;

        $uniqueScope = $this->scopeUniqueVendor((int) $tenantId, $companyId, $branchId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vendors', 'name')->where($uniqueScope),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->where($uniqueScope),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin' => TaxIdentityService::gstinRules(false),
            'address' => ['nullable', 'string'],
        ], [
            'email.email' => 'The email field must be a valid email address.',
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'   => 'Phone number cannot exceed 20 digits.',
        ]);

        $counter = Vendor::where('tenant_id', $tenantId)->count() + 1;
        do {
            $candidateCode = 'VEND-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            $exists = Vendor::where('tenant_id', $tenantId)
                ->where('code', $candidateCode)
                ->exists();
            $counter++;
        } while ($exists);

        $validated['code'] = $candidateCode;
        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = $companyId;
        $validated['branch_id'] = $branchId;
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
        $this->authorizePurchase('purchase.vendors.view');

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

        $openingBalance = $vendor->signedOpeningBalance();

        $totalBilled = (float) $bills->where('status', '!=', 'cancelled')->sum(fn ($b) => $b->total_amount);
        // Every payment made, not just what was applied to bills: a payment
        // against the opening balance or on account has no bill to sit on.
        $totalPaid = (float) $payments
            ->reject(fn ($p) => in_array(strtolower((string) $p->status), ['cancelled', 'void', 'draft', 'rejected'], true))
            ->sum('amount');

        // Net outstanding includes Opening Balance (historical dues owed to supplier)
        $netOutstanding = $openingBalance + $totalBilled - $totalPaid;
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
                'entered_at' => $bill->created_at,
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
                'entered_at' => $pmt->created_at,
                'url' => route('purchase.payments.show', $pmt->id),
            ]);
        }

        // Same-day entries in the order they happened, not bills-then-payments.
        $ledgerEntries = $ledgerEntries->sortBy([
            fn ($a, $b) => \Carbon\Carbon::parse($a['date'])->startOfDay() <=> \Carbon\Carbon::parse($b['date'])->startOfDay(),
            fn ($a, $b) => $a['entered_at'] <=> $b['entered_at'],
        ])->values();

        // Compute Running Payable Balance
        $runningBalance = $vendor->signedOpeningBalance();
        $ledgerWithBalance = $ledgerEntries->map(function ($entry) use (&$runningBalance) {
            $runningBalance += ($entry['credit'] - $entry['debit']);
            $entry['running_balance'] = $runningBalance;
            return $entry;
        });

        // With Accounting, the figures come from the books, so this page agrees
        // with Party Ledger and Bill Settlement: assets bought on credit,
        // vouchers and settlements count, not only Purchase's own documents.
        $billCount = $bills->count();
        $statement = null;
        $partyAccount = app(\App\Domains\Accounting\Services\PartyAccountService::class);
        if ($partyAccount->available()) {
            $summary = $partyAccount->summary('vendor', (int) $vendor->id);
            $totalBilled = $summary['billed'];
            $totalPaid = $summary['paid'];
            $billCount = $summary['bills'];
            $outstandingPayable = $summary['outstanding'];
            $vendorAdvanceCredit = $summary['advance'];
            $overdueAmount = $summary['overdue'];
            $statement = $partyAccount->statement('vendor', (int) $vendor->id);
        }

        return view('modules.purchase.vendors.show', compact(
            'billCount',
            'statement',
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
        $this->authorizePurchase('purchase.vendors.edit');

        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;
        $paymentTerms = \App\Domains\Platform\Models\PaymentTerm::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('due_days', 'asc')
            ->get();

        return view('modules.purchase.vendors.edit', compact('vendor', 'paymentTerms'));
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizePurchase('purchase.vendors.edit');

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();
        $companyId = $vendor->company_id ?? company_id() ?? auth()->user()?->company_id;
        $branchId = $vendor->branch_id ?? branch_id() ?? auth()->user()?->branch_id;

        $uniqueScope = $this->scopeUniqueVendor((int) $tenantId, $companyId, $branchId);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vendors', 'name')->ignore($vendor->id)->where($uniqueScope),
            ],
            'company_name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('vendors', 'email')->ignore($vendor->id)->where($uniqueScope),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'address' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'gstin' => TaxIdentityService::gstinRules(false),
            'pan' => TaxIdentityService::panRules(false),
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'status' => ['required', 'string', 'in:active,inactive,Active,Inactive'],
            ...\App\Domains\Purchase\Services\VendorBillTdsService::vendorRules(),
        ], [
            'email.email'          => 'The email field must be a valid email address.',
            'phone.regex'          => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max'            => 'Phone number cannot exceed 20 digits.',
            'account_number.regex' => 'Bank account number must contain digits/numbers only.',
            'tds_section_id.required_if' => 'Pick the TDS section to deduct under.',
        ]);

        $validated['status'] = strtolower($validated['status']);
        if (array_key_exists('opening_balance', $validated)) {
            $validated['opening_balance'] = $validated['opening_balance'] !== null
                ? convert_to_base((float) $validated['opening_balance'])
                : 0.00;
        }
        $vendor->update($validated);

        $openingWarning = app(\App\Domains\Purchase\Services\VendorOpeningBalanceService::class)->trySync($vendor);

        return redirect()
            ->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' updated successfully.")
            ->with('warning', $openingWarning);
    }

    public function toggleStatus(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizePurchase('purchase.vendors.edit');

        $newStatus = strtolower($vendor->status) === 'active' ? 'inactive' : 'active';
        $vendor->update(['status' => $newStatus]);

        $statusLabel = ucfirst($newStatus);

        return redirect()
            ->back()
            ->with('success', "Vendor '{$vendor->name}' marked as {$statusLabel} successfully.");
    }

    private function authorizePurchase(string $permission): void
    {
        abort_unless(
            app(AccessService::class)->allows(auth()->user(), $permission, [
                'tenant_id' => auth()->user()?->tenant_id,
            ]),
            403
        );
    }

    private function scopeUniqueVendor(int $tenantId, ?int $companyId, ?int $branchId): \Closure
    {
        return function ($query) use ($tenantId, $companyId, $branchId) {
            $query->where('tenant_id', $tenantId);
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            } else {
                $query->whereNull('company_id');
            }
            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            } else {
                $query->whereNull('branch_id');
            }
            return $query;
        };
    }
}
