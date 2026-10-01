<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback360Participant extends BaseModel
{
    use SoftDeletes;

    protected $table = 'feedback_360_participants';

    protected $fillable = [
        'tenant_id',
        'cycle_id',
        'employee_id',
        'manager_id',
        'status',
        'self_score',
        'manager_score',
        'peer_score',
        'direct_report_score',
        'overall_score',
        'manager_summary',
        'development_plan',
        'published_at',
        'published_by',
    ];

    protected $casts = [
        'self_score'          => 'float',
        'manager_score'       => 'float',
        'peer_score'          => 'float',
        'direct_report_score' => 'float',
        'overall_score'       => 'float',
        'published_at'        => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Feedback360Cycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(Feedback360Nomination::class, 'participant_id');
    }

    /**
     * Compute and cache overall scores across reviewer categories.
     */
    public function recalculateScores(): void
    {
        $completedNoms = $this->nominations()
            ->where('status', 'completed')
            ->with(['responses' => function ($q) {
                $q->whereNotNull('rating_value');
            }])
            ->get();

        if ($completedNoms->isEmpty()) {
            return;
        }

        $byType = [
            'self'          => [],
            'manager'       => [],
            'peer'          => [],
            'direct_report' => [],
        ];

        $allRatings = [];

        foreach ($completedNoms as $nom) {
            $type = $nom->reviewer_type;
            foreach ($nom->responses as $resp) {
                if ($resp->rating_value !== null) {
                    $val = (float) $resp->rating_value;
                    $allRatings[] = $val;
                    if (isset($byType[$type])) {
                        $byType[$type][] = $val;
                    }
                }
            }
        }

        $this->self_score          = !empty($byType['self']) ? round(array_sum($byType['self']) / count($byType['self']), 2) : null;
        $this->manager_score       = !empty($byType['manager']) ? round(array_sum($byType['manager']) / count($byType['manager']), 2) : null;
        $this->peer_score          = !empty($byType['peer']) ? round(array_sum($byType['peer']) / count($byType['peer']), 2) : null;
        $this->direct_report_score = !empty($byType['direct_report']) ? round(array_sum($byType['direct_report']) / count($byType['direct_report']), 2) : null;
        $this->overall_score       = !empty($allRatings) ? round(array_sum($allRatings) / count($allRatings), 2) : null;

        // Auto update status if all approved nominations are completed
        $totalApproved = $this->nominations()->whereIn('status', ['approved', 'completed'])->count();
        $totalDone = $this->nominations()->where('status', 'completed')->count();
        if ($totalApproved > 0 && $totalDone >= $totalApproved && $this->status === 'in_progress') {
            $this->status = 'completed';
        }

        $this->save();
    }
}
