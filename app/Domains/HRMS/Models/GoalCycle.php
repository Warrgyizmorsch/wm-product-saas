<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoalCycle extends BaseModel
{
    use SoftDeletes;

    protected $table = 'goal_cycles';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'goal_cycle_id');
    }
}
