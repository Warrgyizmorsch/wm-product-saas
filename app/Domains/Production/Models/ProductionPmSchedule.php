<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPmSchedule extends BaseModel
{
    use HasFactory;

    protected $table = 'production_pm_schedules';

    protected $attributes = [
        'is_active' => true,
    ];

    public const TYPE_PREVENTIVE  = 'preventive';
    public const TYPE_CALIBRATION = 'calibration';
    public const TYPE_INSPECTION  = 'inspection';

    public const FREQ_DAYS   = 'days';
    public const FREQ_WEEKS  = 'weeks';
    public const FREQ_MONTHS = 'months';

    public const PRIORITY_LOW      = 'low';
    public const PRIORITY_MEDIUM   = 'medium';
    public const PRIORITY_HIGH     = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    protected $fillable = [
        'tenant_id',
        'machine_id',
        'name',
        'code',
        'maintenance_type',
        'frequency_type',
        'frequency_value',
        'last_completed_date',
        'next_due_date',
        'estimated_duration_hours',
        'checklist_json',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'frequency_value'          => 'integer',
        'last_completed_date'      => 'date',
        'next_due_date'            => 'date',
        'estimated_duration_hours' => 'decimal:2',
        'checklist_json'           => 'array',
        'is_active'                => 'boolean',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(ProductionMaintenanceWorkOrder::class, 'pm_schedule_id');
    }

    public function isDue(): bool
    {
        if (!$this->is_active || !$this->next_due_date) {
            return false;
        }

        return $this->next_due_date->isPast() || $this->next_due_date->isToday();
    }

    public function isOverdue(): bool
    {
        if (!$this->is_active || !$this->next_due_date) {
            return false;
        }

        return $this->next_due_date->isPast() && !$this->next_due_date->isToday();
    }
}
