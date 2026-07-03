<?php

namespace App\Domains\Platform\Controllers;

use App\Domains\Platform\Services\TenantService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
    ) {
    }

    public function index(): View
    {
        return view('modules.platform.tenants.index', [
            'tenants' => $this->tenants->all(),
            'summary' => $this->tenants->summary(),
        ]);
    }

    public function create(): View
    {
        return view('modules.platform.tenants.create', [
            'tenant' => new Tenant(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenants->create($this->validated($request));

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', 'Tenant '.$tenant->name.' created successfully.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('modules.platform.tenants.edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->tenants->update($tenant, $this->validated($request, $tenant));

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', 'Tenant '.$tenant->name.' updated successfully.');
    }

    public function status(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED])],
        ]);

        $this->tenants->updateStatus($tenant, $validated['status']);

        return redirect()
            ->route('platform.tenants.index')
            ->with('success', 'Tenant '.$tenant->name.' marked '.$validated['status'].'.');
    }

    private function validated(Request $request, ?Tenant $tenant = null): array
    {
        $tenantId = $tenant?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($tenantId),
            ],
            'domain' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenantId),
            ],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(Tenant::statuses()))],
            'plan' => ['required', 'string', Rule::in(array_keys(Tenant::plans()))],
            'subscription_status' => ['required', 'string', Rule::in(array_keys(Tenant::subscriptionStatuses()))],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_storage_mb' => ['nullable', 'integer', 'min:1'],
            'trial_ends_at' => ['nullable', 'date'],
            'plan_started_at' => ['nullable', 'date'],
            'plan_expires_at' => ['nullable', 'date', 'after_or_equal:plan_started_at'],
            'timezone' => ['required', 'string', 'max:100'],
            'locale' => ['required', 'string', 'max:10'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'logo_full' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'logo_abbr' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:10'],
            'financial_year' => ['nullable', 'string', 'max:50'],
            'owner_name' => [$tenant ? 'nullable' : 'required_with:owner_email', 'nullable', 'string', 'max:255'],
            'owner_email' => [$tenant ? 'nullable' : 'required_with:owner_name', 'nullable', 'email', 'max:255'],
            'owner_password' => [$tenant ? 'nullable' : 'required_with:owner_email', 'nullable', 'string', 'min:8', 'confirmed'],
        ]);
    }
}
