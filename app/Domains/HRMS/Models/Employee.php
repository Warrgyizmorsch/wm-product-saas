<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [

        'company_id',
        'business_unit_id',
        'branch_id',
        'department_id',
        'designation_id',
        'pay_group_id',
        'salary_structure_id',
        'leave_plan_id',
        'attendance_penalty_id',
        'reporting_manager_id',
        'shift_id',

        'employee_id',
        'full_name',
        'nick_name',
        'blood_group',
        'employee_stage',
        'job_title',
        'role',
        'employment_type',
        'date_of_joining',
        'date_of_birth',
        'probation_end_date',
        'confirmation_date',
        'office',
        'gender',
        'marital_status',
        'diet_preference',
        'aadhaar_card_number',
        'pan_card_number',
        'photo',

        'present_address',
        'permanent_address',
        'city',
        'postal_code',
        'personal_mobile_number',
        'home_phone',
        'personal_email',
        'office_email',

        'experience',
        'source_of_hire',
        'skill_set',
        'current_salary',
        'qualification',
        'bank_name',
        'account_number',
        'ifsc_code',
        'emergency_contact_name',
        'emergency_contact_number',
        'emergency_contact_relation',

        'status'
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_birth' => 'date',
        'probation_end_date' => 'date',
        'confirmation_date' => 'date',
        'experience' => 'decimal:2',
        'current_salary' => 'decimal:2',
        'status' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($employee): void {
            if (empty($employee->employee_id)) {
                // Load company name to generate the prefix
                $company = $employee->company ?: \App\Domains\HRMS\Models\Company::find($employee->company_id);
                $prefix = 'EMP';

                if ($company && !empty($company->company_name)) {
                    // Extract words
                    $words = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z0-9\s]/', '', $company->company_name))) ?: [];
                    
                    if (count($words) >= 2) {
                        $prefix = '';
                        foreach (array_slice($words, 0, 3) as $word) {
                            $prefix .= substr($word, 0, 1);
                        }
                    } else if (isset($words[0]) && strlen($words[0]) > 0) {
                        $prefix = substr($words[0], 0, 3);
                    }
                    $prefix = strtoupper($prefix);
                }

                // Find the highest sequence number among existing employees (including soft deleted ones)
                $maxEmployee = self::withTrashed()
                    ->where('company_id', $employee->company_id)
                    ->where('employee_id', 'LIKE', $prefix . '-%')
                    ->orderByRaw('CAST(SUBSTRING(employee_id, LENGTH(?) + 2) AS UNSIGNED) DESC', [$prefix])
                    ->first();

                $nextSequence = 1;
                if ($maxEmployee) {
                    $parts = explode('-', $maxEmployee->employee_id);
                    $lastNum = (int) end($parts);
                    if ($lastNum > 0) {
                        $nextSequence = $lastNum + 1;
                    }
                }

                // Format: PREFIX-XXXX (e.g. ACM-0001)
                $employee->employee_id = $prefix . '-' . str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionShift::class, 'shift_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function leavePlan(): BelongsTo
    {
        return $this->belongsTo(LeavePlan::class, 'leave_plan_id');
    }

    public function attendancePenalty(): BelongsTo
    {
        return $this->belongsTo(AttendancePenalty::class, 'attendance_penalty_id');
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function employmentHistories(): HasMany
    {
        return $this->hasMany(EmployeeEmploymentHistory::class)->orderBy('start_date', 'desc');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'assigned_employee_id');
    }

    public function assetRequests(): HasMany
    {
        return $this->hasMany(AssetRequest::class, 'employee_id')->orderBy('request_date', 'desc');
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_employee_id');
    }

    public function headedBusinessUnits(): HasMany
    {
        return $this->hasMany(BusinessUnit::class, 'head_employee_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }

    public function getFirstNameAttribute(): string
    {
        return (string) str($this->full_name)->before(' ');
    }

    public function getLastNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->full_name)) ?: [];

        if (count($parts) <= 1) {
            return '';
        }

        array_shift($parts);

        return implode(' ', $parts);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Migrate the employee to a new leave plan and reconcile balances.
     */
    public function migrateToLeavePlan($oldPlanId, $newPlanId, $action = 'transfer', $unusedAction = 'carry')
    {
        if (empty($newPlanId) || (int) $oldPlanId === (int) $newPlanId) {
            return;
        }

        $oldPlan = \App\Domains\HRMS\Models\LeavePlan::with('types')->find($oldPlanId);
        $newPlan = \App\Domains\HRMS\Models\LeavePlan::with('types')->find($newPlanId);

        if (!$newPlan) {
            return;
        }

        // Fetch current balances for the employee
        $oldBalances = \App\Domains\HRMS\Models\LeaveBalance::where('employee_id', $this->id)->get();
        $oldBalancesMap = $oldBalances->keyBy('leave_type_id');

        // Map old types by code
        $oldTypesMap = $oldPlan ? $oldPlan->types->keyBy('code') : collect();
        
        // Calculate passed & remaining months in current cycle (based on new plan's effective date)
        $startOfYear = \Carbon\Carbon::now()->startOfYear();
        if ($newPlan->effective_from) {
            $startOfYear = \Carbon\Carbon::parse($newPlan->effective_from);
            $now = \Carbon\Carbon::now();
            $diffInYears = $startOfYear->diffInYears($now);
            $startOfYear->addYears($diffInYears);
            if ($startOfYear->isAfter($now)) {
                $startOfYear->subYear();
            }
        }
        
        $monthsPassed = min(12, max(0, $startOfYear->diffInMonths(\Carbon\Carbon::now())));
        $monthsRemaining = 12 - $monthsPassed;

        $processedNewTypeIds = [];

        foreach ($newPlan->types as $newType) {
            // Find matching old type by code
            $oldType = $oldTypesMap->get($newType->code);
            $oldBalance = $oldType ? $oldBalancesMap->get($oldType->id) : null;

            $newAllocated = floatval($newType->quota);
            $newUsed = 0.0;

            if ($oldBalance) {
                $newUsed = floatval($oldBalance->used);
            }

            // Determine if we should calculate based on accruals
            if ($oldBalance && $oldType) {
                $oldRules = $oldType->rules ?? [];
                $accrualRate = $oldRules['accrual']['rate'] ?? 'immediate';
                $accrualFrequency = $oldRules['accrual']['frequency'] ?? 'monthly';

                // Calculate old accrued quota up to now
                $oldAccruedQuota = floatval($oldType->quota);
                if ($accrualRate === 'periodic') {
                    if ($accrualFrequency === 'monthly') {
                        $oldAccruedQuota = ($oldType->quota / 12.0) * $monthsPassed;
                    } elseif ($accrualFrequency === 'quarterly') {
                        $quartersPassed = floor($monthsPassed / 3.0);
                        $oldAccruedQuota = ($oldType->quota / 4.0) * $quartersPassed;
                    } elseif ($accrualFrequency === 'yearly') {
                        $oldAccruedQuota = ($monthsPassed >= 12) ? floatval($oldType->quota) : 0.0;
                    } else {
                        $oldAccruedQuota = ($oldType->quota / 12.0) * $monthsPassed;
                    }
                } elseif ($accrualRate === 'attendance') {
                    $oldAccruedQuota = ($oldType->quota / 12.0) * $monthsPassed;
                }

                // Unused accrued leaves
                $netAccruedUnused = $oldAccruedQuota - $newUsed;

                // Carry forward rules (action & limit)
                $oldAction = $oldRules['yearend']['action'] ?? 'lapse';
                $maxCarry = floatval($oldRules['yearend']['max_carry'] ?? 999.0);

                if ($netAccruedUnused > 0) {
                    if ($unusedAction === 'carry' && $oldAction === 'carry_forward') {
                        $oldUnused = min($netAccruedUnused, $maxCarry);
                    } else {
                        $oldUnused = 0.0; // Lapsed
                    }
                } else {
                    // Excess leaves taken are always carried forward as a negative deduction
                    $oldUnused = $netAccruedUnused;
                }
            } else {
                $oldUnused = 0.0;
            }

            if ($action === 'prorate') {
                // New plan prorated quota for remaining months
                $newProratedQuota = ($newType->quota / 12.0) * $monthsRemaining;
                $newAllocated = $newProratedQuota + $oldUnused + $newUsed;
            } else {
                // Full quota
                $newAllocated = floatval($newType->quota) + $oldUnused + $newUsed;
            }

            // Create or update LeaveBalance
            $newBalance = \App\Domains\HRMS\Models\LeaveBalance::updateOrCreate([
                'tenant_id' => $this->tenant_id,
                'company_id' => $this->company_id,
                'employee_id' => $this->id,
                'leave_type_id' => $newType->id,
            ], [
                'allocated' => round($newAllocated, 2),
                'used' => round($newUsed, 2),
            ]);

            $processedNewTypeIds[] = $newType->id;
        }

        // Clean up or remove old balance records that are not in the new plan
        foreach ($oldBalances as $oldBal) {
            if (!in_array($oldBal->leave_type_id, $processedNewTypeIds)) {
                $oldBal->delete();
            }
        }
    }
}
