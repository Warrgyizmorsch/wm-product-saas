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
use App\Services\PartyBankChangeService;
use App\Services\PartyDuplicateService;
use App\Services\PartyStatusException;
use Illuminate\Validation\ValidationException;
use App\Services\PartyStatusService;
use App\Services\PartyVerificationService;
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

    /** Live "does this party already exist?" lookup used by the create and edit forms (FRS 2.3). */
    public function duplicateCheck(Request $request): JsonResponse
    {
        // The create form needs the create right; the edit form (ignore_id) needs the edit right.
        $this->authorizePurchase($request->integer('ignore_id') ? 'purchase.vendors.edit' : 'purchase.vendors.create');

        $matches = app(PartyDuplicateService::class)->find(
            PartyDuplicateService::VENDOR,
            $request->only(['name', 'company_name', 'email', 'phone', 'gstin', 'pan', 'billing_address', 'address']),
            null,
            $request->integer('ignore_id') ?: null
        );

        return response()->json(['duplicates' => $matches]);
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

        // FRS 2.3: search existing vendors first; the user must confirm to create a look-alike.
        $duplicates = app(PartyDuplicateService::class)->find(PartyDuplicateService::VENDOR, $validated, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicate_matches', $duplicates);
        }

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

        // FRS 2.3: a new vendor starts pending and inactive until a second person verifies it.
        $verification = app(PartyVerificationService::class);
        $validated = array_merge($validated, $verification->pendingAttributes());

        $vendor = Vendor::create($validated);
        $verification->recordSubmission($vendor);

        $openingWarning = app(\App\Domains\Purchase\Services\VendorOpeningBalanceService::class)->trySync($vendor);

        return redirect()
            ->route('purchase.vendors.show', $vendor->id)
            ->with('success', $vendor->isVerified()
                ? "Vendor '{$vendor->name}' created successfully."
                : "Vendor '{$vendor->name}' created. It stays inactive until a second person verifies its GSTIN, PAN and bank details.")
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

        // FRS 2.3: the modal cannot ask for confirmation, so an existing vendor with the same
        // GSTIN, email or mobile is refused here and the user picks that vendor instead.
        $existing = app(PartyDuplicateService::class);
        $identityMatches = $existing->identityMatches($existing->find(PartyDuplicateService::VENDOR, $validated, $tenantId));
        if ($identityMatches !== [] && ! $request->boolean('confirm_duplicate')) {
            throw ValidationException::withMessages([
                'name' => 'This vendor may already exist: ' . $existing->summary($identityMatches) . '. Select the existing vendor instead.',
            ]);
        }

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

        $verification = app(PartyVerificationService::class);
        $validated = array_merge($validated, $verification->pendingAttributes());

        $vendor = Vendor::create($validated);
        $verification->recordSubmission($vendor);

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

        $advances = \App\Domains\Purchase\Models\PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendor->id)
            ->with(['purchaseOrder', 'bankAccount'])
            ->latest()
            ->get();

        $totalAdvancePaid = (float) $advances->where('status', '!=', 'cancelled')->sum('amount');
        $availableAdvance = app(\App\Domains\Purchase\Services\VendorBillService::class)->getAvailableVendorAdvance($vendor->id, $tenantId);

        // With Accounting, the figures come from the books, so this page agrees
        // with Party Ledger and Bill Settlement: assets bought on credit,
        // vouchers and settlements count, not only Purchase's own documents.
        $billCount = $bills->count();
        $statement = null;
        $partyAccount = app(\App\Domains\Accounting\Services\PartyAccountService::class);
        if ($partyAccount->available()) {
            $summary = $partyAccount->summary('vendor', (int) $vendor->id);
            $totalBilled = $summary['billed'];
            $totalPaid = max($totalPaid, (float) $summary['paid']);
            $billCount = $summary['bills'] ?: $bills->count();
            $outstandingPayable = $summary['outstanding'];
            $vendorAdvanceCredit = max((float) $summary['advance'], $availableAdvance);
            $overdueAmount = $summary['overdue'];
            $statement = $partyAccount->statement('vendor', (int) $vendor->id);
        } else {
            $vendorAdvanceCredit = max($vendorAdvanceCredit, $availableAdvance);
        }

        $allProducts = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name', 'sku', 'unit_cost', 'cost_price', 'minimum_order_qty']);
        $vendorItems = $vendor->vendorItems()->with(['product', 'latestPriceHistory'])->get();
        $vendorDocuments = $vendor->vendorDocuments()->latest()->get();
        $expiredDocs = $vendor->expiredDocuments();
        $expiringDocs = $vendor->expiringDocuments(30);

        return view('modules.purchase.vendors.show', compact(
            'billCount',
            'statement',
            'vendor',
            'bills',
            'payments',
            'advances',
            'purchaseOrders',
            'totalBilled',
            'totalPaid',
            'totalAdvancePaid',
            'availableAdvance',
            'outstandingPayable',
            'vendorAdvanceCredit',
            'overdueAmount',
            'ledgerWithBalance',
            'allProducts',
            'vendorItems',
            'vendorDocuments',
            'expiredDocs',
            'expiringDocs'
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

        // FRS 2.3: if a name, GSTIN, PAN, email, mobile or address was changed to match another vendor, ask for confirmation
        $duplicates = app(PartyDuplicateService::class)->findOnUpdate(PartyDuplicateService::VENDOR, $vendor, $validated, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicate_matches', $duplicates);
        }

        if (array_key_exists('opening_balance', $validated)) {
            $validated['opening_balance'] = $validated['opening_balance'] !== null
                ? convert_to_base((float) $validated['opening_balance'])
                : 0.00;
        }

        // FRS 2.3: changing GSTIN/PAN/bank details on a verified vendor sends it back to
        // pending, and a vendor that is not verified can never be set active.
        // Bank details of a verified vendor change only through a callback-verified request.
        $bankChange = app(PartyBankChangeService::class);
        [$validated, $bankAttempted] = $bankChange->stripProtectedFields($vendor, $validated);

        // FRS 2.3 (C-16): a verified vendor's status changes only through the reason form,
        // so whatever the form posts, keep the current status here.
        if (app(PartyStatusService::class)->enabled() && $vendor->isVerified()) {
            $validated['status'] = strtolower($vendor->status);
        }

        $verification = app(PartyVerificationService::class);
        $wasVerified = $vendor->isVerified();
        $blocked = $validated['status'] === 'active' ? $verification->activationBlockedMessage($vendor) : null;
        $validated = array_merge($validated, $verification->attributesForUpdate($vendor, $validated));

        $vendor->update($validated);

        $openingWarning = app(\App\Domains\Purchase\Services\VendorOpeningBalanceService::class)->trySync($vendor);

        if ($wasVerified && ! $vendor->isVerified()) {
            $blocked = "Vendor '{$vendor->name}' tax or bank details changed, so it is inactive until a second person verifies them again.";
        }

        if ($bankAttempted !== []) {
            $blocked = 'Bank details were not changed: they can only be changed with a bank change request that a different person approves after a callback.';
        }

        return redirect()
            ->route('purchase.vendors.show', $vendor->id)
            ->with('success', "Vendor '{$vendor->name}' updated successfully.")
            ->with('warning', $blocked ?: $openingWarning);
    }

    public function toggleStatus(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->authorizePurchase('purchase.vendors.edit');

        $newStatus = strtolower($vendor->status) === 'active' ? 'inactive' : 'active';

        if ($newStatus === 'active'
            && ($message = app(PartyVerificationService::class)->activationBlockedMessage($vendor))) {
            return redirect()->back()->with('error', $message);
        }

        if ($newStatus === 'active') {
            $statusService = app(PartyStatusService::class);

            if ($statusService->enabled() && ! $statusService->isActive($vendor)) {
                try {
                    $statusService->reactivate($vendor, $request->user());
                } catch (PartyStatusException $e) {
                    return redirect()->back()->with('error', $e->getMessage());
                }

                return redirect()->back()->with('success', "Vendor '{$vendor->name}' marked as Active successfully.");
            }
        }

        if ($newStatus === 'inactive') {
            $statusService = app(PartyStatusService::class);
            if ($statusService->enabled() && $vendor->isVerified()) {
                $reason = $request->input('reason', $request->input('inactive_reason'));
                $note = $request->input('note', $request->input('inactive_note'));
                if (! $reason) {
                    return redirect()->route('purchase.vendors.deactivate.create', $vendor)
                        ->with('warning', 'Please provide a reason to mark the vendor as inactive.');
                }
                try {
                    $statusService->deactivate($vendor, $request->user(), $reason, $note);
                    return redirect()->back()->with('success', "Vendor '{$vendor->name}' marked as Inactive successfully.");
                } catch (PartyStatusException $e) {
                    return redirect()->back()->with('error', $e->getMessage());
                }
            }
        }

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
