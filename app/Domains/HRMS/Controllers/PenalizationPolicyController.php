<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\AttendancePenalty;
use Illuminate\Http\Request;

class PenalizationPolicyController extends Controller
{
    public function index(Request $request)
    {
        // Programmatically run schema updates if they haven't been applied yet
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('attendance_penalties')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path' => 'database/migrations/2026_07_02_180000_create_attendance_penalties_tables.php',
                    '--force' => true
                ]);
            }
        } catch (\Exception $e) {
            // Silently capture any setup errors
        }

        $companies = Company::all();
        $leaveTypes = LeaveType::where('status', true)->get();
        
        // Fetch all configuration rules keyed by their rule type for easy reference
        $rules = AttendancePenalty::all()->keyBy('rule_type');
        
        // Track which policy type is active (defaulting to 'no_attendance')
        $selectedType = $request->query('policy_type', 'no_attendance');

        return view('modules.hrms.penalization-policy.index', compact('companies', 'leaveTypes', 'rules', 'selectedType'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'rule_type' => 'required|in:no_attendance,late_arrival,under_hours,missing_logs',
            'company_id' => 'nullable|integer',
            'grace_period_minutes' => 'nullable|integer|min:0',
            'threshold_count' => 'nullable|integer|min:0',
            'penalty_action' => 'required|in:leave_deduction,salary_deduction',
            'leave_type_id' => 'nullable|integer|exists:leave_types,id',
            'penalty_value' => 'required|numeric|min:0',
            'status' => 'required',
        ]);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        // Find or create rule setting for this type and company scoping
        AttendancePenalty::updateOrCreate(
            [
                'rule_type' => $request->rule_type,
                'company_id' => $request->company_id,
            ],
            [
                'grace_period_minutes' => $request->grace_period_minutes ?? 0,
                'threshold_count' => $request->threshold_count ?? 0,
                'penalty_action' => $request->penalty_action,
                'leave_type_id' => $request->leave_type_id,
                'penalty_value' => $request->penalty_value,
                'status' => $status,
            ]
        );

        return redirect()->route('hrms.penalization-policy.index', ['policy_type' => $request->rule_type])
            ->with('success', 'Penalization Policy updated successfully.');
    }
}
