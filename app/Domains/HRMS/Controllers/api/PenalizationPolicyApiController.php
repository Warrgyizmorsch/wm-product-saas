<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeaveType;
use App\Domains\HRMS\Models\AttendancePenalty;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PenalizationPolicyApiController extends Controller
{
    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Null-safe authorization check supporting Web Sessions & HTTP Basic Auth.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
            }
        }

        if (!auth()->user()->hasHrPermission('hr.settings.manage')) {
            return $this->sendError('Unauthorized access. Your user role does not have hr.settings.manage permission.', 403);
        }

        return null;
    }

    /**
     * GET /api/hrms/penalization-policy/summary
     * Get overview of all penalization policy rules & dropdown choices.
     */
    public function summary(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $companies  = Company::all();
        $leaveTypes = LeaveType::where('status', true)
            ->get()
            ->unique('name')
            ->values();

        $rules = AttendancePenalty::all()->keyBy('rule_type');

        return $this->sendSuccess([
            'rules'       => $rules,
            'companies'   => $companies,
            'leave_types' => $leaveTypes,
        ], 'Penalization policies loaded successfully');
    }

    /**
     * GET /api/hrms/penalization-policy/rules/{ruleType}
     * Get specific penalty policy rule (late_arrival, missing_logs, under_hours).
     */
    public function showRule(string $ruleType): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        if (!in_array($ruleType, ['late_arrival', 'missing_logs', 'under_hours'])) {
            return $this->sendError('Invalid policy rule type specified.', 422);
        }

        $rule = AttendancePenalty::where('rule_type', $ruleType)->first();

        if (!$rule) {
            return $this->sendError("No penalization policy configured for rule type: {$ruleType}", 404);
        }

        return $this->sendSuccess($rule, "Penalization policy rule for {$ruleType} retrieved");
    }

    /**
     * POST /api/hrms/penalization-policy/save
     * Create or update penalization policy configuration rules & tiers.
     */
    public function saveRule(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validationRules = [
            'rule_type'  => 'required|in:late_arrival,under_hours,missing_logs',
            'company_id' => 'nullable|integer',
            'status'     => 'required',
        ];

        if ($request->rule_type === 'late_arrival') {
            $validationRules['grace_period_minutes']         = 'required|integer|min:0';
            $validationRules['threshold_count']              = 'required|integer|min:0';
            $validationRules['penalty_tiers']                = 'required|array';
            $validationRules['penalty_tiers.*.min_occurrence'] = 'required|integer|min:1';
            $validationRules['penalty_tiers.*.max_occurrence'] = 'nullable|integer|min:1';
            $validationRules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,leave_deduction,both_deductions';
            $validationRules['penalty_tiers.*.penalty_value']  = 'required|numeric|min:0';
            $validationRules['penalty_tiers.*.leave_type_id'] = 'nullable|integer|exists:leave_types,id';
        } elseif ($request->rule_type === 'missing_logs') {
            $validationRules['threshold_count']              = 'required|integer|min:0';
            $validationRules['penalty_tiers']                = 'required|array';
            $validationRules['penalty_tiers.*.min_occurrence'] = 'required|integer|min:1';
            $validationRules['penalty_tiers.*.max_occurrence'] = 'nullable|integer|min:1';
            $validationRules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,leave_deduction,both_deductions';
            $validationRules['penalty_tiers.*.penalty_value']  = 'required|numeric|min:0';
            $validationRules['penalty_tiers.*.leave_type_id'] = 'nullable|integer|exists:leave_types,id';
        } elseif ($request->rule_type === 'under_hours') {
            $validationRules['grace_period_hours']           = 'required|numeric|min:0';
            $validationRules['threshold_count']              = 'required|integer|min:0';
            $validationRules['penalty_tiers']                = 'required|array';
            $validationRules['penalty_tiers.*.hours_threshold'] = 'required|numeric|min:0|max:24';
            $validationRules['penalty_tiers.*.penalty_action'] = 'required|in:no_deduction,salary_deduction,leave_deduction,both_deductions';
            $validationRules['penalty_tiers.*.penalty_value']  = 'required|numeric|min:0';
            $validationRules['penalty_tiers.*.leave_type_id'] = 'nullable|integer|exists:leave_types,id';
        }

        $validated = $request->validate($validationRules);

        $status = ($request->status === '1' || $request->status === 'active' || $request->status === true);

        $updateData = [
            'status' => $status,
        ];

        if ($request->rule_type === 'late_arrival') {
            $updateData['grace_period_minutes'] = $request->grace_period_minutes ?? 0;
            $tiers = [];
            if ($request->has('penalty_tiers') && is_array($request->penalty_tiers)) {
                foreach ($request->penalty_tiers as $tier) {
                    $tiers[] = [
                        'min_occurrence' => (int) $tier['min_occurrence'],
                        'max_occurrence' => isset($tier['max_occurrence']) && $tier['max_occurrence'] !== '' && $tier['max_occurrence'] !== null ? (int) $tier['max_occurrence'] : null,
                        'penalty_action' => $tier['penalty_action'],
                        'penalty_value'  => (float) $tier['penalty_value'],
                        'leave_type_id'  => !empty($tier['leave_type_id']) ? (int) $tier['leave_type_id'] : null,
                    ];
                }
            }
            $updateData['penalty_tiers']        = $tiers;
            $updateData['threshold_count']      = (int) ($request->threshold_count ?? 0);
            $updateData['penalty_action']       = 'salary_deduction';
            $updateData['leave_type_id']        = null;
            $updateData['penalty_value']       = 0.00;
        } elseif ($request->rule_type === 'missing_logs') {
            $updateData['grace_period_minutes'] = 0;
            $updateData['threshold_count']      = (int) $request->threshold_count;
            $tiers = [];
            if ($request->has('penalty_tiers') && is_array($request->penalty_tiers)) {
                foreach ($request->penalty_tiers as $tier) {
                    $tiers[] = [
                        'min_occurrence' => (int) $tier['min_occurrence'],
                        'max_occurrence' => isset($tier['max_occurrence']) && $tier['max_occurrence'] !== '' && $tier['max_occurrence'] !== null ? (int) $tier['max_occurrence'] : null,
                        'penalty_action' => $tier['penalty_action'],
                        'penalty_value'  => (float) $tier['penalty_value'],
                        'leave_type_id'  => !empty($tier['leave_type_id']) ? (int) $tier['leave_type_id'] : null,
                    ];
                }
            }
            $updateData['penalty_tiers']        = $tiers;
            $updateData['penalty_action']       = 'salary_deduction';
            $updateData['leave_type_id']        = null;
            $updateData['penalty_value']       = 0.00;
        } elseif ($request->rule_type === 'under_hours') {
            $updateData['grace_period_minutes'] = (int) (floatval($request->grace_period_hours) * 60);
            $updateData['threshold_count']      = (int) $request->threshold_count;
            $tiers = [];
            if ($request->has('penalty_tiers') && is_array($request->penalty_tiers)) {
                foreach ($request->penalty_tiers as $tier) {
                    $tiers[] = [
                        'hours_threshold' => (float) $tier['hours_threshold'],
                        'penalty_action'  => $tier['penalty_action'],
                        'penalty_value'   => (float) $tier['penalty_value'],
                        'leave_type_id'   => !empty($tier['leave_type_id']) ? (int) $tier['leave_type_id'] : null,
                    ];
                }
            }
            $updateData['penalty_tiers']        = $tiers;
            $updateData['penalty_action']       = 'salary_deduction';
            $updateData['leave_type_id']        = null;
            $updateData['penalty_value']       = 0.00;
        }

        $policy = AttendancePenalty::updateOrCreate(
            [
                'rule_type'  => $request->rule_type,
                'company_id' => $request->company_id,
            ],
            $updateData
        );

        return $this->sendSuccess($policy, "Penalization policy for {$request->rule_type} saved successfully");
    }

    /**
     * DELETE /api/hrms/penalization-policy/rules/{attendancePenalty}
     * Delete a penalization policy rule configuration.
     */
    public function destroyRule(AttendancePenalty $attendancePenalty): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $attendancePenalty->delete();

        return $this->sendSuccess(null, 'Penalization policy rule deleted successfully');
    }
}
