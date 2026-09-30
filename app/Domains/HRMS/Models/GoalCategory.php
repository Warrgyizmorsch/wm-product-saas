<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoalCategory extends BaseModel
{
    use SoftDeletes;

    protected $table = 'goal_categories';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'color',
        'icon',
        'description',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'goal_category_id');
    }
}
