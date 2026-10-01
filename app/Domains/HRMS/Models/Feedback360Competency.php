<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback360Competency extends BaseModel
{
    use SoftDeletes;

    protected $table = 'feedback_360_competencies';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'cycle_id',
        'name',
        'code',
        'category',
        'description',
        'weightage',
        'is_active',
    ];

    protected $casts = [
        'weightage' => 'float',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Feedback360Cycle::class, 'cycle_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Feedback360Question::class, 'competency_id')->orderBy('sort_order');
    }
}
