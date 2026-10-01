<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback360Response extends BaseModel
{
    public $timestamps = true;

    protected $table = 'feedback_360_responses';

    protected $fillable = [
        'tenant_id',
        'nomination_id',
        'question_id',
        'competency_id',
        'rating_value',
        'text_response',
    ];

    protected $casts = [
        'rating_value' => 'float',
    ];

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(Feedback360Nomination::class, 'nomination_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Feedback360Question::class, 'question_id');
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Feedback360Competency::class, 'competency_id');
    }
}
