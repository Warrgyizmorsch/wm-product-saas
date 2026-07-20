<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveBalance;
use Illuminate\Http\Request;

class LeaveStructureController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = Company::all();
        $leavePlans = LeavePlan::with(['company', 'types'])->get();

        $selectedPlanId = $request->query('plan_id');
        $selectedPlan = null;
        if ($selectedPlanId) {
            $selectedPlan = $leavePlans->firstWhere('id', $selectedPlanId);
        }
        if (!$selectedPlan && $leavePlans->isNotEmpty()) {
            $selectedPlan = $leavePlans->first();
        }

        $leaveTypes = collect();

        $ltSearch = $request->string('lt_search')->trim()->value();
        $ltSort = $request->string('lt_sort')->value() ?: 'name_asc';
        $ltType = $request->filled('lt_type') ? $request->string('lt_type')->value() : null;

        if ($selectedPlan) {
            $typesQuery = $selectedPlan->types();

            if ($ltSearch !== '') {
                $typesQuery->where(function ($query) use ($ltSearch): void {
                    $query->where('name', 'like', "%{$ltSearch}%")
                        ->orWhere('code', 'like', "%{$ltSearch}%");
                });
            }

            if ($ltType !== null && $ltType !== '') {
                $typesQuery->where('type', $ltType);
            }

            switch ($ltSort) {
                case 'name_desc':
                    $typesQuery->orderBy('name', 'desc');
                    break;
                case 'quota_asc':
                    $typesQuery->orderBy('quota', 'asc');
                    break;
                case 'quota_desc':
                    $typesQuery->orderBy('quota', 'desc');
                    break;
                case 'name_asc':
                default:
                    $typesQuery->orderBy('name', 'asc');
                    break;
            }

            $leaveTypes = $typesQuery->paginate(10, ['*'], 'lt_page')->withQueryString();
        }

        return view('modules.hrms.leave-structure.index', compact('companies', 'leavePlans', 'leaveTypes', 'selectedPlan', 'ltSearch', 'ltSort', 'ltType'));
    }

    public function storePlan(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'effective_from' => 'required|date',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $newPlan = LeavePlan::create([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'effective_from' => $request->effective_from,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $newPlan->id])->with('success', __('hrms.leave.plan_created'));
    }

    public function updatePlan(Request $request, LeavePlan $leavePlan)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'effective_from' => 'required|date',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $leavePlan->update([
            'company_id' => $request->company_id,
            'name' => $request->name,
            'effective_from' => $request->effective_from,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leavePlan->id])->with('success', __('hrms.leave.plan_updated'));
    }

    public function destroyPlan(LeavePlan $leavePlan)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $leavePlan->delete();
        return redirect()->route('hrms.leave-structure.index')->with('success', __('hrms.leave.plan_deleted'));
    }

    public function storeType(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'leave_plan_id' => 'required|integer|exists:leave_plans,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required|in:paid,unpaid',
            'color' => 'required|max:20',
            'quota' => 'required|numeric|min:0',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        LeaveType::create([
            'leave_plan_id' => $request->leave_plan_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'color' => $request->color,
            'quota' => $request->quota,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $request->leave_plan_id])->with('success', __('hrms.leave.type_created'));
    }

    public function updateType(Request $request, LeaveType $leaveType)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'leave_plan_id' => 'required|integer|exists:leave_plans,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'type' => 'required|in:paid,unpaid',
            'color' => 'required|max:20',
            'quota' => 'required|numeric|min:0',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $leaveType->update([
            'leave_plan_id' => $request->leave_plan_id,
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'color' => $request->color,
            'quota' => $request->quota,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $request->leave_plan_id])->with('success', __('hrms.leave.type_updated'));
    }

    public function destroyType(LeaveType $leaveType)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $planId = $leaveType->leave_plan_id;
        $leaveType->delete();
        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $planId])->with('success', __('hrms.leave.type_deleted'));
    }

    public function updateRules(Request $request, LeaveType $leaveType)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'rules' => 'required|array'
        ]);

        $leaveType->update([
            'rules' => $request->rules
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rules updated successfully.'
        ]);
    }

    public function renewPlanBalances(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        try {
            $request->validate([
                'leave_plan_id' => 'required|exists:leave_plans,id',
                'yearend_rules' => 'nullable|array',
            ]);

            $leavePlan = LeavePlan::findOrFail($request->leave_plan_id);

            // 1. Update rules persistently in database if yearend_rules are provided
            if ($request->filled('yearend_rules')) {
                foreach ($request->yearend_rules as $typeId => $ruleSet) {
                    $ltype = LeaveType::where('leave_plan_id', $leavePlan->id)->find($typeId);
                    if ($ltype) {
                        $currentRules = $ltype->rules ?? [];
                        $currentRules['yearend'] = [
                            'action' => $ruleSet['action'] ?? 'lapse',
                            'max_carry' => floatval($ruleSet['max_carry'] ?? 0.0),
                            'max_encash' => floatval($currentRules['yearend']['max_encash'] ?? 0.0),
                        ];
                        $ltype->update(['rules' => $currentRules]);
                    }
                }
                $leavePlan->load('types');
            }

            // Fetch all active employees assigned to this plan
            $employees = Employee::where('leave_plan_id', $leavePlan->id)
                ->where('status', true)
                ->get();

            foreach ($employees as $employee) {
                foreach ($leavePlan->types as $ltype) {
                    $balance = LeaveBalance::firstOrCreate([
                        'tenant_id' => $employee->tenant_id,
                        'company_id' => $employee->company_id,
                        'employee_id' => $employee->id,
                        'leave_type_id' => $ltype->id,
                    ], [
                        'allocated' => floatval($ltype->quota),
                        'used' => 0.0,
                    ]);

                    // Calculate rollover based on Year-End Rules
                    $rules = $ltype->rules ?? [];
                    $action = $rules['yearend']['action'] ?? 'lapse';
                    $maxCarry = floatval($rules['yearend']['max_carry'] ?? 0.0);

                    $remaining = floatval($balance->remaining);
                    $rollover = 0.0;

                    if ($action === 'carry_forward' && $remaining > 0.0) {
                        $rollover = min($remaining, $maxCarry);
                    }

                    $newAllocated = floatval($ltype->quota) + $rollover;

                    $balance->update([
                        'allocated' => $newAllocated,
                        'used' => 0.0,
                    ]);
                }
            }

            // Update effective_from to today to start the new cycle today, set last_renewed_at to today, and activate the plan if it was inactive
            $leavePlan->update([
                'effective_from' => now()->toDateString(),
                'last_renewed_at' => now()->toDateString(),
                'status' => true,
            ]);

            return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leavePlan->id])
                ->with('success', 'Leave plan balances renewed successfully for all assigned employees.');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to renew leave plan balances: ' . $e->getMessage());
        }
    }

    public function transitionView(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = \App\Domains\HRMS\Models\Company::all();
        $departments = \App\Domains\HRMS\Models\Department::all();
        $leavePlans = LeavePlan::where('status', true)->get();
        $employees = \App\Domains\HRMS\Models\Employee::where('status', true)->with('leavePlan')->get();

        return view('modules.hrms.leave-structure.transition', compact('companies', 'departments', 'leavePlans', 'employees'));
    }

    public function processTransition(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
            'new_leave_plan_id' => 'required|exists:leave_plans,id',
            'leave_transition_action' => 'required|in:transfer,prorate',
            'leave_transition_unused' => 'required|in:carry,lapse',
        ]);

        try {
            $employeeIds = $request->employee_ids;
            $newPlanId = $request->new_leave_plan_id;
            $action = $request->leave_transition_action;
            $unusedAction = $request->leave_transition_unused;

            $count = 0;
            foreach ($employeeIds as $empId) {
                $employee = \App\Domains\HRMS\Models\Employee::find($empId);
                if ($employee && (int)$employee->leave_plan_id !== (int)$newPlanId) {
                    $oldPlanId = $employee->leave_plan_id;
                    
                    // Update employee plan ID
                    $employee->update([
                        'leave_plan_id' => $newPlanId
                    ]);

                    // Run transition logic
                    $employee->migrateToLeavePlan($oldPlanId, $newPlanId, $action, $unusedAction);
                    $count++;
                }
            }

            return redirect()->route('hrms.leave-structure.index', ['plan_id' => $newPlanId])
                ->with('success', __('hrms.leave.transition_success', ['count' => $count]));
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', __('hrms.leave.transition_error', ['message' => $e->getMessage()]));
        }
    }
}
