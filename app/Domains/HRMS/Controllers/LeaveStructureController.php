<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Repositories\LeaveStructureRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeaveStructureController extends Controller
{
    public function __construct(
        private readonly LeaveStructureRepositoryInterface $leaveStructureRepository
    ) {}

    public function index(Request $request)
    {
        $data = $this->leaveStructureRepository->getIndexData($request->all());

        return view('modules.hrms.leave-structure.index', $data);
    }

    public function storePlan(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'effective_from' => 'required|date',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $newPlan = $this->leaveStructureRepository->storePlan([
            'company_id' => $request->company_id ?: (\App\Domains\HRMS\Models\Company::first()?->id ?? 1),
            'name' => $request->name,
            'effective_from' => $request->effective_from,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $newPlan->id])->with('success', __('hrms.leave.plan_created'));
    }

    public function updatePlan(Request $request, LeavePlan $leavePlan)
    {
        $request->validate([
            'name' => 'required|max:255',
            'company_id' => 'nullable|integer',
            'effective_from' => 'required|date',
            'description' => 'nullable',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->leaveStructureRepository->updatePlan($leavePlan, [
            'company_id' => $request->company_id ?: $leavePlan->company_id ?: (\App\Domains\HRMS\Models\Company::first()?->id ?? 1),
            'name' => $request->name,
            'effective_from' => $request->effective_from,
            'description' => $request->description,
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leavePlan->id])->with('success', __('hrms.leave.plan_updated'));
    }

    public function destroyPlan(LeavePlan $leavePlan)
    {
        $this->leaveStructureRepository->destroyPlan($leavePlan);

        return redirect()->route('hrms.leave-structure.index')->with('success', __('hrms.leave.plan_deleted'));
    }

    public function storeType(Request $request)
    {
        $request->validate([
            'leave_plan_id' => 'required|exists:leave_plans,id',
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'color' => 'nullable|string|max:20',
            'type' => 'required|in:paid,unpaid',
            'unit' => 'required|in:days,hours',
            'quota' => 'required|numeric|min:0',
            'carry_forward' => 'required|boolean',
            'max_carry_forward' => 'nullable|numeric|min:0',
            'encashable' => 'required|boolean',
            'max_encashable' => 'nullable|numeric|min:0',
            'encashment_rate' => 'nullable|numeric|min:0',
            'min_encashment' => 'nullable|numeric|min:0',
            'encashment_calc_type' => 'nullable|string',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->leaveStructureRepository->storeType([
            'leave_plan_id' => $request->leave_plan_id,
            'name' => $request->name,
            'code' => $request->code,
            'color' => $request->color ?: '#3b82f6',
            'type' => $request->type,
            'unit' => $request->unit,
            'quota' => $request->quota,
            'carry_forward' => $request->boolean('carry_forward'),
            'max_carry_forward' => $request->max_carry_forward,
            'encashable' => $request->boolean('encashable'),
            'max_encashable' => $request->max_encashable,
            'encashment_rate' => $request->encashment_rate,
            'min_encashment' => $request->min_encashment,
            'encashment_calc_type' => $request->encashment_calc_type ?: 'gross_salary',
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $request->leave_plan_id])->with('success', __('hrms.leave.type_created'));
    }

    public function updateType(Request $request, LeaveType $leaveType)
    {
        $request->validate([
            'name' => 'required|max:255',
            'code' => 'required|max:50',
            'color' => 'nullable|string|max:20',
            'type' => 'required|in:paid,unpaid',
            'unit' => 'required|in:days,hours',
            'quota' => 'required|numeric|min:0',
            'carry_forward' => 'required|boolean',
            'max_carry_forward' => 'nullable|numeric|min:0',
            'encashable' => 'required|boolean',
            'max_encashable' => 'nullable|numeric|min:0',
            'encashment_rate' => 'nullable|numeric|min:0',
            'min_encashment' => 'nullable|numeric|min:0',
            'encashment_calc_type' => 'nullable|string',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $this->leaveStructureRepository->updateType($leaveType, [
            'name' => $request->name,
            'code' => $request->code,
            'color' => $request->color ?: '#3b82f6',
            'type' => $request->type,
            'unit' => $request->unit,
            'quota' => $request->quota,
            'carry_forward' => $request->boolean('carry_forward'),
            'max_carry_forward' => $request->max_carry_forward,
            'encashable' => $request->boolean('encashable'),
            'max_encashable' => $request->max_encashable,
            'encashment_rate' => $request->encashment_rate,
            'min_encashment' => $request->min_encashment,
            'encashment_calc_type' => $request->encashment_calc_type ?: 'gross_salary',
            'status' => $status,
        ]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leaveType->leave_plan_id])->with('success', __('hrms.leave.type_updated'));
    }

    public function destroyType(LeaveType $leaveType)
    {
        $planId = $leaveType->leave_plan_id;
        $this->leaveStructureRepository->destroyType($leaveType);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $planId])->with('success', __('hrms.leave.type_deleted'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Leave Rules & Renewal
    // ─────────────────────────────────────────────────────────────────────────

    public function updateRules(\Illuminate\Http\Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'rules' => 'required|array',
        ]);

        $leaveType->update(['rules' => $validated['rules']]);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leaveType->leave_plan_id])
            ->with('success', 'Leave policy rules updated successfully.');
    }

    public function renewPlanBalances(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'leave_plan_id' => 'required|exists:leave_plans,id',
            'yearend_rules' => 'nullable|array',
        ]);

        try {
            $leavePlan = LeavePlan::findOrFail($validated['leave_plan_id']);

            if ($request->filled('yearend_rules')) {
                foreach ($request->yearend_rules as $typeId => $ruleSet) {
                    $ltype = LeaveType::where('leave_plan_id', $leavePlan->id)->find($typeId);
                    if ($ltype) {
                        $currentRules = $ltype->rules ?? [];
                        $currentRules['yearend'] = [
                            'action'    => $ruleSet['action'] ?? 'lapse',
                            'max_carry' => floatval($ruleSet['max_carry'] ?? 0.0),
                            'max_encash'=> floatval($currentRules['yearend']['max_encash'] ?? 0.0),
                        ];
                        $ltype->update(['rules' => $currentRules]);
                    }
                }
                $leavePlan->load('types');
            }

            $employees = \App\Domains\HRMS\Models\Employee::where('leave_plan_id', $leavePlan->id)
                ->where('status', true)->get();

            foreach ($employees as $employee) {
                foreach ($leavePlan->types as $ltype) {
                    $balance = \App\Domains\HRMS\Models\LeaveBalance::firstOrCreate([
                        'tenant_id'     => $employee->tenant_id,
                        'company_id'    => $employee->company_id,
                        'employee_id'   => $employee->id,
                        'leave_type_id' => $ltype->id,
                    ], ['allocated' => floatval($ltype->quota), 'used' => 0.0]);

                    $rules          = $ltype->rules ?? [];
                    $action         = $rules['yearend']['action'] ?? 'lapse';
                    $maxCarry       = floatval($rules['yearend']['max_carry'] ?? 0.0);
                    $maxEncash      = floatval($rules['yearend']['max_encash'] ?? 0.0);
                    $remaining      = floatval($balance->remaining);
                    $rollover       = 0.0;
                    $autoEncashDays = 0.0;

                    if ($action === 'carry_forward' && $remaining > 0.0) {
                        $rollover = min($remaining, $maxCarry);
                        $leftoverAfterCarry = max(0.0, $remaining - $rollover);
                        if ($maxEncash > 0.0 && $leftoverAfterCarry > 0.0) {
                            $autoEncashDays = min($leftoverAfterCarry, $maxEncash);
                        }
                    } elseif ($remaining > 0.0 && $maxEncash > 0.0) {
                        $autoEncashDays = min($remaining, $maxEncash);
                    }

                    if ($autoEncashDays > 0.0) {
                        \App\Domains\HRMS\Models\LeaveEncashment::create([
                            'tenant_id'      => $employee->tenant_id,
                            'company_id'     => $employee->company_id,
                            'employee_id'    => $employee->id,
                            'leave_type_id'  => $ltype->id,
                            'requested_days' => $autoEncashDays,
                            'status'         => 'approved',
                            'reason'         => 'Year-end renewal automatic encashment',
                            'approved_by'    => auth()->id(),
                            'approved_at'    => now(),
                        ]);
                    }

                    $newAllocated = round((floatval($ltype->quota) + $rollover) * 2) / 2;
                    $balance->update(['allocated' => $newAllocated, 'used' => 0.0, 'encashed' => 0.0]);
                }
            }

            $leavePlan->update([
                'effective_from'  => now()->toDateString(),
                'last_renewed_at' => now()->toDateString(),
                'status'          => true,
            ]);

            return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leavePlan->id])
                ->with('success', 'Leave plan balances renewed successfully for all assigned employees.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to renew leave plan balances: ' . $e->getMessage());
        }
    }

    public function transitionView(\Illuminate\Http\Request $request)
    {
        $data = $this->leaveStructureRepository->getIndexData($request->all());

        return view('modules.hrms.leave-structure.transition', $data);
    }

    public function processTransition(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'employee_ids'            => 'required|array',
            'employee_ids.*'          => 'exists:employees,id',
            'new_leave_plan_id'       => 'required|exists:leave_plans,id',
            'leave_transition_action' => 'required|in:transfer,prorate',
            'leave_transition_unused' => 'required|in:carry,lapse,encash',
        ]);

        try {
            $employeeIds  = $validated['employee_ids'];
            $newPlanId    = $validated['new_leave_plan_id'];
            $action       = $validated['leave_transition_action'];
            $unusedAction = $validated['leave_transition_unused'];

            // Check for pending leave/encashment
            $pendingEmployees = [];
            foreach ($employeeIds as $empId) {
                $employee = \App\Domains\HRMS\Models\Employee::find($empId);
                if ($employee && (int)$employee->leave_plan_id !== (int)$newPlanId) {
                    $hasPending = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $employee->id)->where('status', 'pending')->exists()
                        || \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $employee->id)->where('status', 'pending')->exists();
                    if ($hasPending) {
                        $pendingEmployees[] = $employee->full_name;
                    }
                }
            }

            if (!empty($pendingEmployees)) {
                return redirect()->back()->with('error',
                    'Cannot transition leave plans. The following employee(s) have pending leave or encashment requests: '
                    . implode(', ', $pendingEmployees));
            }

            $count = 0;
            foreach ($employeeIds as $empId) {
                $employee = \App\Domains\HRMS\Models\Employee::find($empId);
                if ($employee && (int)$employee->leave_plan_id !== (int)$newPlanId) {
                    $oldPlanId = $employee->leave_plan_id;
                    $employee->update(['leave_plan_id' => $newPlanId]);
                    $employee->migrateToLeavePlan($oldPlanId, $newPlanId, $action, $unusedAction);
                    $count++;
                }
            }

            return redirect()->route('hrms.leave-structure.index')
                ->with('success', "Successfully transitioned {$count} employee(s) to the new leave plan.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Leave plan transition failed: ' . $e->getMessage());
        }
    }
}

