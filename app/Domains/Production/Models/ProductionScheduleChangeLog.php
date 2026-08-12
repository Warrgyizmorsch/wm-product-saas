<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionScheduleChangeLog extends BaseModel
{
    protected $table = 'production_schedule_change_logs';

    public const CHANGE_TYPE_MANUAL_SHIFT     = 'manual_shift';
    public const CHANGE_TYPE_RIPPLE_SHIFT     = 'ripple_shift';
    public const CHANGE_TYPE_MACHINE_REASSIGN = 'machine_reassign';
    public const CHANGE_TYPE_LOCK_TOGGLE     = 'lock_toggle';
    public const CHANGE_TYPE_RESCHEDULE_START = 'reschedule_start';
    public const CHANGE_TYPE_LEVEL_CAPACITY   = 'level_capacity';

    public const SHIFT_MODE_ISOLATED = 'isolated';
    public const SHIFT_MODE_RIPPLE   = 'ripple';

    protected $fillable = [
        'tenant_id',
        'production_schedule_id',
        'production_schedule_operation_id',
        'change_type',
        'shift_mode',
        'old_machine_id',
        'new_machine_id',
        'old_planned_start',
        'new_planned_start',
        'old_planned_finish',
        'new_planned_finish',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'old_planned_start'  => 'datetime',
        'new_planned_start'  => 'datetime',
        'old_planned_finish' => 'datetime',
        'new_planned_finish' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionScheduleOperation::class, 'production_schedule_operation_id');
    }

    public function oldMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'old_machine_id');
    }

    public function newMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'new_machine_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
