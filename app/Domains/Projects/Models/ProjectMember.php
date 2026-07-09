<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectMember extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'user_id',
        'project_role',
        'rate_per_hour',
        'cost_per_hour',
        'budget_hours',
        'is_active',
    ];

    protected $casts = [
        'rate_per_hour' => 'decimal:2',
        'cost_per_hour' => 'decimal:2',
        'budget_hours'  => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
