<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\CrmLossReason;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Services\LeadAssignmentService;
use App\Http\Controllers\Controller;
use App\Models\ContactRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $leadAssignmentConfig = LeadAssignmentService::getConfig($tenant);
        $fieldVisitVerification = (bool)($settings['field_visit_verification'] ?? false);
        $leadSlaPolicy = array_merge([
            'response_sla_minutes' => 60,
            'sla_working_hours_only' => true,
            'stale_lead_days' => 3,
            'stale_alert_cadence_days' => 2,
            'auto_ack_inbound' => true,
            'auto_ack_whatsapp' => true,
            'default_nurture_days' => 90,
        ], $settings['crm_lead_policy'] ?? []);

        $users = User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar', 'role']);

        $contactRoles = ContactRole::availableForTenant((int) $tenantId)->get();

        $lossReasons = class_exists(CrmLossReason::class) && \Illuminate\Support\Facades\Schema::hasTable('crm_loss_reasons')
            ? CrmLossReason::where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get()
            : collect();

        return view('modules.crm.settings.index', [
            'tenant' => $tenant,
            'settings' => $settings,
            'quotationApprovalPolicy' => $quotationApprovalPolicy,
            'leadAssignmentConfig' => $leadAssignmentConfig,
            'fieldVisitVerification' => $fieldVisitVerification,
            'leadSlaPolicy' => $leadSlaPolicy,
            'users' => $users,
            'contactRoles' => $contactRoles,
            'lossReasons' => $lossReasons,
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
            'prefer_account_owner' => ['nullable'],
            'rules' => ['nullable', 'array', 'max:25'],
            'rules.*.name' => ['nullable', 'string', 'max:100'],
            'rules.*.territory' => ['nullable', 'string', 'max:100'],
            'rules.*.segment' => ['nullable', 'string', 'max:100'],
            'rules.*.source' => ['nullable', 'string', 'max:100'],
            'rules.*.product' => ['nullable', 'string', 'max:100'],
            'rules.*.strategy' => ['nullable', 'string', 'in:least_loaded,round_robin,first'],
            'rules.*.owner_ids' => ['nullable', 'array'],
            'rules.*.owner_ids.*' => ['integer'],
        ]);

        // FRS 2.5 step 3: routing rules (territory, segment, product, source, workload / round-robin / first)
        $rules = [];
        foreach ((array) ($validated['rules'] ?? []) as $row) {
            $owners = array_values(array_filter(array_map('intval', (array) ($row['owner_ids'] ?? []))));
            $hasCriteria = !empty($row['territory']) || !empty($row['segment']) || !empty($row['source']) || !empty($row['product']);
            if (empty($owners) || !$hasCriteria) {
                continue;
            }
            $rules[] = [
                'name' => trim((string) ($row['name'] ?? '')) ?: 'Rule ' . (count($rules) + 1),
                'territory' => trim((string) ($row['territory'] ?? '')),
                'segment' => trim((string) ($row['segment'] ?? '')),
                'source' => trim((string) ($row['source'] ?? '')),
                'product' => trim((string) ($row['product'] ?? '')),
                'strategy' => $row['strategy'] ?? 'least_loaded',
                'owner_ids' => $owners,
                'active' => true,
            ];
        }

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
            'prefer_account_owner' => $request->has('prefer_account_owner'),
            'rules' => $rules,
        ];

        $currentSettings['lead_assignment'] = $assignmentPayload;
        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('crm.settings.index', ['tab' => 'lead-assignment'])
            ->with('success', 'Lead Auto-Assignment & Round-Robin Distribution settings updated successfully.');
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

    public function updateFieldVisitVerification(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $tenant = tenant();
        if (!$tenant) {
            return redirect()->back()->with('error', 'Tenant context not found.');
        }

        $request->validate([
            'field_visit_verification' => ['nullable'],
            'mappls_api_key' => ['nullable', 'string', 'max:255'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:50', 'max:10000'],
        ]);

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $currentSettings['field_visit_verification'] = $request->boolean('field_visit_verification');
        $currentSettings['mappls_api_key'] = trim((string)$request->input('mappls_api_key', ''));
        $currentSettings['geofence_radius_meters'] = (int)$request->input('geofence_radius_meters', 500);

        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('crm.settings.index', ['tab' => 'field-visit'])
            ->with('success', 'Field Visit, Mappls Map API Key & Geo-Verification settings updated successfully.');
    }

    public function updateLeadSlaPolicy(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $tenant = tenant();
        if (!$tenant) {
            return redirect()->back()->with('error', 'Tenant context not found.');
        }

        $validated = $request->validate([
            'response_sla_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'sla_working_hours_only' => ['nullable'],
            'stale_lead_days' => ['required', 'integer', 'min:1', 'max:90'],
            'stale_alert_cadence_days' => ['required', 'integer', 'min:1', 'max:30'],
            'auto_ack_inbound' => ['nullable'],
            'auto_ack_whatsapp' => ['nullable'],
            'default_nurture_days' => ['required', 'integer', 'min:7', 'max:365'],
            'ack_template' => ['nullable', 'string', 'max:600'],
            'ack_subject' => ['nullable', 'string', 'max:150'],
        ]);

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        $currentSettings['crm_lead_policy'] = [
            'response_sla_minutes' => (int)$validated['response_sla_minutes'],
            'sla_working_hours_only' => $request->boolean('sla_working_hours_only'),
            'stale_lead_days' => (int)$validated['stale_lead_days'],
            'stale_alert_cadence_days' => (int)$validated['stale_alert_cadence_days'],
            'auto_ack_inbound' => $request->boolean('auto_ack_inbound'),
            'auto_ack_whatsapp' => $request->boolean('auto_ack_whatsapp'),
            'default_nurture_days' => (int)$validated['default_nurture_days'],
            'ack_template' => trim((string) ($validated['ack_template'] ?? '')),
            'ack_subject' => trim((string) ($validated['ack_subject'] ?? '')),
        ];

        $tenant->update(['settings' => $currentSettings]);

        return redirect()->route('crm.settings.index', ['tab' => 'lead-sla-policy'])
            ->with('success', 'Lead Response SLA & Stale Inactivity policy updated successfully.');
    }

    public function storeLossReason(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'follow_up_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'requires_competitor' => ['nullable'],
            'is_active' => ['nullable'],
        ]);

        $tenant = tenant();
        $tenantId = $tenant ? $tenant->id : 1;

        $payload = [
            'tenant_id' => $tenantId,
            'name' => trim($validated['name']),
            'code' => strtoupper(Str::slug($validated['name'], '_')),
            'requires_competitor' => $request->boolean('requires_competitor'),
            'sort_order' => (int) CrmLossReason::where('tenant_id', $tenantId)->max('sort_order') + 1,
            'is_active' => $request->boolean('is_active', true),
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('crm_loss_reasons', 'follow_up_days')) {
            $payload['follow_up_days'] = !empty($validated['follow_up_days']) ? (int)$validated['follow_up_days'] : 30;
        }

        CrmLossReason::create($payload);

        return redirect()->route('crm.settings.index', ['tab' => 'loss-reasons'])
            ->with('success', "Loss Reason '{$payload['name']}' added successfully.");
    }

    public function updateLossReason(Request $request, CrmLossReason $lossReason): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'follow_up_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'requires_competitor' => ['nullable'],
            'is_active' => ['nullable'],
        ]);

        $payload = [
            'name' => trim($validated['name']),
            'requires_competitor' => $request->boolean('requires_competitor'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('crm_loss_reasons', 'follow_up_days')) {
            $payload['follow_up_days'] = !empty($validated['follow_up_days']) ? (int)$validated['follow_up_days'] : 30;
        }

        $lossReason->update($payload);

        return redirect()->route('crm.settings.index', ['tab' => 'loss-reasons'])
            ->with('success', "Loss Reason '{$lossReason->name}' updated successfully.");
    }

    public function destroyLossReason(CrmLossReason $lossReason): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        if (empty($lossReason->tenant_id)) {
            return redirect()->route('crm.settings.index', ['tab' => 'loss-reasons'])
                ->with('error', "System default loss reasons cannot be deleted. You can mark them inactive instead.");
        }

        $reasonName = $lossReason->name;
        $lossReason->delete();

        return redirect()->route('crm.settings.index', ['tab' => 'loss-reasons'])
            ->with('success', "Loss Reason '{$reasonName}' deleted successfully.");
    }

    public function toggleLossReasonStatus(CrmLossReason $lossReason): RedirectResponse
    {
        $this->authorize('create', Lead::class);

        $lossReason->update(['is_active' => !$lossReason->is_active]);

        $statusLabel = $lossReason->is_active ? 'Active' : 'Inactive';
        return redirect()->route('crm.settings.index', ['tab' => 'loss-reasons'])
            ->with('success', "Loss Reason '{$lossReason->name}' toggled to {$statusLabel}.");
    }
}
