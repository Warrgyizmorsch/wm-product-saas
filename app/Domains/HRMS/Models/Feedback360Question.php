<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback360Question extends BaseModel
{
    use SoftDeletes;

    protected $table = 'feedback_360_questions';

    protected $fillable = [
        'tenant_id',
        'competency_id',
        'cycle_id',
        'question_text',
        'description',
        'question_type',
        'target_reviewer_type',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Feedback360Competency::class, 'competency_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Feedback360Cycle::class, 'cycle_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Feedback360Response::class, 'question_id');
    }
}
