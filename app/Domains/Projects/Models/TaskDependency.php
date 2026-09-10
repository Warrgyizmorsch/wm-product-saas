<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'project_task_dependencies';

    public const TYPE_FINISH_TO_START = 'Finish-to-Start';
    public const TYPE_START_TO_START = 'Start-to-Start';
    public const TYPE_FINISH_TO_FINISH = 'Finish-to-Finish';
    public const TYPE_START_TO_FINISH = 'Start-to-Finish';

    public const TYPES = [
        self::TYPE_FINISH_TO_START,
        self::TYPE_START_TO_START,
        self::TYPE_FINISH_TO_FINISH,
        self::TYPE_START_TO_FINISH,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_id',
        'task_id',
        'depends_on_task_id',
        'dependency_type',
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
