<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmSettingsController extends Controller
{
    public function index(): View
    {
        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];

        $invoicingPolicy = $settings['invoicing_policy'] ?? 'both';

        return view('modules.crm.settings.index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'invoicingPolicy' => $invoicingPolicy,
        ]);
    }

    public function updateInvoicingPolicy(Request $request): RedirectResponse
    {
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

        return redirect()->route('crm.settings.index')
            ->with('success', 'CRM & Sales Invoicing Policy settings updated successfully.');
    }
}
