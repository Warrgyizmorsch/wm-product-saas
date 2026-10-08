<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Repositories\PenalizationPolicyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PenalizationPolicyController extends Controller
{
    public function __construct(
        private readonly PenalizationPolicyRepositoryInterface $penalizationPolicyRepository
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Domains\HRMS\Models\AttendancePenaltyRule::class);

        $data = $this->penalizationPolicyRepository->getIndexData($request->all());

        return view('modules.hrms.penalization-policy.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Domains\HRMS\Models\AttendancePenaltyRule::class);

        $rules = [
            'rule_type' => 'required|in:late_arrival,under_hours,missing_logs,overtime_rules',
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
            $rules['threshold_count']    = 'required|integer|min:0';
            $rules['penalty_tiers']      = 'required|array';
            $rules['penalty_tiers.*.hours_threshold'] = 'required|numeric|min:0|max:24';
            $rules['penalty_tiers.*.penalty_action']  = 'required|in:no_deduction,salary_deduction,working_hour_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value']   = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id']   = 'nullable';
        } elseif ($request->rule_type === 'overtime_rules') {
            $rules['auto_overtime_threshold_hours'] = 'nullable|numeric|min:0';
            $rules['min_overtime_request_hours']    = 'nullable|numeric|min:0';
            $rules['overtime_max_monthly_hours']    = 'nullable|numeric|min:0';
            $rules['overtime_weekend_multiplier']   = 'nullable|numeric|min:1';
            $rules['overtime_holiday_multiplier']   = 'nullable|numeric|min:1';
            $rules['overtime_tiers']                = 'nullable|array';
            $rules['overtime_tiers.*.min_hours']    = 'nullable|numeric|min:0';
            $rules['overtime_tiers.*.max_hours']    = 'nullable|numeric|min:0';
            $rules['overtime_tiers.*.multiplier']   = 'nullable|numeric|min:1';
            $rules['overtime_tiers.*.rate_multiplier'] = 'nullable|numeric|min:1';
        }

        $validated = $request->validate($rules);

        if ($request->rule_type === 'overtime_rules') {
            $validated['penalty_tiers'] = [
                'auto_overtime_threshold_hours' => $validated['auto_overtime_threshold_hours'] ?? null,
                'min_overtime_request_hours'    => $validated['min_overtime_request_hours'] ?? null,
                'overtime_max_monthly_hours'    => $validated['overtime_max_monthly_hours'] ?? null,
                'overtime_weekend_multiplier'   => $validated['overtime_weekend_multiplier'] ?? null,
                'overtime_holiday_multiplier'   => $validated['overtime_holiday_multiplier'] ?? null,
                'overtime_tiers'                => array_values($validated['overtime_tiers'] ?? []),
            ];
        } elseif (isset($validated['penalty_tiers']) && is_array($validated['penalty_tiers'])) {
            $validated['penalty_tiers'] = array_values($validated['penalty_tiers']);
        }

        $this->penalizationPolicyRepository->storeRule($validated);

        return redirect()->back()->with('success', 'Policy configuration saved successfully.');
    }

    public function queryAttendanceRule(Request $request): JsonResponse
    {
        $rule = $this->penalizationPolicyRepository->queryAttendanceRule($request->all());

        return response()->json($rule);
    }

    public function saveAttendanceRule(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', \App\Domains\HRMS\Models\AttendancePenaltyRule::class);

        $validated = $request->validate([
            'company_id'             => 'required|integer|exists:companies,id',
            'business_unit_id'       => 'nullable|integer',
            'branch_id'              => 'nullable|integer',
            'office_latitude'        => 'nullable|string',
            'office_longitude'       => 'nullable|string',
            'office_radius'          => 'nullable|integer|min:1',
            'office_tracking_minutes'=> 'nullable|integer|min:1|max:120',
            'wfh_tracking_meters'    => 'nullable|integer|min:1',
            'wfh_tracking_minutes'   => 'nullable|integer|min:1|max:120',
            'site_tracking_meters'   => 'nullable|integer|min:1',
            'site_tracking_minutes'  => 'nullable|integer|min:1|max:120',
            'status'                 => 'required',
        ]);

        $validated['office_biometric']        = $request->boolean('office_biometric');
        $validated['office_web']              = $request->boolean('office_web');
        $validated['office_geofence']         = $request->boolean('office_geofence');
        $validated['office_radius']           = $request->filled('office_radius') ? (int)$request->office_radius : 100;
        $validated['office_tracking']         = $request->boolean('office_tracking');
        $validated['office_tracking_minutes'] = $request->filled('office_tracking_minutes') ? (int) $request->office_tracking_minutes : 15;
        $validated['wfh_location']            = $request->boolean('wfh_location');
        $validated['wfh_selfie']              = $request->boolean('wfh_selfie');
        $validated['wfh_geofence']            = $request->boolean('wfh_geofence');
        $validated['wfh_tracking']            = $request->boolean('wfh_tracking');
        $validated['wfh_tracking_meters']     = $request->filled('wfh_tracking_meters') ? (int) $request->wfh_tracking_meters : 50;
        $validated['wfh_tracking_minutes']    = $request->filled('wfh_tracking_minutes') ? (int) $request->wfh_tracking_minutes : 15;
        $validated['site_location']           = $request->boolean('site_location');
        $validated['site_selfie']             = $request->boolean('site_selfie');
        $validated['site_geofence']           = $request->boolean('site_geofence');
        $validated['site_tracking']           = $request->boolean('site_tracking');
        $validated['site_tracking_meters']    = $request->filled('site_tracking_meters') ? (int) $request->site_tracking_meters : 50;
        $validated['site_tracking_minutes']   = $request->filled('site_tracking_minutes') ? (int) $request->site_tracking_minutes : 15;
        $validated['status']                  = filter_var($request->status, FILTER_VALIDATE_BOOLEAN);

        $validated['business_unit_id'] = !empty($validated['business_unit_id']) ? $validated['business_unit_id'] : null;
        $validated['branch_id'] = !empty($validated['branch_id']) ? $validated['branch_id'] : null;

        $rule = $this->penalizationPolicyRepository->saveAttendanceRule($validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Attendance rules saved successfully.',
                'rule' => $rule,
            ]);
        }

        return redirect()->route('hrms.penalization-policy.index', ['policy_type' => 'attendance_rules'])
            ->with('success', 'Attendance rules saved successfully.');
    }
}
