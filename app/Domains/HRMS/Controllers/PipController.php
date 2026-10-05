<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Models\PipCategory;
use App\Domains\HRMS\Models\PipPolicyTemplate;
use App\Domains\HRMS\Repositories\PipRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PipController extends Controller
{
    public function __construct(
        private readonly PipRepositoryInterface $pipRepository
    ) {}

    /**
     * Dashboard & Master List View.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PerformanceImprovementPlan::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        $data = $this->pipRepository->getIndexData($request->all(), $user, $tenantId);

        return view('modules.hrms.pip.index', $data);
    }

    /**
     * View PIP Details, Objectives, Timeline and Check-ins.
     */
    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $data = $this->pipRepository->getShowData($id, $tenantId);

        $this->authorize('view', $data['plan']);

        return view('modules.hrms.pip.show', $data);
    }

    /**
     * Store new PIP Plan.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PerformanceImprovementPlan::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Harmonize field names from web form
        if ($request->filled('policy_template_id') && !$request->filled('pip_policy_template_id')) {
            $request->merge(['pip_policy_template_id' => $request->input('policy_template_id')]);
        }

        // Auto-resolve reason_category if not explicitly provided
        if (!$request->filled('reason_category')) {
            if ($request->filled('pip_category_id')) {
                $category = PipCategory::where('tenant_id', $tenantId)->find($request->input('pip_category_id'));
                $request->merge(['reason_category' => $category?->name ?? 'Performance']);
            } else {
                $request->merge(['reason_category' => 'Performance']);
            }
        }

        // Auto-resolve manager_id from employee profile if not selected
        if (!$request->filled('manager_id') && $request->filled('employee_id')) {
            $emp = Employee::where('tenant_id', $tenantId)->find($request->input('employee_id'));
            $mgrId = $emp?->reporting_manager_id ?? $emp?->id;
            $request->merge(['manager_id' => $mgrId]);
        }

        // Auto-sanitize and align start_date and end_date
        $startDateStr = $request->input('start_date') ?: Carbon::today()->toDateString();
        $start = Carbon::parse($startDateStr);

        if ($request->filled('end_date')) {
            $end = Carbon::parse($request->input('end_date'));
            if ($end->lessThan($start)) {
                $duration = $request->filled('duration_days') ? (int)$request->input('duration_days') : 30;
                $end = $start->copy()->addDays(max(7, $duration));
            }
        } else {
            $duration = $request->filled('duration_days') ? (int)$request->input('duration_days') : 30;
            $end = $start->copy()->addDays(max(7, $duration));
        }

        $durationDays = max(1, (int)$start->diffInDays($end));
        $request->merge([
            'start_date'    => $start->toDateString(),
            'end_date'      => $end->toDateString(),
            'duration_days' => $durationDays,
        ]);

        $validated = $request->validate([
            'employee_id'            => 'required|exists:employees,id',
            'manager_id'             => 'nullable|exists:employees,id',
            'pip_category_id'        => 'nullable|exists:pip_categories,id',
            'pip_policy_template_id' => 'nullable|exists:pip_policy_templates,id',
            'reason_category'        => 'nullable|string|max:255',
            'reason_details'         => 'required|string',
            'start_date'             => 'required|date',
            'end_date'               => 'nullable|date',
            'duration_days'          => 'nullable|integer|min:1|max:365',
            'checkin_frequency'      => 'required|in:weekly,biweekly,monthly',
            'support_provided'       => 'nullable|string',
            'consequences'           => 'nullable|string',
            'initial_notes'          => 'nullable|string',
        ]);

        $validated['hr_representative_id'] = auth()->id();

        $plan = $this->pipRepository->storePlan($validated, $tenantId);

        return redirect()->route('hrms.pip.show', $plan->id)
            ->with('success', "Performance Improvement Plan #{$plan->pip_number} created successfully.");
    }

    /**
     * Update PIP overview.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Auto-resolve reason_category if not explicitly provided
        if (!$request->filled('reason_category') && $request->filled('pip_category_id')) {
            $category = PipCategory::where('tenant_id', $tenantId)->find($request->input('pip_category_id'));
            $request->merge(['reason_category' => $category?->name ?? 'Performance']);
        }

        if ($request->filled('start_date')) {
            $start = Carbon::parse($request->input('start_date'));
            if ($request->filled('end_date')) {
                $end = Carbon::parse($request->input('end_date'));
                if ($end->lessThan($start)) {
                    $end = $start->copy()->addDays(30);
                }
                $request->merge([
                    'start_date'    => $start->toDateString(),
                    'end_date'      => $end->toDateString(),
                    'duration_days' => max(1, (int)$start->diffInDays($end)),
                ]);
            }
        }

        $validated = $request->validate([
            'pip_category_id'   => 'nullable|exists:pip_categories,id',
            'reason_category'   => 'nullable|string|max:255',
            'reason_details'    => 'nullable|string',
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date',
            'duration_days'     => 'nullable|integer|min:1|max:365',
            'checkin_frequency' => 'nullable|in:weekly,biweekly,monthly',
            'support_provided'  => 'nullable|string',
            'consequences'      => 'nullable|string',
            'initial_notes'     => 'nullable|string',
        ]);

        $this->pipRepository->updatePlan($id, $validated, $tenantId);

        return redirect()->back()->with('success', 'PIP details updated.');
    }

    /**
     * Delete PIP Plan.
     */
    public function destroy(int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->pipRepository->deletePlan($id, $tenantId);

        return redirect()->route('hrms.pip.index')->with('success', 'PIP record deleted successfully.');
    }

    /**
     * Add SMART Objective to PIP.
     */
    public function storeObjective(Request $request, int $pipId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_criteria' => 'required|string',
            'support_provided' => 'nullable|string',
            'weightage' => 'nullable|numeric|min:0|max:100',
            'target_date' => 'nullable|date',
        ]);

        $this->pipRepository->storeObjective($pipId, $validated, $tenantId);

        return redirect()->back()->with('success', 'SMART Objective added to PIP.');
    }

    /**
     * Update Objective status/progress.
     */
    public function updateObjective(Request $request, int $objectiveId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'title'            => 'nullable|string|max:255',
            'description'      => 'nullable|string',
            'target_criteria'  => 'nullable|string',
            'support_provided' => 'nullable|string',
            'status'           => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
            'manager_remarks'  => 'nullable|string',
        ]);

        $this->pipRepository->updateObjective($objectiveId, $validated, $tenantId);

        return redirect()->back()->with('success', 'Objective updated successfully.');
    }

    /**
     * Quick Update Objective Status.
     */
    public function updateObjectiveStatus(Request $request, int $pipId, int $objectiveId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
        ]);

        $this->pipRepository->updateObjective($objectiveId, $validated, $tenantId);

        return redirect()->back()->with('success', 'Objective status updated.');
    }

    /**
     * Remove Objective from PIP.
     */
    public function destroyObjective(int $pipId, mixed $objectiveId = null): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $targetId = $objectiveId ?: $pipId;
        $this->pipRepository->deleteObjective((int)$targetId, $tenantId);

        return redirect()->back()->with('success', 'Objective deleted.');
    }

    /**
     * Log Periodic Check-in / Meeting Review.
     */
    public function storeCheckin(Request $request, int $pipId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Support both web modal form field names and legacy names
        if ($request->filled('checkin_date') && !$request->filled('meeting_date')) {
            $request->merge(['meeting_date' => $request->input('checkin_date')]);
        }
        if ($request->filled('manager_comments') && !$request->filled('progress_summary')) {
            $request->merge(['progress_summary' => $request->input('manager_comments')]);
        }

        $validated = $request->validate([
            'checkin_date'         => 'nullable|date',
            'meeting_date'         => 'nullable|date',
            'rating_status'        => 'nullable|in:on_track,off_track,at_risk,exceeding',
            'rating'               => 'nullable|numeric|min:1|max:5',
            'manager_comments'     => 'nullable|string',
            'progress_summary'     => 'nullable|string',
            'concerns_noted'       => 'nullable|string',
            'support_action_items' => 'nullable|string',
            'employee_comments'    => 'nullable|string',
            'next_checkin_date'    => 'nullable|date',
        ]);

        $this->pipRepository->storeCheckin($pipId, $validated, auth()->id(), $tenantId);

        return redirect()->back()->with('success', 'Milestone check-in review logged successfully.');
    }

    /**
     * Update Milestone Check-in Log.
     */
    public function updateCheckin(Request $request, int $pipId, mixed $checkinId = null): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $targetId = $checkinId ?: $pipId;

        $validated = $request->validate([
            'checkin_date'      => 'nullable|date',
            'rating_status'     => 'required|in:on_track,off_track,at_risk,exceeding',
            'manager_comments'  => 'required|string',
            'employee_comments' => 'nullable|string',
            'action_items'      => 'nullable|string',
        ]);

        $this->pipRepository->updateCheckin((int)$targetId, $validated, $tenantId);

        return redirect()->back()->with('success', 'Milestone check-in updated successfully.');
    }

    /**
     * Delete Milestone Check-in Log.
     */
    public function destroyCheckin(int $pipId, mixed $checkinId = null): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $targetId = $checkinId ?: $pipId;

        $this->pipRepository->deleteCheckin((int)$targetId, $tenantId);

        return redirect()->back()->with('success', 'Milestone check-in log deleted successfully.');
    }

    /**
     * Final Evaluation & Closure of PIP.
     */
    public function evaluate(Request $request, int $pipId): RedirectResponse
    {
        $this->authorize('update', PerformanceImprovementPlan::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Support both evaluation_outcome and final_outcome
        $outcome = $request->input('evaluation_outcome') ?: $request->input('final_outcome');
        $remarks = $request->input('final_remarks') ?: $request->input('final_comments') ?: $request->input('exit_notes');

        $request->merge([
            'final_outcome'  => $outcome,
            'final_comments' => $remarks,
        ]);

        $validated = $request->validate([
            'final_outcome'   => 'required|in:successful_completion,completed_success,pip_extension,extended,role_reassignment,failed_role_change,failed_demoted,termination,failed_terminated',
            'final_comments'  => 'nullable|string',
            'extension_days'  => 'nullable|integer|min:7|max:180',
        ]);

        $this->pipRepository->conclude($pipId, $validated, $tenantId);

        return redirect()->back()->with('success', 'PIP final evaluation and conclusion recorded successfully.');
    }

    /**
     * Alias for conclude.
     */
    public function conclude(Request $request, int $id): RedirectResponse
    {
        return $this->evaluate($request, $id);
    }

    /**
     * Employee Acknowledges PIP Plan.
     */
    public function acknowledge(Request $request, int $id): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'signature_data' => 'required|string',
        ]);

        $this->pipRepository->acknowledge($id, $validated['signature_data'], $tenantId);

        return redirect()->back()->with('success', 'PIP digital acknowledgement recorded.');
    }

    /**
     * Master Category & Template Store Actions.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);

        $this->pipRepository->storeCategory($validated, $tenantId);

        return redirect()->back()->with('success', 'PIP Category added.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Support both 'name' (from modal form) and 'title' (if passed)
        if (!$request->has('name') && $request->has('title')) {
            $request->merge(['name' => $request->input('title')]);
        }
        if (!$request->has('duration_days') && $request->has('default_duration_days')) {
            $request->merge(['duration_days' => $request->input('default_duration_days')]);
        }
        if (!$request->has('checkin_frequency') && $request->has('default_checkin_frequency')) {
            $request->merge(['checkin_frequency' => $request->input('default_checkin_frequency')]);
        }

        $validated = $request->validate([
            'name'              => 'required|string|max:255',
            'duration_days'     => 'required|integer|min:7|max:180',
            'checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'description'       => 'nullable|string',
        ]);

        $this->pipRepository->storeTemplate($validated, $tenantId);

        return redirect()->back()->with('success', 'PIP Policy Template added.');
    }

    public function destroyCategory(mixed $category): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $id = is_object($category) ? $category->id : $category;

        $cat = PipCategory::where('tenant_id', $tenantId)->find($id);
        if ($cat) {
            $cat->delete();
        }

        return redirect()->back()->with('success', 'PIP Category deleted.');
    }

    public function destroyTemplate(mixed $template): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $id = is_object($template) ? $template->id : $template;

        $tmpl = PipPolicyTemplate::where('tenant_id', $tenantId)->find($id);
        if ($tmpl) {
            $tmpl->delete();
        }

        return redirect()->back()->with('success', 'PIP Policy Template deleted.');
    }
}
