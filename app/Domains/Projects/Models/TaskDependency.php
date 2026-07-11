<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $table = 'project_task_dependencies';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'task_id',
        'depends_on_task_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}
