<?php

namespace App\Domains\HRMS\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\AttendancePenalty;
use Illuminate\Http\Request;

use App\Models\Tenant;

class PenalizationPolicyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::all();
        $leaveTypes = LeaveType::where('status', true)
            ->get()
            ->unique('name')
            ->values();
        
        $selectedType = $request->query('policy_type', 'late_arrival');
        
        // Fetch all configuration rules keyed by their rule type for easy reference
        $companyId = null;
        if ($request->has('company_id')) {
            $companyId = $request->query('company_id') !== '' ? $request->query('company_id') : null;
        }
        
        $rules = AttendancePenalty::where('company_id', $companyId)->get()->keyBy('rule_type');

        $businessUnits = \App\Domains\HRMS\Models\BusinessUnit::all();
        $branches = \App\Domains\HRMS\Models\Branch::all();
        $attendanceRules = \App\Domains\HRMS\Models\AttendanceRule::with(['company', 'businessUnit', 'branch'])->get();

        // Retrieve current tenant settings for overtime
        $tenantSettings = [
            'auto_overtime_threshold_hours' => '',
            'overtime_rate_multiplier'      => '',
            'min_overtime_request_hours'    => '',
        ];
        $user = auth()->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant && is_array($tenant->settings)) {
                $tenantSettings['auto_overtime_threshold_hours'] = isset($tenant->settings['auto_overtime_threshold_hours']) ? $tenant->settings['auto_overtime_threshold_hours'] : '';
                $tenantSettings['overtime_rate_multiplier']      = isset($tenant->settings['overtime_rate_multiplier']) ? $tenant->settings['overtime_rate_multiplier'] : '';
                $tenantSettings['min_overtime_request_hours']    = isset($tenant->settings['min_overtime_request_hours']) ? $tenant->settings['min_overtime_request_hours'] : '';
            }
        }

        return view('modules.hrms.penalization-policy.index', compact('companies', 'leaveTypes', 'rules', 'selectedType', 'businessUnits', 'branches', 'attendanceRules', 'tenantSettings'));
    }

    public function store(Request $request)
    {
        $rules = [
            'rule_type' => 'required|in:late_arrival,under_hours,missing_logs',
            'company_id' => 'nullable|integer',
            'status' => 'required',
        ];

        if ($request->rule_type === 'late_arrival') {
            $rules['grace_period_minutes'] = 'required|integer|min:0';
            $rules['threshold_count'] = 'required|integer|min:0';
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.min_occurrence'] = 'required|integer|min:1';
            $rules['penalty_tiers.*.max_occurrence'] = 'nullable|integer|min:1';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,working_hour_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable';
        } elseif ($request->rule_type === 'missing_logs') {
            $rules['threshold_count'] = 'required|integer|min:0';
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.min_occurrence'] = 'required|integer|min:1';
            $rules['penalty_tiers.*.max_occurrence'] = 'nullable|integer|min:1';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,working_hour_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable';
        } elseif ($request->rule_type === 'under_hours') {
            $rules['grace_period_hours'] = 'required|numeric|min:0';
            $rules['threshold_count'] = 'required|integer|min:0';
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.hours_threshold'] = 'required|numeric|min:0|max:24';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,working_hour_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable';
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
                        'leave_type_id' => null,
                    ];
                }
            }
            $updateData['penalty_tiers'] = $tiers;
            $updateData['threshold_count'] = (int) ($request->threshold_count ?? 0);
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
                        'leave_type_id' => null,
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
                        'leave_type_id' => null,
                    ];
                }
            }
            $updateData['penalty_tiers'] = $tiers;
            $updateData['penalty_action'] = 'salary_deduction';
            $updateData['leave_type_id'] = null;
            $updateData['penalty_value'] = 0.00;
        }

        // Find or create rule setting for this type and company scoping
        $companyId = $request->company_id ?: null;
        $existingRule = AttendancePenalty::where('rule_type', $request->rule_type)
            ->where('company_id', $companyId)
            ->first();
        $isCreate = ($existingRule === null);

        AttendancePenalty::updateOrCreate(
            [
                'rule_type' => $request->rule_type,
                'company_id' => $companyId,
            ],
            $updateData
        );

        $successMsg = $isCreate ? __('hrms.penalization.policy_created') : __('hrms.penalization.policy_updated');

        return redirect()->route('hrms.penalization-policy.index', [
            'policy_type' => $request->rule_type,
            'company_id' => $request->company_id ?? '',
        ])->with('success', $successMsg);
    }

    public function queryAttendanceRule(Request $request)
    {
        $companyId = $request->query('company_id');
        $buId = $request->query('business_unit_id');
        $branchId = $request->query('branch_id');

        $rule = \App\Domains\HRMS\Models\AttendanceRule::where('company_id', $companyId)
            ->where('business_unit_id', $buId ?: null)
            ->where('branch_id', $branchId ?: null)
            ->first();

        return response()->json($rule);
    }

    public function saveAttendanceRule(Request $request)
    {
        $validated = $request->validate([
            'company_id'             => 'required|integer|exists:companies,id',
            'business_unit_id'       => 'nullable|integer|exists:business_units,id',
            'branch_id'              => 'nullable|integer|exists:branches,id',
            'office_latitude'        => 'nullable|string',
            'office_longitude'       => 'nullable|string',
            'office_radius'          => 'required|integer|min:1',
            'office_tracking_minutes'=> 'nullable|integer|min:1|max:120',
            'wfh_tracking_meters'    => 'required|integer|min:1',
            'wfh_tracking_minutes'   => 'nullable|integer|min:1|max:120',
            'site_tracking_meters'   => 'required|integer|min:1',
            'site_tracking_minutes'  => 'nullable|integer|min:1|max:120',
            'status'                 => 'required',
        ]);

        $validated['office_biometric']       = $request->has('office_biometric');
        $validated['office_web']             = $request->has('office_web');
        $validated['office_geofence']        = $request->has('office_geofence');
        $validated['office_tracking']        = $request->has('office_tracking');
        $validated['office_tracking_minutes']= $request->input('office_tracking_minutes', 15);
        $validated['wfh_location']           = $request->has('wfh_location');
        $validated['wfh_selfie']             = $request->has('wfh_selfie');
        $validated['wfh_geofence']           = $request->has('wfh_geofence');
        $validated['wfh_tracking']           = $request->has('wfh_tracking');
        $validated['wfh_tracking_minutes']   = $request->input('wfh_tracking_minutes', 15);
        $validated['site_location']          = $request->has('site_location');
        $validated['site_selfie']            = $request->has('site_selfie');
        $validated['site_geofence']          = false;
        $validated['site_tracking']          = $request->has('site_tracking');
        $validated['site_tracking_minutes']  = $request->input('site_tracking_minutes', 15);

        $validated['status'] = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        // Normalize empty strings to null for integer foreign keys
        $validated['business_unit_id'] = $validated['business_unit_id'] ?: null;
        $validated['branch_id'] = $validated['branch_id'] ?: null;

        // Clean up any historical duplicate entries for this exact scope
        $duplicates = \App\Domains\HRMS\Models\AttendanceRule::where('company_id', $validated['company_id'])
            ->where('business_unit_id', $validated['business_unit_id'])
            ->where('branch_id', $validated['branch_id'])
            ->get();
        
        $isCreate = ($duplicates->count() === 0);

        if ($duplicates->count() > 1) {
            $keepId = $duplicates->last()->id;
            \App\Domains\HRMS\Models\AttendanceRule::where('company_id', $validated['company_id'])
                ->where('business_unit_id', $validated['business_unit_id'])
                ->where('branch_id', $validated['branch_id'])
                ->where('id', '!=', $keepId)
                ->delete();
        }

        \App\Domains\HRMS\Models\AttendanceRule::updateOrCreate(
            [
                'company_id' => $validated['company_id'],
                'business_unit_id' => $validated['business_unit_id'],
                'branch_id' => $validated['branch_id'],
            ],
            $validated
        );

        $successMsg = $isCreate ? 'Attendance rules created successfully.' : 'Attendance rules updated successfully.';

        return redirect()->route('hrms.penalization-policy.index', [
            'policy_type' => 'attendance_rules',
            'company_id' => $validated['company_id'],
            'business_unit_id' => $validated['business_unit_id'] ?? '',
            'branch_id' => $validated['branch_id'] ?? '',
        ])->with('success', $successMsg);
    }
}
