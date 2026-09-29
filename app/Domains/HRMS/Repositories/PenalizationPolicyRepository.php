<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\AttendancePenalty;
use App\Domains\HRMS\Models\AttendanceRule;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\LeaveType;
use App\Models\Tenant;

class PenalizationPolicyRepository implements PenalizationPolicyRepositoryInterface
{
    public function getIndexData(array $inputs): array
    {
        $companies = Company::all();
        $leaveTypes = LeaveType::where('status', true)
            ->get()
            ->unique('name')
            ->values();
        
        $selectedType = $inputs['policy_type'] ?? 'late_arrival';
        $companyId = !empty($inputs['company_id']) ? $inputs['company_id'] : null;
        
        $rules = AttendancePenalty::where('company_id', $companyId)->get()->keyBy('rule_type');

        $businessUnits = BusinessUnit::all();
        $branches = Branch::all();
        $attendanceRules = AttendanceRule::with(['company', 'businessUnit', 'branch'])->get();

        $overtimeRule = $rules->get('overtime_rules');
        $tenantSettings = [
            'auto_overtime_threshold_hours' => '',
            'min_overtime_request_hours'    => '',
            'overtime_max_monthly_hours'    => '',
            'overtime_weekend_multiplier'   => '',
            'overtime_holiday_multiplier'   => '',
            'overtime_tiers'                => [],
        ];

        if ($overtimeRule) {
            $settings = is_array($overtimeRule->penalty_tiers) ? $overtimeRule->penalty_tiers : [];
            $tenantSettings['auto_overtime_threshold_hours'] = $settings['auto_overtime_threshold_hours'] ?? '';
            $tenantSettings['min_overtime_request_hours']    = $settings['min_overtime_request_hours'] ?? '';
            $tenantSettings['overtime_max_monthly_hours']    = $settings['overtime_max_monthly_hours'] ?? '';
            $tenantSettings['overtime_weekend_multiplier']   = $settings['overtime_weekend_multiplier'] ?? '';
            $tenantSettings['overtime_holiday_multiplier']   = $settings['overtime_holiday_multiplier'] ?? '';
            $tenantSettings['overtime_tiers']                = $settings['overtime_tiers'] ?? [];
        } else {
            $user = auth()->user();
            if ($user && $user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
                if ($tenant && is_array($tenant->settings)) {
                    $tenantSettings['auto_overtime_threshold_hours'] = $tenant->settings['auto_overtime_threshold_hours'] ?? '';
                    $tenantSettings['min_overtime_request_hours']    = $tenant->settings['min_overtime_request_hours'] ?? '';
                    $tenantSettings['overtime_max_monthly_hours']    = $tenant->settings['overtime_max_monthly_hours'] ?? '';
                    $tenantSettings['overtime_weekend_multiplier']   = $tenant->settings['overtime_weekend_multiplier'] ?? '';
                    $tenantSettings['overtime_holiday_multiplier']   = $tenant->settings['overtime_holiday_multiplier'] ?? '';
                    $tenantSettings['overtime_tiers']                = $tenant->settings['overtime_tiers'] ?? [];
                }
            }
        }

        return compact(
            'companies', 'leaveTypes', 'rules', 'selectedType',
            'businessUnits', 'branches', 'attendanceRules', 'tenantSettings'
        );
    }

    public function storeRule(array $validated): AttendancePenalty
    {
        $statusBool = filter_var($validated['status'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $payload = [
            'status' => $statusBool,
            'grace_period_minutes' => $validated['grace_period_minutes'] ?? null,
            'threshold_count' => $validated['threshold_count'] ?? null,
            'penalty_tiers' => $validated['penalty_tiers'] ?? null,
        ];

        return AttendancePenalty::updateOrCreate(
            [
                'rule_type' => $validated['rule_type'],
                'company_id' => $validated['company_id'] ?? null,
            ],
            $payload
        );
    }

    public function queryAttendanceRule(array $inputs): ?AttendanceRule
    {
        $companyId = !empty($inputs['company_id']) ? $inputs['company_id'] : null;
        $businessUnitId = !empty($inputs['business_unit_id']) ? $inputs['business_unit_id'] : null;
        $branchId = !empty($inputs['branch_id']) ? $inputs['branch_id'] : null;

        return AttendanceRule::where('company_id', $companyId)
            ->where('business_unit_id', $businessUnitId)
            ->where('branch_id', $branchId)
            ->first();
    }

    public function saveAttendanceRule(array $validated): AttendanceRule
    {
        return AttendanceRule::updateOrCreate(
            [
                'company_id' => $validated['company_id'] ?? null,
                'business_unit_id' => $validated['business_unit_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
            ],
            $validated
        );
    }
}
