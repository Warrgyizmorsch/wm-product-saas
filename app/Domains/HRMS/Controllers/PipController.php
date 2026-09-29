<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\PerformanceImprovementPlan;
use App\Domains\HRMS\Repositories\PipRepositoryInterface;
use App\Http\Controllers\Controller;
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

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'manager_id' => 'required|exists:employees,id',
            'pip_category_id' => 'nullable|exists:pip_categories,id',
            'pip_policy_template_id' => 'nullable|exists:pip_policy_templates,id',
            'reason_category' => 'required|string|max:255',
            'reason_details' => 'required|string',
            'start_date' => 'required|date',
            'duration_days' => 'required|integer|in:30,60,90',
            'checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'support_provided' => 'nullable|string',
            'consequences' => 'nullable|string',
            'initial_notes' => 'nullable|string',
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

        $validated = $request->validate([
            'reason_category' => 'required|string|max:255',
            'reason_details' => 'required|string',
            'support_provided' => 'nullable|string',
            'consequences' => 'nullable|string',
            'initial_notes' => 'nullable|string',
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'target_criteria' => 'required|string',
            'status' => 'required|in:pending,in_progress,achieved,partially_achieved,not_achieved',
            'manager_rating' => 'nullable|numeric|min:1|max:5',
            'manager_feedback' => 'nullable|string',
        ]);

        $this->pipRepository->updateObjective($objectiveId, $validated, $tenantId);

        return redirect()->back()->with('success', 'Objective updated successfully.');
    }

    /**
     * Remove Objective from PIP.
     */
    public function destroyObjective(int $objectiveId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->pipRepository->deleteObjective($objectiveId, $tenantId);

        return redirect()->back()->with('success', 'Objective deleted.');
    }

    /**
     * Log Periodic Check-in / Meeting Review.
     */
    public function storeCheckin(Request $request, int $pipId): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'meeting_date' => 'required|date',
            'rating' => 'required|numeric|min:1|max:5',
            'progress_summary' => 'required|string',
            'concerns_noted' => 'nullable|string',
            'support_action_items' => 'nullable|string',
            'employee_comments' => 'nullable|string',
            'next_checkin_date' => 'nullable|date',
        ]);

        $this->pipRepository->storeCheckin($pipId, $validated, auth()->id(), $tenantId);

        return redirect()->back()->with('success', 'Check-in review logged successfully.');
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
     * Conclude PIP with Final Outcome.
     */
    public function conclude(Request $request, int $id): RedirectResponse
    {
        $this->authorize('update', PerformanceImprovementPlan::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'final_outcome' => 'required|in:completed_success,extended,failed_terminated,failed_role_change,failed_demoted',
            'exit_action' => 'nullable|string',
            'exit_notes' => 'nullable|string',
        ]);

        $this->pipRepository->conclude($id, $validated, $tenantId);

        return redirect()->back()->with('success', 'PIP formal conclusion recorded.');
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

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'default_duration_days' => 'required|integer|in:30,60,90',
            'default_checkin_frequency' => 'required|in:weekly,biweekly,monthly',
            'policy_terms' => 'nullable|string',
            'consequences_text' => 'nullable|string',
            'support_guidelines' => 'nullable|string',
        ]);

        $this->pipRepository->storeTemplate($validated, $tenantId);

        return redirect()->back()->with('success', 'PIP Policy Template added.');
    }
}
