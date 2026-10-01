<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\GoalRepositoryInterface;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalController extends Controller
{
    public function __construct(
        private readonly GoalRepositoryInterface $goalRepository
    ) {}

    /**
     * Resolve current tenant ID and authenticated user context.
     */
    private function resolveContext(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 1;
        $user = auth()->user();

        return [(int) $tenantId, $user];
    }

    /**
     * Display the Main Goals & OKRs Hub (Single Panel ERP Interface).
     */
    public function index(Request $request): View
    {
        [$tenantId, $user] = $this->resolveContext();

        $data = $this->goalRepository->getIndexData($request->all(), $user, $tenantId);

        return view('modules.hrms.goals.index', $data);
    }

    /**
     * Display Goal / OKR Detail Reader & Check-in Timeline.
     */
    public function show(int $id): View
    {
        [$tenantId, $user] = $this->resolveContext();

        $data = $this->goalRepository->getShowData($id, $user, $tenantId);

        return view('modules.hrms.goals.show', $data);
    }

    /**
     * Store new Goal / Objective with key results.
     */
    public function store(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'title'                => 'required|string|max:255',
            'goal_cycle_id'        => 'nullable',
            'custom_goal_cycle'    => 'nullable|string|max:255',
            'goal_category_id'     => 'nullable',
            'custom_goal_category' => 'nullable|string|max:255',
            'owner_type'           => 'required|in:company,department,employee',
            'department_id'        => 'nullable|exists:departments,id',
            'employee_id'          => 'nullable|exists:employees,id',
            'employee_ids'         => 'nullable|array',
            'employee_ids.*'       => 'exists:employees,id',
            'parent_goal_id'       => 'nullable|exists:goals,id',
            'priority'             => 'nullable|in:low,medium,high,critical',
            'due_date'             => 'nullable|date',
        ]);

        try {
            $data = $request->all();

            // Structure key results from form repeater if submitted
            if ($request->filled('kr_title') && is_array($request->kr_title)) {
                $krs = [];
                foreach ($request->kr_title as $idx => $title) {
                    if (empty(trim($title))) {
                        continue;
                    }
                    $krs[] = [
                        'title'         => trim($title),
                        'metric_type'   => $request->kr_metric_type[$idx] ?? 'percentage',
                        'unit'          => $request->kr_unit[$idx] ?? '%',
                        'start_value'   => (float) ($request->kr_start_value[$idx] ?? 0),
                        'target_value'  => (float) ($request->kr_target_value[$idx] ?? 100),
                        'current_value' => (float) ($request->kr_current_value[$idx] ?? 0),
                        'weightage'     => (float) ($request->kr_weightage[$idx] ?? 100),
                    ];
                }
                $data['key_results'] = $krs;
            }

            $goal = $this->goalRepository->storeGoal($data, $tenantId, $user);

            return redirect()->route('hrms.goals.show', $goal->id)
                ->with('success', "Goal '{$goal->title}' created successfully.");
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create goal: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update an existing Goal.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'title'                => 'required|string|max:255',
            'goal_cycle_id'        => 'nullable',
            'custom_goal_cycle'    => 'nullable|string|max:255',
            'goal_category_id'     => 'nullable',
            'custom_goal_category' => 'nullable|string|max:255',
            'owner_type'           => 'required|in:company,department,employee',
            'department_id'        => 'nullable|exists:departments,id',
            'employee_id'          => 'nullable|exists:employees,id',
            'employee_ids'         => 'nullable|array',
            'employee_ids.*'       => 'exists:employees,id',
            'parent_goal_id'       => 'nullable|exists:goals,id',
            'health_status'        => 'nullable|in:on_track,at_risk,behind,completed,cancelled',
            'priority'             => 'nullable|in:low,medium,high,critical',
            'due_date'             => 'nullable|date',
        ]);

        try {
            $data = $request->all();

            if ($request->filled('kr_title') && is_array($request->kr_title)) {
                $krs = [];
                foreach ($request->kr_title as $idx => $title) {
                    if (empty(trim($title))) {
                        continue;
                    }
                    $krs[] = [
                        'id'            => $request->kr_id[$idx] ?? null,
                        'title'         => trim($title),
                        'metric_type'   => $request->kr_metric_type[$idx] ?? 'percentage',
                        'unit'          => $request->kr_unit[$idx] ?? '%',
                        'start_value'   => (float) ($request->kr_start_value[$idx] ?? 0),
                        'target_value'  => (float) ($request->kr_target_value[$idx] ?? 100),
                        'current_value' => (float) ($request->kr_current_value[$idx] ?? 0),
                        'weightage'     => (float) ($request->kr_weightage[$idx] ?? 100),
                    ];
                }
                $data['key_results'] = $krs;
            }

            $this->goalRepository->updateGoal($id, $data, $tenantId, $user);

            return redirect()->back()->with('success', 'Goal updated successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to update goal: ' . $e->getMessage());
        }
    }

    /**
     * Delete an existing Goal.
     */
    public function destroy(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->goalRepository->deleteGoal($id, $tenantId);
            return redirect()->route('hrms.goals.index')->with('success', 'Goal deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete goal: ' . $e->getMessage());
        }
    }

    /**
     * Record a fast Progress Check-in on a Goal or Key Result.
     */
    public function checkIn(Request $request, int $id): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'goal_key_result_id' => 'nullable|exists:goal_key_results,id',
            'new_value'          => 'nullable|numeric',
            'health_status'      => 'required|in:on_track,at_risk,behind',
            'comment'            => 'required|string|max:1000',
            'blockers'           => 'nullable|string|max:1000',
        ]);

        try {
            $this->goalRepository->recordCheckIn($id, $request->all(), $tenantId, $user);

            return redirect()->back()->with('success', 'Progress check-in recorded successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Check-in failed: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CYCLES & CATEGORIES MASTER ACTIONS
    // =========================================================================

    public function storeCycle(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'       => 'required|string|max:100',
            'code'       => 'nullable|string|max:50',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'status'     => 'nullable|in:planning,active,review,closed',
        ]);

        try {
            $this->goalRepository->storeCycle($request->all(), $tenantId, $user);

            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Goal Cycle created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create cycle: ' . $e->getMessage());
        }
    }

    public function destroyCycle(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->goalRepository->deleteCycle($id, $tenantId);
            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Goal Cycle deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete cycle: ' . $e->getMessage());
        }
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        [$tenantId, $user] = $this->resolveContext();

        $request->validate([
            'name'  => 'required|string|max:100',
            'code'  => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'icon'  => 'nullable|string|max:50',
        ]);

        try {
            $this->goalRepository->storeCategory($request->all(), $tenantId, $user);

            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Strategic Pillar / Category created successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create category: ' . $e->getMessage());
        }
    }

    public function destroyCategory(int $id): RedirectResponse
    {
        [$tenantId] = $this->resolveContext();

        try {
            $this->goalRepository->deleteCategory($id, $tenantId);
            return redirect()->route('hrms.goals.index', ['active_tab' => 'cycles_pillars'])
                ->with('success', 'Category deleted successfully.');
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete category: ' . $e->getMessage());
        }
    }
}
