<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\SopAssignment;
use App\Domains\HRMS\Models\SopCategory;
use App\Domains\HRMS\Models\SopDocument;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Domains\HRMS\Services\SopService;
use App\Domains\HRMS\Repositories\SopRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SopController extends Controller
{
    public function __construct(
        private readonly SopService $sopService,
        private readonly HrmsScopeService $scopeService,
        private readonly SopRepositoryInterface $sopRepository
    ) {}

    /**
     * RBAC helper: check if user has full SOP management privileges.
     */
    private function canManageSop(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.sop.manage');
    }

    /**
     * RBAC helper: check if user can create SOPs.
     */
    private function canCreateSop(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->canManageSop($user) || $user->hasHrPermission('hrms.sop.create');
    }

    /**
     * RBAC helper: check if user can update SOPs.
     */
    private function canUpdateSop(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->canManageSop($user) || $user->hasHrPermission('hrms.sop.update');
    }

    /**
     * RBAC helper: check if user can delete SOPs.
     */
    private function canDeleteSop(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->canManageSop($user) || $user->hasHrPermission('hrms.sop.delete');
    }

    /**
     * Check if user is an HR Manager, Admin, or has operational SOP visibility.
     */
    private function isHrOrAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.sop.manage') ||
            $user->hasHrPermission('hrms.sop.create') ||
            $user->hasHrPermission('hrms.sop.update') ||
            $user->hasHrPermission('hrms.sop.delete') ||
            $user->hasHrPermission('hrms.employees.view');
    }

    /**
     * Display the Enterprise SOP Hub (Admin Management, Directory, My SOPs, Compliance).
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdmin($user);

        $data = $this->sopService->getIndexData(
            $request->all(),
            $currentEmployee,
            (bool) $isHrOrAdmin,
            (int) $tenantId
        );

        return view('modules.hrms.sop.index', $data);
    }

    /**
     * Display the employee-focused workspace for My Assigned SOPs.
     */
    public function mySops(Request $request): View
    {
        $request->merge(['active_tab' => 'my_sops']);
        return $this->index($request);
    }

    /**
     * Display detailed SOP Document Reader, Version History, and Sign-off records.
     */
    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->isHrOrAdmin($user);

        $sop = SopDocument::with(['category', 'department', 'creator', 'approver', 'sections', 'versionHistories.creator'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        // Security Guard: Draft SOPs can only be previewed by Admins and HR Managers
        if ($sop->status !== 'published' && !$isHrOrAdmin) {
            abort(403, __('hrms.sop.draft_access_denied'));
        }

        $myAssignment = null;
        if ($currentEmployee) {
            $myAssignment = SopAssignment::where('sop_document_id', $sop->id)
                ->where('employee_id', $currentEmployee->id)
                ->first();
        }

        $assignments = collect();
        if ($isHrOrAdmin) {
            if ($sop->status === 'published' && SopAssignment::where('sop_document_id', $sop->id)->count() === 0) {
                $this->sopService->dispatchAssignments($sop);
            }
            $assignments = SopAssignment::with(['employee.department', 'employee.designation'])
                ->where('sop_document_id', $sop->id)
                ->latest('assigned_at')
                ->get();
        }

        return view('modules.hrms.sop.show', [
            'sop' => $sop,
            'myAssignment' => $myAssignment,
            'assignments' => $assignments,
            'isHrOrAdmin' => $isHrOrAdmin,
            'currentEmployee' => $currentEmployee,
        ]);
    }

    /**
     * Store a newly created SOP Document.
     */
    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canCreateSop($user), 403, __('hrms.sop.unauthorized_create'));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'sop_category_id' => ['nullable', 'integer', Rule::exists('sop_categories', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'criticality' => 'nullable|in:low,medium,high,critical',
            'target_audience_type' => 'nullable|in:all,department,designation,custom',
            'target_department_ids' => 'nullable|array',
            'target_department_ids.*' => [Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'target_designation_ids' => 'nullable|array',
            'target_designation_ids.*' => [Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'target_employee_ids' => 'nullable|array',
            'target_employee_ids.*' => [Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'is_mandatory' => 'nullable|boolean',
            'auto_assign_new_hires' => 'nullable|boolean',
            'acknowledgment_days_limit' => 'nullable|integer|min:1|max:365',
            'effective_date' => 'nullable|date',
            'review_interval_months' => 'nullable|integer|min:1|max:60',
            'summary' => 'nullable|string',
            'objective' => 'nullable|string',
            'scope' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'status' => 'nullable|in:draft,under_review,published',
            'sections' => 'nullable|array',
        ]);

        if (($validated['target_audience_type'] ?? '') === 'department' && !empty($validated['department_id']) && empty($validated['target_department_ids'])) {
            $validated['target_department_ids'] = [(int) $validated['department_id']];
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->storeTenantFile('hrms/sops', 'public');
            $validated['attachment_path'] = $path;
        }

        $this->sopService->createSop($validated, (int) $tenantId, $user);

        return redirect()->route('hrms.sop.index', ['active_tab' => 'directory'])
            ->with('success', __('hrms.sop.sop_created_success'));
    }

    /**
     * Update an existing SOP Document with version bump options.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canUpdateSop($user), 403, __('hrms.sop.unauthorized_update'));

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'sop_category_id' => ['nullable', 'integer', Rule::exists('sop_categories', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'criticality' => 'nullable|in:low,medium,high,critical',
            'target_audience_type' => 'nullable|in:all,department,designation,custom',
            'target_department_ids' => 'nullable|array',
            'target_department_ids.*' => [Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'target_designation_ids' => 'nullable|array',
            'target_designation_ids.*' => [Rule::exists('designations', 'id')->where('tenant_id', $tenantId)],
            'target_employee_ids' => 'nullable|array',
            'target_employee_ids.*' => [Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'is_mandatory' => 'nullable|boolean',
            'auto_assign_new_hires' => 'nullable|boolean',
            'acknowledgment_days_limit' => 'nullable|integer|min:1|max:365',
            'effective_date' => 'nullable|date',
            'review_interval_months' => 'nullable|integer|min:1|max:60',
            'summary' => 'nullable|string',
            'objective' => 'nullable|string',
            'scope' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'status' => 'nullable|in:draft,under_review,published,archived',
            'version_bump' => 'nullable|in:none,minor,major',
            'changes_summary' => 'nullable|string|max:500',
            'sections' => 'nullable|array',
        ]);

        if (($validated['target_audience_type'] ?? '') === 'department' && !empty($validated['department_id']) && empty($validated['target_department_ids'])) {
            $validated['target_department_ids'] = [(int) $validated['department_id']];
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->storeTenantFile('hrms/sops', 'public');
            $validated['attachment_path'] = $path;
        }

        $this->sopService->updateSop($sop, $validated, $user);

        return redirect()->route('hrms.sop.show', $sop->id)
            ->with('success', __('hrms.sop.sop_updated_success'));
    }

    /**
     * Delete an SOP Document.
     */
    public function destroy(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canDeleteSop($user), 403, __('hrms.sop.unauthorized_delete'));

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $sop->delete();

        return redirect()->route('hrms.sop.index', ['active_tab' => 'directory'])
            ->with('success', __('hrms.sop.sop_deleted_success'));
    }

    /**
     * Publish SOP directly and dispatch assignments to all target employees.
     */
    public function publish(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_publish'));

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $sop->update([
            'status' => 'published',
            'approved_by' => $user?->id,
            'approved_at' => now(),
            'effective_date' => $sop->effective_date ?? now()->toDateString(),
        ]);

        $assignedCount = $this->sopService->dispatchAssignments($sop);

        return back()->with('success', __('hrms.sop.sop_published_success', ['count' => $assignedCount]));
    }

    /**
     * Archive an SOP Document.
     */
    public function archive(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_manage'));

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $sop->update(['status' => 'archived']);

        return back()->with('success', __('hrms.sop.sop_archived_success'));
    }

    /**
     * Manual assignment trigger for an SOP.
     */
    public function assign(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_dispatch'));

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);

        if ($sop->status !== 'published') {
            $sop->update([
                'status' => 'published',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }

        $assignedCount = $this->sopService->dispatchAssignments($sop);

        return back()->with('success', __('hrms.sop.assignments_synced_success', ['count' => $assignedCount]));
    }

    /**
     * Employee digital sign-off and acknowledgment.
     */
    public function acknowledge(Request $request, int $assignmentId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $this->canManageSop($user);

        $assignment = SopAssignment::where('tenant_id', $tenantId)->findOrFail($assignmentId);

        // Security Guard: Only the assigned employee or an authorized HR Admin can acknowledge
        if (!$isHrOrAdmin && (!$currentEmployee || $assignment->employee_id !== $currentEmployee->id)) {
            abort(403, __('hrms.sop.unauthorized_acknowledge'));
        }

        $request->validate([
            'checklist_responses' => 'nullable|array',
            'signature_data' => 'nullable|string',
            'confirm_understanding' => 'required',
        ]);

        $this->sopService->acknowledgeAssignment(
            $assignment,
            $request->all(),
            $request->ip() ?: '0.0.0.0',
            $request->userAgent() ?: 'Web Client'
        );

        return back()->with('success', __('hrms.sop.signoff_success'));
    }

    /**
     * Send reminder nudge to a single employee.
     */
    public function remind(int $assignmentId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_manage'));

        $assignment = SopAssignment::with(['document', 'employee'])->where('tenant_id', $tenantId)->findOrFail($assignmentId);

        $this->sopService->sendReminder($assignment);

        $empName = $assignment->employee->full_name ?? ($assignment->employee->first_name ?? __('hrms.common.employee'));
        return back()->with('success', __('hrms.sop.reminder_sent_success', ['name' => $empName]));
    }

    /**
     * Send bulk reminder nudges for an SOP.
     */
    public function bulkRemind(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_manage'));

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $pendingAssignments = SopAssignment::with(['document', 'employee'])
            ->where('sop_document_id', $sop->id)
            ->where('status', 'pending')
            ->get();

        $count = 0;
        foreach ($pendingAssignments as $assignment) {
            $this->sopService->sendReminder($assignment);
            $count++;
        }

        return back()->with('success', __('hrms.sop.bulk_reminders_sent_success', ['count' => $count]));
    }

    /**
     * Export compliance audit log as CSV.
     */
    public function exportAudit(int $id): mixed
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->isHrOrAdmin($user), 403, __('hrms.sop.unauthorized_manage'));

        return $this->sopRepository->exportAudit($id, $tenantId);
    }

    /**
     * Store new SOP Category.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_cat_create'));

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = $user?->company_id;
        $validated['color'] = $validated['color'] ?? '#3b82f6';

        SopCategory::create($validated);

        return redirect()->route('hrms.sop.index', ['active_tab' => 'categories'])
            ->with('success', __('hrms.sop.category_created_success'));
    }

    /**
     * Update SOP Category.
     */
    public function updateCategory(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_cat_update'));

        $cat = SopCategory::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $cat->update($validated);

        return redirect()->route('hrms.sop.index', ['active_tab' => 'categories'])
            ->with('success', __('hrms.sop.category_updated_success'));
    }

    /**
     * Delete SOP Category.
     */
    public function destroyCategory(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        abort_unless($tenantId, 400, __('hrms.sop.unauthorized_tenant'));

        $user = auth()->user();
        abort_unless($this->canManageSop($user), 403, __('hrms.sop.unauthorized_cat_delete'));

        $cat = SopCategory::where('tenant_id', $tenantId)->findOrFail($id);

        $linkedDocsCount = $cat->documents()->count();
        if ($linkedDocsCount > 0) {
            return redirect()->route('hrms.sop.index', ['active_tab' => 'categories'])
                ->with('error', __('hrms.sop.category_has_docs_error', ['name' => $cat->name, 'count' => $linkedDocsCount]));
        }

        $cat->delete();

        return redirect()->route('hrms.sop.index', ['active_tab' => 'categories'])
            ->with('success', __('hrms.sop.category_deleted_success'));
    }
}
