<?php

namespace App\Domains\CRM\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Services\CustomerService;
use App\Http\Controllers\Controller;
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

    public function index(): View
    {
        $this->authorize('viewAny', Customer::class);

        return view('modules.crm.customers.index', [
            'summary' => $this->customers->summary(),
            'customers' => $this->customers->latestActive(),
        ]);
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
}
