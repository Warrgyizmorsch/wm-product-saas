<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionSettingsController extends Controller
{
    public function index(): View
    {
        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];

        return view('modules.production.settings.index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'currentWorkflow' => $settings['subcontract_procurement_workflow'] ?? 'manual_pr_po',
            'autoApprovalLimit' => $settings['subcontract_auto_approval_limit'] ?? 10000.00,
        ]);
    }

    public function updateSubcontract(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subcontract_procurement_workflow' => 'required|in:manual_pr_po,auto_draft_po,auto_approved_po',
            'subcontract_auto_approval_limit' => 'nullable|numeric|min:0',
        ]);

        $tenant = tenant();
        if (!$tenant) {
            return redirect()->back()->with('error', 'Tenant context not found.');
        }

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $currentSettings['subcontract_procurement_workflow'] = $validated['subcontract_procurement_workflow'];
        $currentSettings['subcontract_auto_approval_limit'] = (float) ($validated['subcontract_auto_approval_limit'] ?? 0.0);

        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('production.settings.index')
            ->with('success', 'Subcontract procurement settings updated successfully.');
    }
}
