<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback360Cycle extends BaseModel
{
    use SoftDeletes;

    protected $table = 'feedback_360_cycles';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'description',
        'start_date',
        'end_date',
        'nomination_deadline',
        'submission_deadline',
        'status',
        'is_peer_anonymous',
        'is_direct_report_anonymous',
        'min_peer_nominations',
        'max_peer_nominations',
        'allow_self_nomination',
        'require_manager_approval',
        'created_by',
    ];

    protected $casts = [
        'start_date'                  => 'date',
        'end_date'                    => 'date',
        'nomination_deadline'         => 'date',
        'submission_deadline'         => 'date',
        'is_peer_anonymous'           => 'boolean',
        'is_direct_report_anonymous'  => 'boolean',
        'min_peer_nominations'        => 'integer',
        'max_peer_nominations'        => 'integer',
        'allow_self_nomination'       => 'boolean',
        'require_manager_approval'    => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(Feedback360Competency::class, 'cycle_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Feedback360Question::class, 'cycle_id')->orderBy('sort_order');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Feedback360Participant::class, 'cycle_id');
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(Feedback360Nomination::class, 'cycle_id');
    }

    /**
     * Compute completion rate percentage of this cycle.
     */
    public function getCompletionRateAttribute(): float
    {
        $total = $this->nominations()->where('status', '!=', 'declined')->count();
        if ($total === 0) {
            return 0.0;
        }
        $completed = $this->nominations()->where('status', 'completed')->count();
        return round(($completed / $total) * 100, 1);
    }
}
