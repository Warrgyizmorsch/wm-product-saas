<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Domains\HRMS\Traits\HasHrmsScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends BaseModel
{
    use SoftDeletes, HasHrmsScope;

    protected $fillable = [

        'tenant_id',
        'user_id',
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
        'wfh_latitude',
        'wfh_longitude',
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

        'status',
        'weekly_pattern',
        'resume_path'
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_birth' => 'date',
        'probation_end_date' => 'date',
        'confirmation_date' => 'date',
        'experience' => 'decimal:2',
        'current_salary' => 'decimal:2',
        'status' => 'boolean',
        'weekly_pattern' => 'array',
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

        static::saving(function (self $employee) {
            if ($employee->user_id) {
                $user = $employee->user ?: \App\Models\User::find($employee->user_id);
                if ($user && !empty($user->email)) {
                    $employee->office_email = $user->email;
                }
            }
        });

        static::saved(function (self $employee) {
            if ($employee->user_id) {
                $user = $employee->user ?: \App\Models\User::find($employee->user_id);
                if ($user && method_exists($user, 'syncWithEmployee')) {
                    $user->syncWithEmployee($employee);
                }
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
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

    public function allocations(): HasMany
    {
        return $this->hasMany(AssetAllocation::class, 'employee_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'employee_id');
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

    public function probationEvaluations(): HasMany
    {
        return $this->hasMany(EmployeeProbationEvaluation::class, 'employee_id')->orderBy('evaluation_date', 'desc');
    }

    public function profileUpdateRequests(): HasMany
    {
        return $this->hasMany(EmployeeProfileUpdateRequest::class, 'employee_id')->latest('id');
    }

    public function pendingProfileUpdateRequest(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeProfileUpdateRequest::class, 'employee_id')->where('status', 'pending')->latest('id');
    }

    public function exits(): HasMany
    {
        return $this->hasMany(EmployeeExit::class, 'employee_id')->orderBy('created_at', 'desc');
    }

    public function activeExit()
    {
        return $this->hasOne(EmployeeExit::class, 'employee_id')->whereNotIn('status', ['rejected', 'cancelled'])->latestOfMany();
    }

    public function fnfSettlements(): HasMany
    {
        return $this->hasMany(EmployeeFnfSettlement::class, 'employee_id')->orderBy('calculation_date', 'desc');
    }

    public function exitDocuments(): HasMany
    {
        return $this->hasMany(EmployeeExitDocument::class, 'employee_id')->orderBy('issue_date', 'desc');
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

    public function getJobTitleAttribute($value): ?string
    {
        return $value ?: ($this->designation?->name ?? null);
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

        $oldCycleStart = null;
        if ($oldPlan) {
            if ($oldPlan->last_renewed_at) {
                $oldCycleStart = \Carbon\Carbon::parse($oldPlan->last_renewed_at);
            } elseif ($oldPlan->effective_from) {
                $startDate = \Carbon\Carbon::parse($oldPlan->effective_from);
                $now = \Carbon\Carbon::now();
                $diffInYears = $startDate->diffInYears($now);
                $oldCycleStart = $startDate->copy()->addYears($diffInYears);
                if ($oldCycleStart->isAfter($now)) {
                    $oldCycleStart->subYear();
                }
            }
        }

        $processedNewTypeIds = [];

        foreach ($newPlan->types as $newType) {
            // Find matching old type by case-insensitive code or name
            $oldType = null;
            if ($oldPlan && $oldPlan->types) {
                $oldType = $oldPlan->types->first(function ($t) use ($newType) {
                    return strcasecmp(trim((string)$t->code), trim((string)$newType->code)) === 0;
                });
                if (!$oldType) {
                    $oldType = $oldPlan->types->first(function ($t) use ($newType) {
                        return strcasecmp(trim((string)$t->name), trim((string)$newType->name)) === 0;
                    });
                }
            }

            $oldBalance = $oldType ? $oldBalancesMap->get($oldType->id) : null;

            $newAllocated = floatval($newType->quota);
            $newUsed = 0.0;
            $newEncashed = 0.0;

            if ($oldBalance) {
                $newUsed = floatval($oldBalance->used ?? 0);
                $newEncashed = floatval($oldBalance->encashed ?? 0);
            }

            // Re-link employee's existing leave requests from the old leave type to the new leave type
            // ONLY if they belong to the current active cycle of the old plan (i.e. on or after $oldCycleStart)
            if ($oldType) {
                $reqQuery = \App\Domains\HRMS\Models\LeaveRequest::where('employee_id', $this->id)
                    ->where('leave_type_id', $oldType->id);
                if ($oldCycleStart) {
                    $reqQuery->where('start_date', '>=', $oldCycleStart);
                }
                $reqQuery->update(['leave_type_id' => $newType->id]);

                // Also re-link LeaveEncashments for the same cycle
                $encQuery = \App\Domains\HRMS\Models\LeaveEncashment::where('employee_id', $this->id)
                    ->where('leave_type_id', $oldType->id);
                if ($oldCycleStart) {
                    $encQuery->where('created_at', '>=', $oldCycleStart);
                }
                $encQuery->update(['leave_type_id' => $newType->id]);
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
                    } elseif ($accrualFrequency === 'half_yearly') {
                        $halfYearsPassed = floor($monthsPassed / 6.0);
                        $oldAccruedQuota = ($oldType->quota / 2.0) * $halfYearsPassed;
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
                    } elseif ($unusedAction === 'encash') {
                        $encashableDays = round($netAccruedUnused * 2) / 2;
                        if ($encashableDays > 0.0) {
                            \App\Domains\HRMS\Models\LeaveEncashment::create([
                                'tenant_id' => $this->tenant_id,
                                'company_id' => $this->company_id,
                                'employee_id' => $this->id,
                                'leave_type_id' => $newType->id,
                                'requested_days' => $encashableDays,
                                'status' => 'approved',
                                'reason' => 'Plan transition automatic encashment (Migrated to ' . $newPlan->name . ')',
                                'approved_by' => auth()->id() ?? $this->user_id,
                                'approved_at' => now(),
                            ]);
                            $newEncashed += $encashableDays;
                        }
                        $oldUnused = 0.0;
                    } else {
                        $oldUnused = 0.0; // Lapsed
                    }
                } else {
                    // Excess leaves taken are carried forward as a negative deduction
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

            // Round the final allocated balance to the nearest 0.5 (half day or full day)
            $newAllocated = round($newAllocated * 2) / 2;

            // Create or update LeaveBalance
            $newBalance = \App\Domains\HRMS\Models\LeaveBalance::updateOrCreate([
                'tenant_id' => $this->tenant_id,
                'company_id' => $this->company_id,
                'employee_id' => $this->id,
                'leave_type_id' => $newType->id,
            ], [
                'allocated' => round($newAllocated, 2),
                'used' => round($newUsed, 2),
                'encashed' => round($newEncashed, 2),
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

    public function rosters(): HasMany
    {
        return $this->hasMany(\App\Domains\HRMS\Models\ShiftRoster::class, 'employee_id');
    }

    public function defaultShift(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionShift::class, 'shift_id');
    }

    /**
     * Resolve the active shift for a given date.
     * Precedence:
     * 1. Date-specific roster override (from shift_rosters table)
     * 2. Weekly pattern default (from employee's weekly_pattern JSON field)
     * 3. General default shift (from employee's default shift_id)
     *
     * @param string|\Carbon\Carbon|null $date
     * @return \App\Domains\Production\Models\ProductionShift|null
     */
    public function resolveShiftForDate($date)
    {
        if (empty($date)) {
            return $this->defaultShift ?? $this->shift;
        }

        try {
            $carbonDate = $date instanceof \Carbon\Carbon ? $date->copy() : \Carbon\Carbon::parse($date);
        } catch (\Throwable $e) {
            return $this->defaultShift ?? $this->shift;
        }

        $dateStr = $carbonDate->format('Y-m-d');
        $dayOfWeek = (int) $carbonDate->dayOfWeek;

        // 1. Check for specific date override in shift_rosters
        if ($this->relationLoaded('rosters')) {
            $roster = $this->rosters->first(function ($r) use ($dateStr) {
                $rDate = $r->date instanceof \Carbon\Carbon ? $r->date->format('Y-m-d') : (string) $r->date;
                return $rDate === $dateStr;
            });
        } else {
            $roster = \App\Domains\HRMS\Models\ShiftRoster::where([
                'employee_id' => $this->id,
                'date' => $dateStr
            ])->first();
        }

        if ($roster) {
            if (is_null($roster->shift_id) || $roster->shift_id === 0 || $roster->shift_id === 'off') {
                return null; // Day Off override
            }
            $shiftModel = ($roster->relationLoaded('shift') && $roster->shift) 
                ? $roster->shift 
                : \App\Domains\Production\Models\ProductionShift::find($roster->shift_id);
            if ($shiftModel) {
                return $shiftModel;
            }
        }

        // 2. Check for weekly pattern default (fallback to 'off' for Sunday if not explicitly set)
        $pattern = is_array($this->weekly_pattern) 
            ? $this->weekly_pattern 
            : (is_string($this->weekly_pattern) ? json_decode($this->weekly_pattern, true) : null);

        $weeklyShiftId = null;
        if (is_array($pattern)) {
            if (array_key_exists($dayOfWeek, $pattern)) {
                $weeklyShiftId = $pattern[$dayOfWeek];
            } elseif (array_key_exists((string) $dayOfWeek, $pattern)) {
                $weeklyShiftId = $pattern[(string) $dayOfWeek];
            }
        }

        // Sunday defaults to off if not explicitly set in weekly pattern
        if (is_null($weeklyShiftId) && $dayOfWeek === 0) {
            $weeklyShiftId = 'off';
        }

        if ($weeklyShiftId === 'off') {
            return null; // Weekly Off
        }

        if (!empty($weeklyShiftId) && $weeklyShiftId !== 'default') {
            $patternShift = \App\Domains\Production\Models\ProductionShift::find((int) $weeklyShiftId);
            if ($patternShift) {
                return $patternShift;
            }
        }

        // 3. Fall back to general default shift
        return $this->defaultShift ?? $this->shift;
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SalaryRevision::class, 'employee_id')->orderBy('effective_date', 'desc');
    }

    /**
     * Multi-tier resolution of Employee profile for a given User model.
     */
    public static function resolveForUser(?\App\Models\User $user): ?self
    {
        if (!$user) {
            return null;
        }

        // 1. Primary check: by user_id link
        $employee = static::where('user_id', $user->id)->first();
        if ($employee) {
            return $employee;
        }

        // 2. Secondary check: case-insensitive email match (office_email or personal_email)
        if (!empty($user->email)) {
            $employee = static::where(function ($q) use ($user) {
                $q->whereRaw('LOWER(office_email) = ?', [strtolower($user->email)])
                  ->orWhereRaw('LOWER(personal_email) = ?', [strtolower($user->email)]);
            })->first();

            if ($employee) {
                // Auto-link user_id for instant future lookups
                if (!$employee->user_id) {
                    $employee->updateQuietly(['user_id' => $user->id]);
                }
                return $employee;
            }
        }

        // 3. Tertiary check: match full_name with user name if available
        if (!empty($user->name)) {
            $employee = static::whereRaw('LOWER(full_name) = ?', [strtolower($user->name)])->first();
            if ($employee) {
                if (!$employee->user_id) {
                    $employee->updateQuietly(['user_id' => $user->id]);
                }
                return $employee;
            }
        }

        return null;
    }
}
