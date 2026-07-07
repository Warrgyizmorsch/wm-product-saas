<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeavePlan;
use App\Domains\HRMS\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveStructureController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        // Programmatically run schema updates if they haven't been applied yet
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('leave_plans')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_07_02_170000_create_leave_structures_tables.php',
                    '--force' => true
                ]);
            }
        } catch (\Exception $e) {
            // Silently capture any setup errors
        }

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

        return view('modules.hrms.leave-structure.index', compact('companies', 'leavePlans', 'selectedPlan'));
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

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $newPlan->id])->with('success', 'Leave Plan created successfully.');
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

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $leavePlan->id])->with('success', 'Leave Plan updated successfully.');
    }

    public function destroyPlan(LeavePlan $leavePlan)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $leavePlan->delete();
        return redirect()->route('hrms.leave-structure.index')->with('success', 'Leave Plan deleted successfully.');
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

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $request->leave_plan_id])->with('success', 'Leave Type created successfully.');
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

        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $request->leave_plan_id])->with('success', 'Leave Type updated successfully.');
    }

    public function destroyType(LeaveType $leaveType)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $planId = $leaveType->leave_plan_id;
        $leaveType->delete();
        return redirect()->route('hrms.leave-structure.index', ['plan_id' => $planId])->with('success', 'Leave Type deleted successfully.');
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
}
