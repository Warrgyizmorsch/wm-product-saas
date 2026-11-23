<?php

namespace App\Domains\CRM\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerService;
use App\Exports\CustomerExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\PartyDuplicateService;
use App\Services\PartyStatusException;
use Illuminate\Validation\ValidationException;
use App\Services\PartyStatusService;
use App\Services\PartyVerificationService;
use App\Services\TaxIdentityService;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $countsQuery = Customer::where('tenant_id', $tenantId);

        $totalCount = (clone $countsQuery)->count();
        $activeCount = (clone $countsQuery)->whereIn('status', ['active', 'Active'])->count();
        $inactiveCount = (clone $countsQuery)->whereIn('status', ['inactive', 'Inactive'])->count();

        $query = Customer::query()->where('tenant_id', $tenantId)->with('crmAccount');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
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

        $customers = $query->paginate(10)->withQueryString();

        return view('modules.crm.customers.index', compact('customers', 'totalCount', 'activeCount', 'inactiveCount'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('modules.crm.customers.create');
    }

    /** Live "does this party already exist?" lookup used by the create form (FRS 2.3). */
    public function duplicateCheck(Request $request): JsonResponse
    {
        // The create form needs the create right; the edit form (ignore_id) needs the right to update that customer.
        if ($ignoreId = $request->integer('ignore_id')) {
            $this->authorize('update', Customer::findOrFail($ignoreId));
        } else {
            $this->authorize('create', Customer::class);
        }

        $matches = app(PartyDuplicateService::class)->find(
            PartyDuplicateService::CUSTOMER,
            $request->only(['name', 'company_name', 'email', 'phone', 'gstin', 'pan', 'billing_address']),
            null,
            $request->integer('ignore_id') ?: null
        );

        return response()->json(['duplicates' => $matches]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin' => TaxIdentityService::gstinRules(false),
            'pan' => TaxIdentityService::panRules(false),
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ], [
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max' => 'Phone number cannot exceed 20 digits.',
            'account_number.regex' => 'Bank account number must contain digits/numbers only.',
        ]);

        // FRS 2.3: search existing customers first; the user must confirm to create a look-alike.
        $duplicates = app(PartyDuplicateService::class)->find(PartyDuplicateService::CUSTOMER, $validated, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicate_matches', $duplicates);
        }

        $validated['opening_balance'] = isset($validated['opening_balance'])
            ? convert_to_base((float) $validated['opening_balance'])
            : 0.00;
        $validated['tenant_id'] = $tenantId;

        // FRS 2.3: a new customer starts pending and inactive until a second person verifies it.
        $verification = app(PartyVerificationService::class);
        $validated = array_merge($validated, $verification->pendingAttributes());

        $customer = $this->customers->create($validated);
        $verification->recordSubmission($customer);

        $openingWarning = app(\App\Domains\CRM\Services\CustomerOpeningBalanceService::class)->trySync($customer);

        return redirect()
            ->route('crm.customers.index')
            ->with('success', $customer->isVerified()
                ? 'Customer created successfully.'
                : 'Customer created. It stays inactive until a second person verifies its GSTIN, PAN and bank details.')
            ->with('warning', $openingWarning);
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin' => TaxIdentityService::gstinRules(false),
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
        ], [
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max' => 'Phone number cannot exceed 20 digits.',
        ]);

        // FRS 2.3: the modal cannot ask for confirmation, so an existing customer with the same
        // GSTIN, email or mobile is refused here and the user picks that customer instead.
        $existing = app(PartyDuplicateService::class);
        $identityMatches = $existing->identityMatches($existing->find(PartyDuplicateService::CUSTOMER, $validated, $tenantId));
        if ($identityMatches !== [] && ! $request->boolean('confirm_duplicate')) {
            throw ValidationException::withMessages([
                'name' => 'This customer may already exist: ' . $existing->summary($identityMatches) . '. Select the existing customer instead.',
            ]);
        }

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'active';
        $verification = app(PartyVerificationService::class);
        $validated = array_merge($validated, $verification->pendingAttributes());
        $customer = $this->customers->create($validated);
        $verification->recordSubmission($customer);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name . ($customer->email ? " ({$customer->email})" : ''),
            'email' => $customer->email,
            'phone' => $customer->phone,
            'billing_address' => $customer->billing_address,
            'shipping_address' => $customer->shipping_address,
        ]);
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['crmAccount.contacts', 'crmAccount.owner', 'crmAccount.deals.quotations']);

        $invoices = $customer->invoices()->with(['salesOrder.quotation.deal', 'customer'])->latest()->get();
        $payments = $customer->payments()->with(['allocations.invoice', 'allocations.salesOrder'])->latest()->get();
        $salesOrders = $customer->salesOrders()->with(['quotation.deal', 'items'])->latest()->get();
        $salesReturns = \App\Domains\Sales\Models\SalesReturn::where('customer_id', $customer->id)
            ->with(['salesOrder.quotation.deal'])
            ->latest()
            ->get();

        $deals = $customer->deals()->get();
        $openDealsCount = $deals->whereNotIn('stage', ['Won', 'Closed Won', 'Lost', 'Closed Lost'])->count();
        $wonDealsCount = $deals->whereIn('stage', ['Won', 'Closed Won'])->count();
        $lostDealsCount = $deals->whereIn('stage', ['Lost', 'Closed Lost'])->count();
        $pipelineValue = (float) $deals->whereNotIn('stage', ['Lost', 'Closed Lost'])->sum(fn($d) => $d->actual_value ?: $d->estimated_value);

        $totalBilled = $invoices->where('status', '!=', 'cancelled')->sum('total_amount');
        $totalPaid = $invoices->where('status', '!=', 'cancelled')->sum('amount_paid');
        if ($totalPaid == 0 && $payments->isNotEmpty()) {
            $totalPaid = $payments->where('status', 'completed')->sum('amount');
        }
        $totalReturns = $salesReturns->where('status', '!=', 'cancelled')->sum(function ($r) {
            return floatval($r->total_amount ?: $r->total_refund_amount);
        });

        $openingBalance = $customer->signedOpeningBalance();
        $netOutstanding = $openingBalance + $totalBilled - $totalPaid - $totalReturns;
        $outstandingBalance = max(0, $netOutstanding);
        $customerCreditBalance = $netOutstanding < 0 ? abs($netOutstanding) : 0;

        $overdueAmount = $invoices->where('status', 'unpaid')
            ->filter(function ($inv) {
                return $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->isPast();
            })->sum('balance_due');

        // With Accounting, the figures come from the books, so this page agrees
        // with Party Ledger, ageing and Bill Settlement (opening dues, receipt
        // vouchers and settlements included, not only Sales documents).
        $partyAccount = app(\App\Domains\Accounting\Services\PartyAccountService::class);
        if ($partyAccount->available()) {
            $summary = $partyAccount->summary('customer', (int) $customer->id);
            $totalBilled = $summary['billed'];
            $outstandingBalance = $summary['outstanding'];
            $customerCreditBalance = $summary['advance'];
            $overdueAmount = $summary['overdue'];
        }

        $creditLimit = floatval($customer->crmAccount?->credit_limit ?? 0);
        $availableCredit = max(0, $creditLimit - $outstandingBalance);

        return view('modules.crm.customers.show', compact(
            'customer',
            'invoices',
            'payments',
            'salesOrders',
            'salesReturns',
            'deals',
            'openDealsCount',
            'wonDealsCount',
            'lostDealsCount',
            'pipelineValue',
            'totalBilled',
            'totalPaid',
            'totalReturns',
            'outstandingBalance',
            'customerCreditBalance',
            'overdueAmount',
            'creditLimit',
            'availableCredit'
        ));
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);
        $customer->load('crmAccount');
        return view('modules.crm.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'gstin' => TaxIdentityService::gstinRules(false),
            'pan' => TaxIdentityService::panRules(false),
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:30'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ], [
            'phone.regex' => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'phone.max' => 'Phone number cannot exceed 20 digits.',
            'account_number.regex' => 'Bank account number must contain digits/numbers only.',
        ]);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        // FRS 2.3: if a name, GSTIN, PAN, email, mobile or address was changed to match another customer, ask for confirmation
        $duplicates = app(PartyDuplicateService::class)->findOnUpdate(PartyDuplicateService::CUSTOMER, $customer, $validated, $tenantId);
        if ($duplicates !== [] && ! $request->boolean('confirm_duplicate')) {
            return back()->withInput()->with('duplicate_matches', $duplicates);
        }

        if (array_key_exists('opening_balance', $validated)) {
            $validated['opening_balance'] = $validated['opening_balance'] !== null
                ? convert_to_base((float) $validated['opening_balance'])
                : 0.00;
        }

        // FRS 2.3: changing GSTIN/PAN/bank details on a verified customer sends it
        // back to pending, and a party that is not verified can never be set active.
        // FRS 2.3 (C-16): a verified customer's status changes only through the reason form,
        // so whatever the form posts, keep the current status here.
        if (app(PartyStatusService::class)->enabled() && $customer->isVerified()) {
            $validated['status'] = $customer->status;
        }

        $verification = app(PartyVerificationService::class);
        $wasVerified = $customer->isVerified();
        $blocked = $validated['status'] === 'active' ? $verification->activationBlockedMessage($customer) : null;
        $validated = array_merge($validated, $verification->attributesForUpdate($customer, $validated));

        $customer->update($validated);

        if ($customer->crmAccount) {
            $customer->crmAccount->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'status' => $customer->status,
            ]);
        }

        $openingWarning = app(\App\Domains\CRM\Services\CustomerOpeningBalanceService::class)->trySync($customer);

        if ($wasVerified && ! $customer->isVerified()) {
            $blocked = "'{$customer->name}' tax or bank details changed, so it is inactive until a second person verifies them again.";
        }

        return redirect()
            ->route('crm.customers.show', $customer)
            ->with('success', 'Customer updated successfully.')
            ->with('warning', $blocked ?: $openingWarning);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,Active,Inactive'],
            'reason' => ['nullable', 'string'],
            'inactive_reason' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'inactive_note' => ['nullable', 'string'],
        ]);

        $newStatus = strtolower($validated['status']);

        if ($newStatus === 'active'
            && ($message = app(PartyVerificationService::class)->activationBlockedMessage($customer))) {
            return redirect()->back()->with('error', $message);
        }

        if ($newStatus === 'active' && $reactivated = $this->reactivateIfInactive($request, $customer)) {
            return $reactivated;
        }

        if ($newStatus === 'inactive') {
            $reason = $request->input('reason', $request->input('inactive_reason'));
            $note = $request->input('note', $request->input('inactive_note'));
            $statusService = app(PartyStatusService::class);
            if ($statusService->enabled() && $customer->isVerified()) {
                if (! $reason) {
                    return redirect()->route('crm.customers.deactivate.create', $customer)
                        ->with('warning', 'Please provide a reason to mark the customer as inactive.');
                }
                try {
                    $statusService->deactivate($customer, $request->user(), $reason, $note);
                    return redirect()->back()->with('success', "Customer '{$customer->name}' marked as Inactive successfully.");
                } catch (PartyStatusException $e) {
                    return redirect()->back()->with('error', $e->getMessage());
                }
            }
        }

        $customer->update([
            'status' => $newStatus,
        ]);

        $statusLabel = $newStatus === 'active' ? 'Active' : 'Inactive';

        return redirect()
            ->back()
            ->with('success', "Customer '{$customer->name}' marked as {$statusLabel} successfully.");
    }

    public function toggleStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $newStatus = strtolower($request->input('status', 'inactive'));
        if (!in_array($newStatus, ['active', 'inactive'])) {
            $newStatus = 'inactive';
        }

        if ($newStatus === 'active'
            && ($message = app(PartyVerificationService::class)->activationBlockedMessage($customer))) {
            return redirect()->back()->with('error', $message);
        }

        if ($newStatus === 'active' && $reactivated = $this->reactivateIfInactive($request, $customer)) {
            return $reactivated;
        }

        if ($newStatus === 'inactive') {
            $statusService = app(PartyStatusService::class);
            if ($statusService->enabled() && $customer->isVerified()) {
                $reason = $request->input('reason', $request->input('inactive_reason'));
                $note = $request->input('note', $request->input('inactive_note'));
                if (! $reason) {
                    return redirect()->route('crm.customers.deactivate.create', $customer)
                        ->with('warning', 'Please provide a reason to mark the customer as inactive.');
                }
                try {
                    $statusService->deactivate($customer, $request->user(), $reason, $note);
                    return redirect()->back()->with('success', "Customer '{$customer->name}' marked as Inactive successfully.");
                } catch (PartyStatusException $e) {
                    return redirect()->back()->with('error', $e->getMessage());
                }
            }
        }

        $customer->update([
            'status' => $newStatus,
        ]);

        $statusLabel = $newStatus === 'active' ? 'Active' : 'Inactive';

        return redirect()
            ->back()
            ->with('success', "Customer '{$customer->name}' marked as {$statusLabel} successfully.");
    }

    /**
     * Activating an inactive customer goes through PartyStatusService, which applies the
     * risk-reason rule (a different authorised person) and records the change.
     * Returns a redirect when it handled the request, or null when nothing needs doing.
     */
    private function reactivateIfInactive(Request $request, Customer $customer): ?RedirectResponse
    {
        $status = app(PartyStatusService::class);

        if (! $status->enabled() || $status->isActive($customer)) {
            return null;
        }

        try {
            $status->reactivate($customer, $request->user());
        } catch (PartyStatusException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Customer '{$customer->name}' marked as Active successfully.");
    }

    /**
     * Export Customers to Excel with customizable columns and applied filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? auth()->user()->tenant_id ?? 1;

        return Excel::download(
            new CustomerExport($tenantId, $request->all()),
            'customers_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }
}
