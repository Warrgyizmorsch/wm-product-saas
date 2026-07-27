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
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $data = $this->leaveStructureRepository->getIndexData($request->all());

        return view('modules.hrms.leave-structure.index', $data);
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

        $newPlan = $this->leaveStructureRepository->storePlan([
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

        $this->leaveStructureRepository->updatePlan($leavePlan, [
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

        $this->leaveStructureRepository->destroyPlan($leavePlan);

        return redirect()->route('hrms.leave-structure.index')->with('success', __('hrms.leave.plan_deleted'));
    }

    public function storeType(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

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
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

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
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $planId = $leaveType->leave_plan_id;
        $this->leaveStructureRepository->destroyType($leaveType);

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $planId])->with('success', __('hrms.leave.type_deleted'));
    }
}
