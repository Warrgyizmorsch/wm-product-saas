<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Services\LeadAssignmentService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmSettingsController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Lead::class);

        $tenant = tenant();
        $tenantId = $tenant ? $tenant->id : 1;
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];

        $quotationApprovalPolicy = $settings['quotation_approval_policy'] ?? 'approval_required';
        $invoicingPolicy = $settings['invoicing_policy'] ?? 'sales_order';
        $leadAssignmentConfig = LeadAssignmentService::getConfig($tenant);

        $users = User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'role']);

        return view('modules.crm.settings.index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'quotationApprovalPolicy' => $quotationApprovalPolicy,
            'invoicingPolicy' => $invoicingPolicy,
            'leadAssignmentConfig' => $leadAssignmentConfig,
            'users' => $users,
        ]);
    }

    public function updateLeadAssignment(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $tenant = tenant();
        if (!$tenant) {
            return redirect()->back()->with('error', 'Tenant context not found.');
        }

        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:manual_creator,unassigned,round_robin'],
            'default_owner_id' => ['nullable', 'integer'],
            'round_robin_users' => ['nullable', 'array'],
            'round_robin_users.*' => ['integer'],
            'apply_on_web_forms' => ['nullable'],
            'apply_on_api_imports' => ['nullable'],
            'apply_on_manual_create' => ['nullable'],
        ]);

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $existingAssignment = $currentSettings['lead_assignment'] ?? LeadAssignmentService::getDefaultConfig();

        $assignmentPayload = [
            'mode' => $validated['mode'],
            'default_owner_id' => !empty($validated['default_owner_id']) ? (int)$validated['default_owner_id'] : null,
            'round_robin_users' => !empty($validated['round_robin_users']) ? array_map('intval', $validated['round_robin_users']) : [],
            'last_assigned_user_id' => $existingAssignment['last_assigned_user_id'] ?? null,
            'apply_on_web_forms' => $request->has('apply_on_web_forms'),
            'apply_on_api_imports' => $request->has('apply_on_api_imports'),
            'apply_on_manual_create' => $request->has('apply_on_manual_create'),
        ];

        $currentSettings['lead_assignment'] = $assignmentPayload;
        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('crm.settings.index', ['tab' => 'lead-assignment'])
            ->with('success', 'Lead Auto-Assignment & Round-Robin Distribution settings updated successfully.');
    }

    public function updateInvoicingPolicy(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

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

        return redirect()->route('crm.settings.index', ['tab' => 'invoicing-policy'])
            ->with('success', 'CRM & Sales Invoicing Policy settings updated successfully.');
    }

    public function updateQuotationApprovalPolicy(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $validated = $request->validate([
            'quotation_approval_policy' => ['required', 'string', 'in:approval_required,auto_approve'],
        ]);

        $tenant = tenant();
        if (!$tenant) {
            return redirect()->back()->with('error', 'Tenant context not found.');
        }

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $currentSettings['quotation_approval_policy'] = $validated['quotation_approval_policy'];

        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('crm.settings.index', ['tab' => 'quotation-approval'])
            ->with('success', 'Quotation Approval Policy updated successfully.');
    }
}
