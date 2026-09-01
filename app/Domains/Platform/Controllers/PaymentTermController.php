<?php

namespace App\Domains\Platform\Controllers;

use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Models\PaymentTerm;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PaymentTermController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $query = PaymentTerm::query()->where('tenant_id', $tenantId);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort_by', 'due_days');
        $direction = $request->input('sort_order', 'asc');
        $query->orderBy($sort, $direction);

        $paymentTerms = $query->paginate(15)->withQueryString();

        return view('modules.platform.payment_terms.index', compact('paymentTerms'));
    }

    public function create(): View
    {
        return view('modules.platform.payment_terms.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'due_days' => ['required', 'integer', 'min:0'],
            'discount_days' => ['nullable', 'integer', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (empty($validated['code'])) {
            $validated['code'] = strtoupper(str_replace(' ', '_', substr($validated['name'], 0, 10)));
        }

        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = session('company_id') ?? 1;
        $validated['branch_id'] = session('branch_id') ?? 1;
        $validated['created_by'] = Auth::id();
        $validated['discount_days'] = $validated['discount_days'] ?? 0;
        $validated['discount_percentage'] = $validated['discount_percentage'] ?? 0.00;

        PaymentTerm::create($validated);

        return redirect()
            ->route('platform.payment-terms.index')
            ->with('success', "Payment Term '{$validated['name']}' created successfully.");
    }

    public function edit(PaymentTerm $paymentTerm): View
    {
        return view('modules.platform.payment_terms.edit', compact('paymentTerm'));
    }

    public function update(Request $request, PaymentTerm $paymentTerm): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'due_days' => ['required', 'integer', 'min:0'],
            'discount_days' => ['nullable', 'integer', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['updated_by'] = Auth::id();
        $validated['discount_days'] = $validated['discount_days'] ?? 0;
        $validated['discount_percentage'] = $validated['discount_percentage'] ?? 0.00;

        $paymentTerm->update($validated);

        return redirect()
            ->route('platform.payment-terms.index')
            ->with('success', "Payment Term '{$paymentTerm->name}' updated successfully.");
    }

    public function destroy(PaymentTerm $paymentTerm): RedirectResponse
    {
        $name = $paymentTerm->name;
        $paymentTerm->delete();

        return redirect()
            ->route('platform.payment-terms.index')
            ->with('success', "Payment Term '{$name}' deleted successfully.");
    }

    public function toggleStatus(PaymentTerm $paymentTerm): RedirectResponse
    {
        $paymentTerm->update(['is_active' => !$paymentTerm->is_active]);

        $statusLabel = $paymentTerm->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->back()
            ->with('success', "Payment Term '{$paymentTerm->name}' {$statusLabel} successfully.");
    }
}
