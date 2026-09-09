<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceImprovementPlan extends BaseModel
{
    use SoftDeletes;

    protected $table = 'performance_improvement_plans';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'pip_number',
        'employee_id',
        'manager_id',
        'hr_representative_id',
        'pip_category_id',
        'reason_category',
        'reason_details',
        'start_date',
        'end_date',
        'duration_days',
        'checkin_frequency',
        'status',
        'final_outcome',
        'final_comments',
        'completed_at',
        'employee_signed_at',
        'manager_signed_at',
        'hr_signed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'completed_at' => 'datetime',
        'employee_signed_at' => 'datetime',
        'manager_signed_at' => 'datetime',
        'hr_signed_at' => 'datetime',
        'duration_days' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function hrRepresentative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_representative_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PipCategory::class, 'pip_category_id');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(PipObjective::class, 'pip_id');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(PipCheckin::class, 'pip_id')->orderBy('review_date', 'asc');
    }

    /**
     * Smart Next Check-in Due Date calculation based on frequency and last checkin date.
     */
    public function getNextCheckinDueDateAttribute(): ?\Carbon\Carbon
    {
        if (!in_array($this->status, ['active', 'under_review', 'extended']) || !$this->start_date) {
            return null;
        }

        $days = match($this->checkin_frequency) {
            'weekly'   => 7,
            'biweekly' => 14,
            'monthly'  => 30,
            default    => 7,
        };

        $latestCheckin = $this->checkins->sortByDesc('review_date')->first();
        $baseDate = $latestCheckin ? \Carbon\Carbon::parse($latestCheckin->review_date) : \Carbon\Carbon::parse($this->start_date);

        return $baseDate->copy()->addDays($days);
    }

    /**
     * Determine if next check-in is overdue.
     */
    public function getIsCheckinOverdueAttribute(): bool
    {
        $nextDue = $this->next_checkin_due_date;
        return $nextDue ? \Carbon\Carbon::now()->startOfDay()->greaterThan($nextDue->startOfDay()) : false;
    }

    /**
     * Calculate days overdue.
     */
    public function getCheckinOverdueDaysAttribute(): int
    {
        if (!$this->is_checkin_overdue || !$this->next_checkin_due_date) {
            return 0;
        }
        return (int) \Carbon\Carbon::now()->startOfDay()->diffInDays($this->next_checkin_due_date->startOfDay());
    }
}
