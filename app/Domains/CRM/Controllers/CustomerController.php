<?php

namespace App\Domains\CRM\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

        $totalCount = Customer::where('tenant_id', $tenantId)->count();
        $activeCount = Customer::where('tenant_id', $tenantId)->whereIn('status', ['active', 'Active'])->count();
        $inactiveCount = Customer::where('tenant_id', $tenantId)->whereIn('status', ['inactive', 'Inactive'])->count();

        $query = Customer::query()->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
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

        $customers = $query->paginate(15)->withQueryString();

        return view('modules.crm.customers.index', compact('customers', 'totalCount', 'activeCount', 'inactiveCount'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('modules.crm.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $tenantId = tenant_id() ?? app(TenantContext::class)->id();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 
                'email', 
                'max:255', 
                Rule::unique('customers', 'email')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ]);

        $this->customers->create($validated);

        return redirect()
            ->route('crm.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Customer::class);
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable', 
                'email', 
                'max:255', 
                Rule::unique('customers', 'email')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string'],
            'shipping_address' => ['nullable', 'string'],
        ]);

        $validated['status'] = 'active';
        $customer = $this->customers->create($validated);

        return response()->json([
            'id'               => $customer->id,
            'name'             => $customer->name . ($customer->email ? " ({$customer->email})" : ''),
            'email'            => $customer->email,
            'phone'            => $customer->phone,
            'billing_address'  => $customer->billing_address,
            'shipping_address' => $customer->shipping_address,
        ]);
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['crmAccount.contacts', 'crmAccount.owner']);

        $invoices = $customer->invoices()->with(['salesOrder.quotation.deal', 'customer'])->latest()->get();
        $payments = $customer->payments()->with(['allocations.invoice', 'allocations.salesOrder'])->latest()->get();
        $salesOrders = $customer->salesOrders()->with(['quotation.deal', 'items'])->latest()->get();
        $salesReturns = \App\Domains\Sales\Models\SalesReturn::where('customer_id', $customer->id)
            ->with(['salesOrder.quotation.deal'])
            ->latest()
            ->get();

        $totalBilled = $invoices->where('status', '!=', 'cancelled')->sum('total_amount');
        $totalPaid = $invoices->where('status', '!=', 'cancelled')->sum('amount_paid');
        if ($totalPaid == 0 && $payments->isNotEmpty()) {
            $totalPaid = $payments->where('status', 'completed')->sum('amount');
        }
        $totalReturns = $salesReturns->where('status', '!=', 'cancelled')->sum(function($r) {
            return floatval($r->total_amount ?: $r->total_refund_amount);
        });

        $netOutstanding = $totalBilled - $totalPaid - $totalReturns;
        $outstandingBalance = max(0, $netOutstanding);
        $customerCreditBalance = $netOutstanding < 0 ? abs($netOutstanding) : 0;
        
        $overdueAmount = $invoices->where('status', 'unpaid')
            ->filter(function($inv) {
                return $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->isPast();
            })->sum('balance_due');

        $creditLimit = floatval($customer->crmAccount?->credit_limit ?? 0);
        $availableCredit = max(0, $creditLimit - $outstandingBalance);

        return view('modules.crm.customers.show', compact(
            'customer',
            'invoices',
            'payments',
            'salesOrders',
            'salesReturns',
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

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,Active,Inactive'],
        ]);

        $customer->update([
            'status' => strtolower($validated['status']),
        ]);

        $statusLabel = strtolower($validated['status']) === 'active' ? 'Active' : 'Inactive';

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

        $customer->update([
            'status' => $newStatus,
        ]);

        $statusLabel = $newStatus === 'active' ? 'Active' : 'Inactive';

        return redirect()
            ->back()
            ->with('success', "Customer '{$customer->name}' marked as {$statusLabel} successfully.");
    }
}
