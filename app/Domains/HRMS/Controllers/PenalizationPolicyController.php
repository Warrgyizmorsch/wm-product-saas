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
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $companies = Company::all();
        $leaveTypes = LeaveType::where('status', true)
            ->get()
            ->unique('name')
            ->values();
        
        // Fetch all configuration rules keyed by their rule type for easy reference
        $rules = AttendancePenalty::all()->keyBy('rule_type');
        
        // Track which policy type is active (defaulting to 'late_arrival')
        $selectedType = $request->query('policy_type', 'late_arrival');

        return view('modules.hrms.penalization-policy.index', compact('companies', 'leaveTypes', 'rules', 'selectedType'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasHrPermission('hr.settings.manage'), 403);

        $rules = [
            'rule_type' => 'required|in:late_arrival,under_hours,missing_logs',
            'company_id' => 'nullable|integer',
            'status' => 'required',
        ];

        if ($request->rule_type === 'late_arrival') {
            $rules['grace_period_minutes'] = 'required|integer|min:0';
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.min_occurrence'] = 'required|integer|min:1';
            $rules['penalty_tiers.*.max_occurrence'] = 'nullable|integer|min:1';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,leave_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable|integer|exists:leave_types,id';
        } elseif ($request->rule_type === 'missing_logs') {
            $rules['threshold_count'] = 'required|integer|min:0';
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.min_occurrence'] = 'required|integer|min:1';
            $rules['penalty_tiers.*.max_occurrence'] = 'nullable|integer|min:1';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,leave_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable|integer|exists:leave_types,id';
        } elseif ($request->rule_type === 'under_hours') {
            $rules['grace_period_hours'] = 'required|numeric|min:0';
            $rules['threshold_count'] = 'required|integer|min:0';
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.hours_threshold'] = 'required|numeric|min:0|max:24';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,leave_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable|integer|exists:leave_types,id';
        }

        $request->validate($rules);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $updateData = [
            'status' => $status,
        ];

        if ($request->rule_type === 'late_arrival') {
            $updateData['grace_period_minutes'] = $request->grace_period_minutes ?? 0;
            // Clean up the penalty tiers to store
            $tiers = [];
            if ($request->has('penalty_tiers') && is_array($request->penalty_tiers)) {
                foreach ($request->penalty_tiers as $tier) {
                    $tiers[] = [
                        'min_occurrence' => (int) $tier['min_occurrence'],
                        'max_occurrence' => $tier['max_occurrence'] !== null && $tier['max_occurrence'] !== '' ? (int) $tier['max_occurrence'] : null,
                        'penalty_action' => $tier['penalty_action'],
                        'penalty_value' => (float) $tier['penalty_value'],
                        'leave_type_id' => !empty($tier['leave_type_id']) ? (int) $tier['leave_type_id'] : null,
                    ];
                }
            }
            $updateData['penalty_tiers'] = $tiers;
            $updateData['threshold_count'] = 0;
            $updateData['penalty_action'] = 'salary_deduction';
            $updateData['leave_type_id'] = null;
            $updateData['penalty_value'] = 0.00;
        } elseif ($request->rule_type === 'missing_logs') {
            $updateData['grace_period_minutes'] = 0;
            $updateData['threshold_count'] = (int) $request->threshold_count;
            // Clean up the penalty tiers to store (min/max occurrence-based)
            $tiers = [];
            if ($request->has('penalty_tiers') && is_array($request->penalty_tiers)) {
                foreach ($request->penalty_tiers as $tier) {
                    $tiers[] = [
                        'min_occurrence' => (int) $tier['min_occurrence'],
                        'max_occurrence' => $tier['max_occurrence'] !== null && $tier['max_occurrence'] !== '' ? (int) $tier['max_occurrence'] : null,
                        'penalty_action' => $tier['penalty_action'],
                        'penalty_value' => (float) $tier['penalty_value'],
                        'leave_type_id' => !empty($tier['leave_type_id']) ? (int) $tier['leave_type_id'] : null,
                    ];
                }
            }
            $updateData['penalty_tiers'] = $tiers;
            $updateData['penalty_action'] = 'salary_deduction';
            $updateData['leave_type_id'] = null;
            $updateData['penalty_value'] = 0.00;
        } elseif ($request->rule_type === 'under_hours') {
            $updateData['grace_period_minutes'] = (int) (floatval($request->grace_period_hours) * 60);
            $updateData['threshold_count'] = (int) $request->threshold_count;
            // Clean up percentage-based tiers to store
            $tiers = [];
            if ($request->has('penalty_tiers') && is_array($request->penalty_tiers)) {
                foreach ($request->penalty_tiers as $tier) {
                    $tiers[] = [
                        'hours_threshold' => (float) $tier['hours_threshold'],
                        'penalty_action' => $tier['penalty_action'],
                        'penalty_value' => (float) $tier['penalty_value'],
                        'leave_type_id' => !empty($tier['leave_type_id']) ? (int) $tier['leave_type_id'] : null,
                    ];
                }
            }
            $updateData['penalty_tiers'] = $tiers;
            $updateData['penalty_action'] = 'salary_deduction';
            $updateData['leave_type_id'] = null;
            $updateData['penalty_value'] = 0.00;
        }

        // Find or create rule setting for this type and company scoping
        AttendancePenalty::updateOrCreate(
            [
                'rule_type' => $request->rule_type,
                'company_id' => $request->company_id,
            ],
            $updateData
        );

        return redirect()->route('hrms.penalization-policy.index', ['policy_type' => $request->rule_type])
            ->with('success', 'Penalization Policy updated successfully.');
    }
}
