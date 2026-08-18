<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Customer;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CrmAccountController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;
        $search = $request->input('search');

        $query = CrmAccount::with(['contacts', 'deals', 'customer'])
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

        $accounts = $query->orderBy('id', 'desc')->paginate(15);

        return view('modules.crm.accounts.index', compact('accounts', 'search'));
    }

    public function create(): View
    {
        $users = \App\Models\User::orderBy('name')->get();
        return view('modules.crm.accounts.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'gstin'         => 'nullable|string|max:50',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'website'       => 'nullable|string|max:255',
            'industry_type' => 'nullable|string|max:255',
            'credit_limit'  => 'nullable|numeric|min:0',
            'owner_id'      => 'nullable|exists:users,id',
            'street'        => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'zip_code'      => 'nullable|string|max:20',
            // Primary contact fields
            'contact_name'  => 'nullable|string|max:255',
            'designation'   => 'nullable|string|max:255',
            'role'          => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ]);

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

        if (!$customer) {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name'      => $validated['name'],
                'email'     => $validated['email'] ?? null,
                'phone'     => $validated['phone'] ?? null,
                'status'    => 'active',
            ]);
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
            'credit_limit'  => $validated['credit_limit'] ?? 0.00,
            'street'        => $validated['street'] ?? null,
            'city'          => $validated['city'] ?? null,
            'state'         => $validated['state'] ?? null,
            'country'       => $validated['country'] ?? null,
            'zip_code'      => $validated['zip_code'] ?? null,
            'status'        => 'active',
            'owner_id'      => $validated['owner_id'] ?? (auth()->id() ?: 1),
        ]);

        // 3. Create Primary Contact if name provided
        if (!empty($validated['contact_name'])) {
            CrmContact::create([
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

        return redirect()->route('crm.accounts.show', $account)->with('success', 'Account created successfully.');
    }

    public function show(CrmAccount $account): View
    {
        $account->load(['contacts', 'deals.quotations', 'quotations', 'customer', 'owner']);

        $salesOrders = collect();
        if ($account->customer_id) {
            $salesOrders = SalesOrder::where('customer_id', $account->customer_id)->latest()->get();
        }

        $lifetimeRevenue = $account->lifetime_revenue;
        $openDealsCount = $account->deals->whereNotIn('stage', ['Closed Won', 'Closed Lost'])->count();
        $wonDealsCount = $account->deals->where('stage', 'Closed Won')->count();
        $lostDealsCount = $account->deals->where('stage', 'Closed Lost')->count();
        $aov = $wonDealsCount > 0 ? ($lifetimeRevenue / $wonDealsCount) : 0;
        $lastPurchaseDate = $account->last_purchase_date;

        return view('modules.crm.accounts.show', compact(
            'account',
            'salesOrders',
            'lifetimeRevenue',
            'openDealsCount',
            'wonDealsCount',
            'lostDealsCount',
            'aov',
            'lastPurchaseDate'
        ));
    }

    public function edit(CrmAccount $account): View
    {
        $account->load('primaryContact');
        $users = \App\Models\User::orderBy('name')->get();
        return view('modules.crm.accounts.edit', compact('account', 'users'));
    }

    public function update(Request $request, CrmAccount $account): RedirectResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'gstin'         => 'nullable|string|max:50',
            'email'         => 'nullable|email|max:255',
            'phone'         => 'nullable|string|max:50',
            'website'       => 'nullable|string|max:255',
            'industry_type' => 'nullable|string|max:255',
            'credit_limit'  => 'nullable|numeric|min:0',
            'owner_id'      => 'nullable|exists:users,id',
            'street'        => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'zip_code'      => 'nullable|string|max:20',
            'status'        => 'required|string|in:active,inactive',
        ]);

        $account->update($validated);

        if ($account->customer_id) {
            Customer::where('id', $account->customer_id)->update([
                'name'  => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);
        }

        return redirect()->route('crm.accounts.show', $account)->with('success', 'Account updated successfully.');
    }

    public function storeContact(Request $request, CrmAccount $account): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'role'        => 'nullable|string|max:100',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'mobile'      => 'nullable|string|max:50',
            'is_primary'  => 'nullable|boolean',
        ]);

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
            'crm_account_id' => 'required|exists:crm_accounts,id',
            'name'           => 'required|string|max:255',
            'designation'    => 'nullable|string|max:255',
            'role'           => 'nullable|string|max:100',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'mobile'         => 'nullable|string|max:50',
        ]);

        $account = CrmAccount::findOrFail($validated['crm_account_id']);

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
        $account->delete();
        return redirect()->route('crm.accounts.index')->with('success', 'Account deleted successfully.');
    }
}
