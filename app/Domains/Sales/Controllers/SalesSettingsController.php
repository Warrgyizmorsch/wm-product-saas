<?php

namespace App\Domains\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesSettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SalesOrder::class);

        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];
        $invoicingPolicy = $settings['invoicing_policy'] ?? 'both';

        return view('modules.sales.settings.index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'invoicingPolicy' => $invoicingPolicy,
        ]);
    }

    public function updateInvoicingPolicy(Request $request): RedirectResponse
    {
        $this->authorize('create', SalesOrder::class);

        $validated = $request->validate([
            'invoicing_policy' => ['required', 'string', 'in:sales_order,dispatch_order,both'],
        ]);

        $tenant = tenant();
        if (!$tenant) {
            return redirect()->back()->with('error', 'Tenant context not found.');
        }

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $currentSettings['invoicing_policy'] = $validated['invoicing_policy'];

        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('sales.settings.index')
            ->with('success', 'Sales Invoicing Policy settings updated successfully.');
    }
}
