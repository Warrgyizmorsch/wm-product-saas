<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SopDocument extends BaseModel
{
    use SoftDeletes;

    protected $table = 'sop_documents';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'sop_category_id',
        'department_id',
        'code',
        'title',
        'summary',
        'objective',
        'scope',
        'prerequisites',
        'version',
        'status',
        'criticality',
        'target_audience_type',
        'target_department_ids',
        'target_designation_ids',
        'target_employee_ids',
        'is_mandatory',
        'auto_assign_new_hires',
        'acknowledgment_days_limit',
        'effective_date',
        'review_interval_months',
        'next_review_date',
        'created_by',
        'approved_by',
        'approved_at',
        'attachment_path',
    ];

    protected $casts = [
        'target_department_ids' => 'array',
        'target_designation_ids' => 'array',
        'target_employee_ids' => 'array',
        'is_mandatory' => 'boolean',
        'auto_assign_new_hires' => 'boolean',
        'acknowledgment_days_limit' => 'integer',
        'review_interval_months' => 'integer',
        'effective_date' => 'date',
        'next_review_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SopCategory::class, 'sop_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SopSection::class, 'sop_document_id')->orderBy('step_number');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SopAssignment::class, 'sop_document_id');
    }

    public function versionHistories(): HasMany
    {
        return $this->hasMany(SopVersionHistory::class, 'sop_document_id')->latest();
    }

    public function getComplianceRateAttribute(): float
    {
        $total = $this->assignments()->count();
        if ($total === 0) {
            return 0.0;
        }
        $acknowledged = $this->assignments()->where('status', 'acknowledged')->count();
        return round(($acknowledged / $total) * 100, 1);
    }
}
