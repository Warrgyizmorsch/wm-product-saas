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
            $rules['penalty_tiers'] = 'required|array';
            $rules['penalty_tiers.*.min_deficit_minutes'] = 'required|integer|min:0';
            $rules['penalty_tiers.*.max_deficit_minutes'] = 'nullable|integer|min:0';
            $rules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,working_hour_deduction,both_deductions';
            $rules['penalty_tiers.*.penalty_value'] = 'required|numeric|min:0';
            $rules['penalty_tiers.*.leave_type_id'] = 'nullable';
        } elseif ($request->rule_type === 'overtime_rules') {
            $rules['auto_overtime_threshold_hours'] = 'nullable|numeric|min:0';
            $rules['min_overtime_request_hours']    = 'nullable|numeric|min:0';
            $rules['overtime_max_monthly_hours']    = 'nullable|numeric|min:0';
            $rules['overtime_weekend_multiplier']   = 'nullable|numeric|min:1';
            $rules['overtime_holiday_multiplier']   = 'nullable|numeric|min:1';
            $rules['overtime_tiers']                = 'nullable|array';
            $rules['overtime_tiers.*.min_hours']    = 'required|numeric|min:0';
            $rules['overtime_tiers.*.rate_multiplier'] = 'required|numeric|min:1';
        }

        $validated = $request->validate($rules);

        if ($request->rule_type === 'overtime_rules') {
            $validated['penalty_tiers'] = [
                'auto_overtime_threshold_hours' => $validated['auto_overtime_threshold_hours'] ?? null,
                'min_overtime_request_hours'    => $validated['min_overtime_request_hours'] ?? null,
                'overtime_max_monthly_hours'    => $validated['overtime_max_monthly_hours'] ?? null,
                'overtime_weekend_multiplier'   => $validated['overtime_weekend_multiplier'] ?? null,
                'overtime_holiday_multiplier'   => $validated['overtime_holiday_multiplier'] ?? null,
                'overtime_tiers'                => $validated['overtime_tiers'] ?? [],
            ];
        }

        $this->penalizationPolicyRepository->storeRule($validated);

        return redirect()->back()->with('success', 'Policy configuration saved successfully.');
    }

    public function queryAttendanceRule(Request $request): JsonResponse
    {
        $rule = $this->penalizationPolicyRepository->queryAttendanceRule($request->all());

        return response()->json([
            'success' => true,
            'rule' => $rule,
        ]);
    }

    public function saveAttendanceRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id'                => 'nullable|integer',
            'business_unit_id'          => 'nullable|integer',
            'branch_id'                 => 'nullable|integer',
            'half_day_min_hours'        => 'nullable|numeric|min:0',
            'full_day_min_hours'        => 'nullable|numeric|min:0',
            'max_allowed_late_minutes'  => 'nullable|integer|min:0',
            'max_allowed_early_exit_minutes' => 'nullable|integer|min:0',
            'require_facial_recognition'=> 'nullable|boolean',
            'require_gps_validation'    => 'nullable|boolean',
            'enforce_ip_restriction'    => 'nullable|boolean',
            'allowed_ip_addresses'      => 'nullable|string',
        ]);

        $rule = $this->penalizationPolicyRepository->saveAttendanceRule($validated);

        return response()->json([
            'success' => true,
            'message' => 'Attendance rule saved successfully.',
            'rule' => $rule,
        ]);
    }
}
