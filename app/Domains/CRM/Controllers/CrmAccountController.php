<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\Sales\Models\SalesOrder;
use App\Exports\AccountExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CrmAccountController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', CrmAccount::class);

        $tenantId = tenant_id() ?? 1;
        $search = $request->input('search');

        $query = CrmAccount::with(['contacts', 'deals', 'customer', 'owner'])
            ->where('tenant_id', $tenantId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%");
            });
        }

        $accounts = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        $users = \App\Models\User::orderBy('name')->get();

        return view('modules.crm.accounts.index', compact('accounts', 'search', 'users'));
    }

    public function create(): View
    {
        $this->authorize('create', CrmAccount::class);

        $users = \App\Models\User::orderBy('name')->get();
        return view('modules.crm.accounts.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', CrmAccount::class);

        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate($this->getAccountValidationRules(), $this->getAccountValidationMessages());

        $openingBalance = !empty($validated['opening_balance']) ? convert_to_base((float)$validated['opening_balance']) : 0.00;

        // 1. Sync or Create ERP Customer
        $customer = null;
        if (!empty($validated['email']) || !empty($validated['phone'])) {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($validated) {
                    if (!empty($validated['email'])) $q->where('email', $validated['email']);
                    if (!empty($validated['phone'])) $q->orWhere('phone', $validated['phone']);
                })->first();
        }

        if ($customer) {
            if ($openingBalance > 0 && (float)($customer->opening_balance ?? 0) == 0) {
                $customer->update([
                    'opening_balance' => $openingBalance,
                    'opening_balance_type' => $validated['opening_balance_type'] ?? 'debit',
                ]);
            }
        }

        if (!$customer) {
            Customer::$skipAccountAutoCreate = true;
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name'      => $validated['name'],
                'email'     => $validated['email'] ?? null,
                'phone'     => $validated['phone'] ?? null,
                'gstin'     => $validated['gstin'] ?? null,
                'billing_address' => $validated['street'] ?? null,
                'opening_balance' => $openingBalance,
                'opening_balance_type' => $validated['opening_balance_type'] ?? 'debit',
                'status'    => 'active',
            ]);
            Customer::$skipAccountAutoCreate = false;
        }

        $openingWarning = $customer
            ? app(\App\Domains\CRM\Services\CustomerOpeningBalanceService::class)->trySync($customer)
            : null;
        if ($openingWarning) {
            session()->flash('warning', $openingWarning);
        }

        // 2. Create CRM Account
        $account = CrmAccount::create([
            'tenant_id'     => $tenantId,
            'customer_id'   => $customer->id,
            'name'          => $validated['name'],
            'gstin'         => $validated['gstin'] ?? null,
            'email'         => $validated['email'] ?? null,
            'phone'         => $validated['phone'] ?? null,
            'website'       => $validated['website'] ?? null,
            'industry_type' => $validated['industry_type'] ?? null,
            'credit_limit'  => !empty($validated['credit_limit']) ? convert_to_base((float)$validated['credit_limit']) : 0.00,
            'street'        => $validated['street'] ?? null,
            'city'          => $validated['city'] ?? null,
            'state'         => $validated['state'] ?? null,
            'country'       => $validated['country'] ?? null,
            'zip_code'      => $validated['zip_code'] ?? null,
            'status'        => 'active',
            'owner_id'      => $validated['owner_id'] ?? (auth()->id() ?: 1),
        ]);

        // 3. Create Primary Contact if name provided
        $contact = null;
        if (!empty($validated['contact_name'])) {
            $contact = CrmContact::create([
                'tenant_id'      => $tenantId,
                'crm_account_id' => $account->id,
                'name'           => $validated['contact_name'],
                'designation'    => $validated['designation'] ?? null,
                'role'           => $validated['role'] ?? 'Purchase Decision Maker',
                'email'          => $validated['contact_email'] ?? $validated['email'] ?? null,
                'phone'          => $validated['contact_phone'] ?? $validated['phone'] ?? null,
                'mobile'         => $validated['contact_phone'] ?? null,
                'is_primary'     => true,
                'status'         => 'active',
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            $displayText = $account->name . ($account->account_number ? ' (' . $account->account_number . ')' : '');
            return response()->json([
                'success'        => true,
                'id'             => $account->id,
                'name'           => $displayText,
                'account_number' => $account->account_number,
                'customer_id'    => $customer->id,
                'primary_contact'=> $contact ? [
                    'id'          => $contact->id,
                    'name'        => $contact->name,
                    'designation' => $contact->designation,
                    'role'        => $contact->role,
                ] : null,
                'message'        => 'Account created successfully.'
            ]);
        }

        return redirect()->route('crm.accounts.show', $account)->with('success', 'Account created successfully.');
    }

    public function show(CrmAccount $account): View
    {
        $this->authorize('view', $account);

        $account->load(['contacts', 'deals.quotations', 'quotations', 'customer', 'owner']);

        // Deal metrics
        $openDealsCount = $account->deals->whereNotIn('stage', ['Closed Won', 'Closed Lost'])->count();
        $wonDealsCount = $account->deals->where('stage', 'Closed Won')->count();
        $lostDealsCount = $account->deals->where('stage', 'Closed Lost')->count();
        $pipelineValue = $account->deals->whereNotIn('stage', ['Closed Lost'])->sum('estimated_value');

        // Customer financial metrics & tab data
        $totalBilled = 0;
        $outstandingBalance = 0;
        $customerCreditBalance = 0;
        $overdueAmount = 0;
        $openingBalance = 0;
        $creditLimit = floatval($account->credit_limit ?? 0);
        $availableCredit = $creditLimit;
        $invoiceCount = 0;
        $invoices = collect();
        $payments = collect();
        $salesOrders = collect();
        $salesReturns = collect();
        $creditNotes = collect();
        $totalBilled = 0;
        $outstandingBalance = 0;
        $customerCreditBalance = 0;
        $overdueAmount = 0;
        $availableCredit = $creditLimit;
        $invoiceCount = 0;
        $openingBalance = 0;

        if ($account->customer) {
            $customer = $account->customer;
            $invoices = $customer->invoices()->with(['salesOrder.quotation.deal', 'customer'])->latest()->get();
            $payments = $customer->payments()->with(['allocations.invoice', 'allocations.salesOrder'])->latest()->get();
            $salesOrders = $customer->salesOrders()->with(['quotation.deal', 'items'])->latest()->get();
            $salesReturns = \App\Domains\Sales\Models\SalesReturn::where('customer_id', $customer->id)
                ->with(['salesOrder.quotation.deal', 'items'])
                ->latest()->get();

            $tenantId = $customer->tenant_id ?? (tenant_id() ?? 1);
            $creditNotes = \App\Domains\Accounting\Models\Journal::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('voucher_type', 'credit_note')
                ->where('status', 'posted')
                ->where(function ($q) use ($customer, $invoices) {
                    $q->whereHas('entries', function ($sq) use ($customer) {
                        $sq->where('party_type', 'customer')->where('party_id', $customer->id);
                    });
                    if ($invoices->isNotEmpty()) {
                        $q->orWhere(function ($sq) use ($invoices) {
                            $sq->where('reference_type', 'invoice')
                               ->whereIn('reference_id', $invoices->pluck('id'));
                        });
                    }
                })
                ->with(['entries.account'])
                ->latest('journal_date')
                ->get();

            // Filter active non-cancelled invoices
            $activeInvoices = $invoices->filter(fn($inv) => !in_array(strtolower((string)$inv->status), ['cancelled', 'void']));
            $invoiceCount = $activeInvoices->count();
            $totalBilled = (float) $activeInvoices->sum('total_amount');

            // Outstanding balance from active invoices
            $invoiceOutstanding = (float) $activeInvoices->sum('balance_due');

            // Total posted/completed payments
            $postedPayments = $payments->filter(fn($p) => in_array(strtolower((string)$p->status), ['posted', 'completed', 'paid']));

            // Unallocated advances (e.g. from cancelled invoices or direct unallocated payments)
            $unallocatedAdvance = (float) \App\Domains\Sales\Models\PaymentAllocation::whereHas('payment', function($q) use ($customer) {
                $q->where('customer_id', $customer->id)->whereIn('status', ['Posted', 'posted', 'completed', 'paid']);
            })->whereNull('invoice_id')->sum('allocated_amount');

            $paymentsWithoutAlloc = (float) $postedPayments->filter(fn($p) => $p->allocations->isEmpty())->sum('amount');
            $totalAdvanceHold = $unallocatedAdvance + $paymentsWithoutAlloc;

            $openingBalance = $customer->signedOpeningBalance();

            $netOutstanding = ($openingBalance + $invoiceOutstanding) - $totalAdvanceHold;

            if ($netOutstanding < 0) {
                $outstandingBalance = 0;
                $customerCreditBalance = abs($netOutstanding);
            } else {
                $outstandingBalance = $netOutstanding;
                $customerCreditBalance = ($totalAdvanceHold > 0 && $outstandingBalance == 0) ? $totalAdvanceHold : 0;
            }

            $overdueAmount = $activeInvoices->filter(fn($inv) => $inv->balance_due > 0 && $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->isPast())
                ->sum('balance_due');

            $availableCredit = max(0, $creditLimit - $outstandingBalance + $customerCreditBalance);
        }

        return view('modules.crm.accounts.show', compact(
            'account',
            'openDealsCount',
            'wonDealsCount',
            'lostDealsCount',
            'pipelineValue',
            'totalBilled',
            'outstandingBalance',
            'customerCreditBalance',
            'overdueAmount',
            'creditLimit',
            'availableCredit',
            'invoiceCount',
            'invoices',
            'payments',
            'salesOrders',
            'salesReturns',
            'creditNotes',
            'openingBalance'
        ));
    }


    public function edit(CrmAccount $account): View
    {
        $this->authorize('update', $account);

        $account->load(['primaryContact', 'customer']);
        $users = \App\Models\User::orderBy('name')->get();
        return view('modules.crm.accounts.edit', compact('account', 'users'));
    }

    public function update(Request $request, CrmAccount $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $tenantId = tenant_id() ?? 1;

        $rules = $this->getAccountValidationRules();
        $rules['status'] = 'required|string|in:active,inactive';

        $validated = $request->validate($rules, $this->getAccountValidationMessages());

        if (isset($validated['credit_limit'])) {
            $validated['credit_limit'] = !empty($validated['credit_limit']) ? convert_to_base((float) $validated['credit_limit']) : 0.00;
        }

        $account->update($validated);

        // Sync Primary Contact
        if (!empty($validated['contact_name']) || !empty($validated['contact_email']) || !empty($validated['contact_phone'])) {
            $primaryContact = $account->primaryContact;
            if ($primaryContact) {
                $primaryContact->update([
                    'name'        => $validated['contact_name'] ?? $primaryContact->name,
                    'designation' => $validated['designation'] ?? null,
                    'role'        => $validated['role'] ?? 'Purchase Decision Maker',
                    'email'       => $validated['contact_email'] ?? null,
                    'phone'       => $validated['contact_phone'] ?? null,
                    'mobile'      => $validated['contact_phone'] ?? null,
                ]);
            } else {
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'company_id'     => $account->company_id,
                    'branch_id'      => $account->branch_id,
                    'crm_account_id' => $account->id,
                    'name'           => $validated['contact_name'],
                    'designation'    => $validated['designation'] ?? null,
                    'role'           => $validated['role'] ?? 'Purchase Decision Maker',
                    'email'          => $validated['contact_email'] ?? null,
                    'phone'          => $validated['contact_phone'] ?? null,
                    'mobile'         => $validated['contact_phone'] ?? null,
                    'is_primary'     => true,
                    'status'         => 'active',
                ]);
            }
        }

        if ($account->customer_id) {
            $customerUpdate = [
                'name'  => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
            ];
            if (array_key_exists('opening_balance', $validated)) {
                $customerUpdate['opening_balance'] = $validated['opening_balance'] !== null
                    ? convert_to_base((float) $validated['opening_balance'])
                    : 0.00;
            }
            if (! empty($validated['opening_balance_type'])) {
                $customerUpdate['opening_balance_type'] = $validated['opening_balance_type'];
            }
            $customer = Customer::where('id', $account->customer_id)->first();
            if ($customer) {
                // If another customer already has this email, avoid breaking customers table unique constraint
                $otherCustomerWithEmail = Customer::where('tenant_id', $tenantId)
                    ->where('email', $customerUpdate['email'])
                    ->where('id', '!=', $customer->id)
                    ->exists();
                if ($otherCustomerWithEmail) {
                    unset($customerUpdate['email']);
                }
                $customer->update($customerUpdate);
                if ($openingWarning = app(\App\Domains\CRM\Services\CustomerOpeningBalanceService::class)->trySync($customer)) {
                    session()->flash('warning', $openingWarning);
                }
            }
        }

        return redirect()->route('crm.accounts.show', $account)->with('success', 'Account updated successfully.');
    }

    public function storeContact(Request $request, CrmAccount $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'role'        => 'nullable|string|max:100',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'mobile'      => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'is_primary'  => 'nullable|boolean',
        ], $this->getAccountValidationMessages());

        if (!empty($validated['is_primary'])) {
            CrmContact::where('crm_account_id', $account->id)->update(['is_primary' => false]);
        }

        CrmContact::create([
            'tenant_id'      => $tenantId,
            'crm_account_id' => $account->id,
            'name'           => $validated['name'],
            'designation'    => $validated['designation'] ?? null,
            'role'           => $validated['role'] ?? 'Purchase Decision Maker',
            'email'          => $validated['email'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'mobile'         => $validated['mobile'] ?? null,
            'is_primary'     => !empty($validated['is_primary']),
            'status'         => 'active',
        ]);

        return redirect()->route('crm.accounts.show', $account)->with('success', 'Contact added successfully.');
    }

    public function getContactsList(CrmAccount $account): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $account);

        $contacts = CrmContact::where('crm_account_id', $account->id)
            ->orderBy('name')
            ->get(['id', 'name', 'designation', 'role', 'email', 'phone']);

        return response()->json([
            'success' => true,
            'contacts' => $contacts
        ]);
    }

    public function quickStoreContact(Request $request): \Illuminate\Http\JsonResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'crm_account_id' => [
                'required',
                Rule::exists('crm_accounts', 'id')->where('tenant_id', $tenantId),
            ],
            'name'           => 'required|string|max:255',
            'designation'    => 'nullable|string|max:255',
            'role'           => 'nullable|string|max:100',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'mobile'         => 'nullable|string|regex:/^[0-9]+$/|max:20',
        ], $this->getAccountValidationMessages());

        $account = CrmAccount::where('tenant_id', $tenantId)->findOrFail($validated['crm_account_id']);
        $this->authorize('update', $account);

        $contact = CrmContact::create([
            'tenant_id'      => $tenantId,
            'crm_account_id' => $account->id,
            'name'           => $validated['name'],
            'designation'    => $validated['designation'] ?? null,
            'role'           => $validated['role'] ?? 'Primary Contact',
            'email'          => $validated['email'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'mobile'         => $validated['mobile'] ?? $validated['phone'] ?? null,
            'is_primary'     => true,
            'status'         => 'active',
        ]);

        $additional = $request->input('additional_contacts', []);
        if (is_array($additional)) {
            foreach ($additional as $add) {
                if (empty($add['name'])) continue;
                CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'crm_account_id' => $account->id,
                    'name'           => $add['name'],
                    'designation'    => $add['designation'] ?? null,
                    'role'           => 'Additional Contact',
                    'email'          => $add['email'] ?? null,
                    'phone'          => $add['phone'] ?? null,
                    'mobile'         => $add['phone'] ?? null,
                    'is_primary'     => false,
                    'status'         => 'active',
                ]);
            }
        }

        $subInfo = $contact->designation ?: ($contact->role ?: '');
        $displayText = $contact->name . ($subInfo ? " ({$subInfo})" : '');

        return response()->json([
            'success' => true,
            'id'      => $contact->id,
            'name'    => $displayText,
            'message' => 'Contact Person created successfully for ' . $account->name . '!'
        ]);
    }

    public function destroy(CrmAccount $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $account->delete();
        return redirect()->route('crm.accounts.index')->with('success', 'Account deleted successfully.');
    }

    /**
     * Export Accounts to Excel with customizable columns and applied filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', CrmAccount::class);
        $tenantId = tenant_id() ?? auth()->user()->tenant_id ?? 1;

        return Excel::download(
            new AccountExport($tenantId, $request->all()),
            'accounts_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    /**
     * Update the assigned Account Manager / Owner for an Account.
     */
    public function updateOwner(Request $request, CrmAccount $account)
    {
        $this->authorize('update', $account);

        $validated = $request->validate([
            'account_owner_id' => 'nullable|exists:users,id',
            'note'             => 'nullable|string|max:500',
        ]);

        $ownerId = $validated['account_owner_id'] ?? null;
        $account->update(['owner_id' => $ownerId]);

        $targetUser = $ownerId ? \App\Models\User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';
        $message = "Account manager successfully updated to {$ownerName}!";

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'account_id'    => $account->id,
                'owner_id'      => $ownerId,
                'owner_name'    => $ownerName,
                'owner_email'   => $targetUser?->email ?? '',
                'owner_initial' => strtoupper(substr($ownerName, 0, 1)),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Bulk assign accounts to an Account Manager / Sales Rep.
     */
    public function bulkAssign(Request $request)
    {
        $this->authorize('viewAny', CrmAccount::class);

        $validated = $request->validate([
            'account_ids'      => 'required|array|min:1',
            'account_ids.*'    => 'required|integer|exists:crm_accounts,id',
            'account_owner_id' => 'nullable|exists:users,id',
            'note'             => 'nullable|string|max:500',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
        $ownerId = $validated['account_owner_id'] ?? null;
        $targetUser = $ownerId ? \App\Models\User::find($ownerId) : null;
        $ownerName = $targetUser ? $targetUser->name : 'Unassigned';

        $accounts = CrmAccount::where('tenant_id', $tenantId)
            ->whereIn('id', $validated['account_ids'])
            ->get();

        $updatedCount = 0;
        foreach ($accounts as $account) {
            $account->update(['owner_id' => $ownerId]);
            $updatedCount++;
        }

        $message = "Successfully assigned {$updatedCount} account(s) to {$ownerName}!";

        if ($request->wantsJson() || $request->ajax() || $request->header('Accept') === 'application/json') {
            return response()->json([
                'success'       => true,
                'message'       => $message,
                'updated_count' => $updatedCount,
                'owner_id'      => $ownerId,
                'owner_name'    => $ownerName,
                'owner_email'   => $targetUser?->email ?? '',
                'owner_initial' => strtoupper(substr($ownerName, 0, 1)),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    private function getAccountValidationRules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'gstin'         => 'nullable|string|max:50',
            'email'         => 'required|email|max:255',
            'phone'         => 'nullable|string|regex:/^[0-9]+$/|max:20',
            'website'       => 'nullable|string|max:255',
            'industry_type' => 'nullable|string|max:255',
            'credit_limit'  => 'nullable|numeric|min:0',
            'opening_balance' => 'nullable|numeric|min:0',
            'opening_balance_type' => ['nullable', 'in:debit,credit'],
            'owner_id'      => 'nullable|exists:users,id',
            'street'        => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'zip_code'      => 'nullable|string|regex:/^[0-9]+$/|max:10',
            // Primary contact fields
            'contact_name'  => 'nullable|string|max:255',
            'designation'   => 'nullable|string|max:255',
            'role'          => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|regex:/^[0-9]+$/|max:20',
        ];
    }

    private function getAccountValidationMessages(): array
    {
        return [
            'name.required'        => 'Company Name is required.',
            'email.required'       => 'Contact Email is required.',
            'email.email'          => 'Please enter a valid email address for Company Email.',
            'phone.regex'          => 'Phone number must contain digits/numbers only (no special characters or letters).',
            'contact_phone.regex'  => 'Primary contact phone number must contain digits/numbers only (no special characters or letters).',
            'contact_email.email'  => 'Please enter a valid email address for Primary Contact.',
            'zip_code.regex'       => 'Zip / Postal code must contain digits/numbers only (no special characters or letters).',
            'credit_limit.numeric' => 'Credit limit must be a valid number.',
            'opening_balance.numeric' => 'Opening balance must be a valid number.',
            'mobile.regex'         => 'Mobile number must contain digits/numbers only (no special characters or letters).',
        ];
    }
}
