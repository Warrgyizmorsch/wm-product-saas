<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\SopAssignment;
use App\Domains\HRMS\Models\SopCategory;
use App\Domains\HRMS\Models\SopDocument;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Domains\HRMS\Services\SopService;
use App\Domains\HRMS\Repositories\SopRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SopController extends Controller
{
    public function __construct(
        private readonly SopService $sopService,
        private readonly HrmsScopeService $scopeService,
        private readonly SopRepositoryInterface $sopRepository
    ) {}

    /**
     * Display the Enterprise SOP Hub (Admin Management, Directory, My SOPs, Compliance).
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.sop.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        $data = $this->sopService->getIndexData(
            $request->all(),
            $currentEmployee,
            (bool) $isHrOrAdmin,
            (int) $tenantId
        );

        return view('modules.hrms.sop.index', $data);
    }

    /**
     * Display detailed SOP Document Reader, Version History, and Sign-off records.
     */
    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();
        $currentEmployee = $this->scopeService->resolveEmployee($user, $tenantId);
        $isHrOrAdmin = $user && (
            $this->scopeService->isCompanyAdmin($user) ||
            $user->hasHrPermission('hr.settings.manage') ||
            $user->hasHrPermission('hrms.sop.manage') ||
            $user->hasHrPermission('hrms.employees.view')
        );

        $sop = SopDocument::with(['category', 'department', 'creator', 'approver', 'sections', 'versionHistories.creator'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $myAssignment = null;
        if ($currentEmployee) {
            $myAssignment = SopAssignment::where('sop_document_id', $sop->id)
                ->where('employee_id', $currentEmployee->id)
                ->first();
        }

        $assignments = collect();
        if ($isHrOrAdmin) {
            if (SopAssignment::where('sop_document_id', $sop->id)->count() === 0) {
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
        $user = auth()->user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'sop_category_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'criticality' => 'nullable|in:low,medium,high,critical',
            'target_audience_type' => 'nullable|in:all,department,designation,custom',
            'target_department_ids' => 'nullable|array',
            'target_designation_ids' => 'nullable|array',
            'target_employee_ids' => 'nullable|array',
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

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('hrms/sops', 'public');
            $validated['attachment_path'] = $path;
        }

        $this->sopService->createSop($validated, (int) $tenantId, $user);

        return redirect()->route('hrms.sop.index', ['active_tab' => 'directory'])
            ->with('success', 'Standard Operating Procedure created successfully.');
    }

    /**
     * Update an existing SOP Document with version bump options.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'sop_category_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'criticality' => 'nullable|in:low,medium,high,critical',
            'target_audience_type' => 'nullable|in:all,department,designation,custom',
            'target_department_ids' => 'nullable|array',
            'target_designation_ids' => 'nullable|array',
            'target_employee_ids' => 'nullable|array',
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

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('hrms/sops', 'public');
            $validated['attachment_path'] = $path;
        }

        $this->sopService->updateSop($sop, $validated, $user);

        return redirect()->route('hrms.sop.show', $sop->id)
            ->with('success', 'SOP updated successfully.');
    }

    /**
     * Delete an SOP Document.
     */
    public function destroy(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $sop->delete();

        return redirect()->route('hrms.sop.index', ['active_tab' => 'directory'])
            ->with('success', 'SOP deleted successfully.');
    }

    /**
     * Publish SOP directly and dispatch assignments to all target employees.
     */
    public function publish(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $sop->update([
            'status' => 'published',
            'approved_by' => $user?->id,
            'approved_at' => now(),
            'effective_date' => $sop->effective_date ?? now()->toDateString(),
        ]);

        $assignedCount = $this->sopService->dispatchAssignments($sop);

        return back()->with('success', "SOP published successfully. Dispatched to {$assignedCount} target employees.");
    }

    /**
     * Archive an SOP Document.
     */
    public function archive(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);
        $sop->update(['status' => 'archived']);

        return back()->with('success', 'SOP archived.');
    }

    /**
     * Manual assignment trigger for an SOP.
     */
    public function assign(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $sop = SopDocument::where('tenant_id', $tenantId)->findOrFail($id);

        if ($sop->status !== 'published') {
            $sop->update([
                'status' => 'published',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        }

        $assignedCount = $this->sopService->dispatchAssignments($sop);

        return back()->with('success', "Assignments synchronized. Dispatched to {$assignedCount} target employees.");
    }

    /**
     * Employee digital sign-off and acknowledgment.
     */
    public function acknowledge(Request $request, int $assignmentId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $assignment = SopAssignment::where('tenant_id', $tenantId)->findOrFail($assignmentId);

        $request->validate([
            'checklist_responses' => 'nullable|array',
            'signature_data' => 'nullable|string',
            'confirm_understanding' => 'required',
        ]);

        $this->sopService->acknowledgeAssignment(
            $assignment,
            $request->all(),
            $request->ip() ?? '127.0.0.1',
            $request->userAgent() ?? 'Web Browser'
        );

        return back()->with('success', 'Thank you! You have successfully acknowledged and signed off on this SOP.');
    }

    /**
     * Send reminder nudge to a single employee.
     */
    public function remind(int $assignmentId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $assignment = SopAssignment::with(['document', 'employee'])->where('tenant_id', $tenantId)->findOrFail($assignmentId);

        $this->sopService->sendReminder($assignment);

        $empName = $assignment->employee->full_name ?? ($assignment->employee->first_name ?? 'Employee');
        return back()->with('success', "Reminder sent to {$empName}.");
    }

    /**
     * Send bulk reminder nudges for an SOP.
     */
    public function bulkRemind(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
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

        return back()->with('success', "Reminders sent to {$count} pending employees.");
    }

    /**
     * Export compliance audit log as CSV.
     */
    public function exportAudit(int $id): mixed
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        return $this->sopRepository->exportAudit($id, $tenantId);
    }

    /**
     * Store new SOP Category.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:255',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['company_id'] = auth()->user()?->company_id;
        $validated['color'] = $validated['color'] ?? '#3b82f6';

        SopCategory::create($validated);

        return redirect()->route('hrms.sop.index', ['active_tab' => 'categories'])
            ->with('success', 'SOP Category created successfully.');
    }

    /**
     * Update SOP Category.
     */
    public function updateCategory(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
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
            ->with('success', 'SOP Category updated successfully.');
    }

    /**
     * Delete SOP Category.
     */
    public function destroyCategory(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $cat = SopCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $cat->delete();

        return redirect()->route('hrms.sop.index', ['active_tab' => 'categories'])
            ->with('success', 'SOP Category deleted successfully.');
    }
}
