<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubTask extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'project_sub_tasks';

    protected $fillable = [
        'tenant_id',
        'task_id',
        'title',
        'assignee_id',
        'is_completed',
        'position',
        'completed_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'position'     => 'integer',
        'completed_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
