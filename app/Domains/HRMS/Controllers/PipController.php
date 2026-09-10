<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCategory;
use App\Domains\HRMS\Models\PipCheckin;
use App\Domains\HRMS\Models\PipObjective;
use App\Domains\HRMS\Models\PipPolicyTemplate;
use App\Domains\HRMS\Services\PipService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PipController extends Controller
{
    public function __construct(
        private readonly PipService $pipService
    ) {}

    /**
     * Dashboard & Master List View.
     */
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $activeTab = $request->input('active_tab', $request->input('tab', 'plans'));
        $search = $request->input('search');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');
        $categoryId = $request->input('category_id');

        // Query Plans
        $plansQuery = PerformanceImprovementPlan::query()
            ->where('tenant_id', $tenantId)
            ->with(['employee.department', 'employee.designation', 'manager', 'hrRepresentative', 'category', 'objectives', 'checkins']);

        if ($search) {
            $plansQuery->where(function ($q) use ($search) {
                $q->where('pip_number', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('employee_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $plansQuery->where('status', $status);
        }

        if ($departmentId) {
            $plansQuery->whereHas('employee', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($categoryId) {
            $plansQuery->where('pip_category_id', $categoryId);
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'oldest'   => $plansQuery->oldest('id'),
            'pip_asc'  => $plansQuery->orderBy('pip_number', 'asc'),
            'pip_desc' => $plansQuery->orderBy('pip_number', 'desc'),
            default    => $plansQuery->latest('id'),
        };

        $plans = $plansQuery->paginate(15)->appends($request->all());

        // Stats Computation
        $baseStats = PerformanceImprovementPlan::where('tenant_id', $tenantId);
        $totalActive = (clone $baseStats)->whereIn('status', ['active', 'under_review', 'extended'])->count();
        $onTrackCount = (clone $baseStats)->where('status', 'active')->count();
        $atRiskCount = (clone $baseStats)->where('status', 'under_review')->count();
        $completedCount = (clone $baseStats)->where('status', 'completed_success')->count();
        $terminatedCount = (clone $baseStats)->where('status', 'failed_terminated')->count();

        // Masters & Select Data
        $categoriesList = PipCategory::where('tenant_id', $tenantId)->get();
        $policyTemplatesList = PipPolicyTemplate::where('tenant_id', $tenantId)->get();

        $categories = PipCategory::where('tenant_id', $tenantId)->latest('id')->paginate(10, ['*'], 'categories_page')->appends($request->all());
        $policyTemplates = PipPolicyTemplate::where('tenant_id', $tenantId)->latest('id')->paginate(10, ['*'], 'templates_page')->appends($request->all());

        $employees = Employee::where('tenant_id', $tenantId)->orderBy('full_name')->get();
        $departments = Department::where('tenant_id', $tenantId)->get();

        return view('modules.hrms.pip.index', compact(
            'plans',
            'activeTab',
            'totalActive',
            'onTrackCount',
            'atRiskCount',
            'completedCount',
            'terminatedCount',
            'categories',
            'categoriesList',
            'policyTemplates',
            'policyTemplatesList',
            'employees',
            'departments'
        ));
    }

    /**
     * Store new PIP plan.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'manager_id'         => 'nullable|exists:employees,id',
            'pip_category_id'    => 'nullable|exists:pip_categories,id',
            'reason_category'   => 'nullable|string|max:100',
            'reason_details'    => 'required|string',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after_or_equal:start_date',
            'checkin_frequency'  => 'required|in:weekly,biweekly,monthly',
            'objectives'         => 'nullable|array',
            'objectives.*.title' => 'required_with:objectives|string|max:255',
            'objectives.*.description' => 'nullable|string',
            'objectives.*.target_criteria' => 'nullable|string',
            'objectives.*.support_provided' => 'nullable|string',
        ]);

        $validated['hr_representative_id'] = auth()->id();
        $this->pipService->createPip($validated);

        return redirect()->route('hrms.pip.index')
            ->with('success', 'Performance Improvement Plan initiated successfully.');
    }

    /**
     * Display PIP detailed workspace.
     */
    public function show(PerformanceImprovementPlan $pip): View
    {
        $pip->load([
            'employee.department',
            'employee.designation',
            'employee.reportingManager',
            'manager',
            'hrRepresentative',
            'category',
            'objectives',
            'checkins.reviewer'
        ]);

        return view('modules.hrms.pip.show', compact('pip'));
    }

    /**
     * Update main PIP plan metadata.
     */
    public function update(Request $request, PerformanceImprovementPlan $pip): RedirectResponse
    {
        $validated = $request->validate([
            'pip_category_id'   => 'nullable|exists:pip_categories,id',
            'reason_details'    => 'required|string',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'status'            => 'required|string',
        ]);

        $pip->update($validated);

        return redirect()->back()->with('success', 'PIP plan details updated successfully.');
    }

    /**
     * Delete PIP plan and associated records.
     */
    public function destroy(PerformanceImprovementPlan $pip): RedirectResponse
    {
        $pip->objectives()->delete();
        $pip->checkins()->delete();
        $pip->delete();

        return redirect()->route('hrms.pip.index')->with('success', 'PIP plan deleted successfully.');
    }

    /**
     * Add SMART Objective goal.
     */
    public function storeObjective(Request $request, PerformanceImprovementPlan $pip): RedirectResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'target_criteria'  => 'nullable|string',
            'support_provided' => 'nullable|string',
        ]);

        $validated['tenant_id'] = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $validated['pip_id'] = $pip->id;
        $validated['status'] = 'pending';

        PipObjective::create($validated);

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', 'SMART Objective added successfully.');
    }

    /**
     * Update Objective status.
     */
    public function updateObjectiveStatus(Request $request, PerformanceImprovementPlan $pip, PipObjective $objective): RedirectResponse
    {
        $validated = $request->validate([
            'status'          => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
            'manager_remarks' => 'nullable|string',
        ]);

        $objective->update($validated);

        return redirect()->back()->with('success', 'Objective status updated.');
    }

    /**
     * Update SMART Objective full details.
     */
    public function updateObjective(Request $request, PerformanceImprovementPlan $pip, PipObjective $objective): RedirectResponse
    {
        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'description'      => 'nullable|string',
            'target_criteria'  => 'nullable|string',
            'support_provided' => 'nullable|string',
            'status'           => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
        ]);

        $objective->update($validated);

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', 'SMART Objective updated successfully.');
    }

    /**
     * Delete SMART Objective.
     */
    public function destroyObjective(PerformanceImprovementPlan $pip, PipObjective $objective): RedirectResponse
    {
        $objective->delete();

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', 'SMART Objective removed successfully.');
    }

    /**
     * Store 1-on-1 Milestone Check-in.
     */
    public function storeCheckin(Request $request, PerformanceImprovementPlan $pip): RedirectResponse
    {
        $validated = $request->validate([
            'checkin_date'    => 'required|date',
            'rating_status'   => 'required|in:on_track,off_track,at_risk,exceeding',
            'manager_comments'=> 'required|string',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        PipCheckin::create([
            'tenant_id'        => $tenantId,
            'pip_id'           => $pip->id,
            'review_date'      => $validated['checkin_date'],
            'rating_status'    => $validated['rating_status'],
            'manager_comments' => $validated['manager_comments'],
            'reviewer_id'      => auth()->id(),
        ]);

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', 'Milestone check-in recorded successfully.');
    }

    /**
     * Update 1-on-1 Milestone Check-in.
     */
    public function updateCheckin(Request $request, PerformanceImprovementPlan $pip, PipCheckin $checkin): RedirectResponse
    {
        $validated = $request->validate([
            'checkin_date'    => 'required|date',
            'rating_status'   => 'required|in:on_track,off_track,at_risk,exceeding',
            'manager_comments'=> 'required|string',
        ]);

        $checkin->update([
            'review_date'      => $validated['checkin_date'],
            'rating_status'    => $validated['rating_status'],
            'manager_comments' => $validated['manager_comments'],
        ]);

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', 'Milestone check-in updated successfully.');
    }

    /**
     * Delete 1-on-1 Milestone Check-in.
     */
    public function destroyCheckin(PerformanceImprovementPlan $pip, PipCheckin $checkin): RedirectResponse
    {
        $checkin->delete();

        return redirect()->route('hrms.pip.show', $pip->id)
            ->with('success', 'Milestone check-in log deleted successfully.');
    }

    /**
     * Evaluate & Close PIP.
     */
    public function evaluate(Request $request, PerformanceImprovementPlan $pip): RedirectResponse
    {
        $validated = $request->validate([
            'evaluation_outcome' => 'required|in:successful_completion,pip_extension,role_reassignment,termination',
            'final_remarks'      => 'required|string',
            'extension_days'     => 'nullable|required_if:evaluation_outcome,pip_extension|integer|min:7|max:90',
        ]);

        $this->pipService->evaluateFinalOutcome($pip, $validated);

        $message = match ($validated['evaluation_outcome']) {
            'successful_completion' => 'PIP closed successfully. Employee has passed the improvement plan.',
            'pip_extension'         => 'PIP period extended by ' . ($validated['extension_days'] ?? 30) . ' days.',
            'role_reassignment'     => 'PIP closed. Employee marked for role reassignment.',
            'termination'           => 'PIP closed. Employment termination process initiated.',
            default                 => 'PIP evaluation submitted.',
        };

        return redirect()->route('hrms.pip.show', $pip->id)->with('success', $message);
    }

    /**
     * Master: Store Category
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:50',
            'description' => 'nullable|string',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $validated['tenant_id'] = $tenantId;

        PipCategory::create($validated);

        return redirect()->route('hrms.pip.index', ['active_tab' => 'categories'])
            ->with('success', 'PIP Category created successfully.');
    }

    /**
     * Master: Store Policy Template
     */
    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'duration_days'     => 'required|integer|min:7|max:180',
            'checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'description'       => 'nullable|string',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $validated['tenant_id'] = $tenantId;

        PipPolicyTemplate::create($validated);

        return redirect()->route('hrms.pip.index', ['active_tab' => 'templates'])
            ->with('success', 'PIP Policy Template created successfully.');
    }

    /**
     * Master: Delete Category
     */
    public function destroyCategory(PipCategory $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('hrms.pip.index', ['active_tab' => 'categories'])
            ->with('success', 'PIP Category deleted successfully.');
    }

    /**
     * Master: Delete Policy Template
     */
    public function destroyTemplate(PipPolicyTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('hrms.pip.index', ['active_tab' => 'templates'])
            ->with('success', 'PIP Policy Template deleted successfully.');
    }
}
