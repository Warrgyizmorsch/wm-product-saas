<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionMachineDowntime extends BaseModel
{
    use HasFactory;

    /**
     * PX-10: opening / closing a downtime changes the readiness of the operations planned on that machine.
     */
    protected static function booted(): void
    {
        $refresh = function (self $downtime) {
            if ($downtime->machine_id) {
                app(\App\Domains\Production\Services\ProductionReadinessRefresher::class)
                    ->forMachine((int) $downtime->tenant_id, (int) $downtime->machine_id);
            }
        };
        static::saved($refresh);
        static::deleted($refresh);
    }

    protected $table = 'production_machine_downtimes';

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'machine_id',
        'work_center_id',
        'production_order_id',
        'production_order_operation_id',
        'reason',
        'category',
        'start_time',
        'end_time',
        'duration_minutes',
        'created_by',
        'approved_by',
        'remarks',
        'status',
    ];

    protected $casts = [
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'duration_minutes' => 'decimal:2',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function workOrderLogs(): HasMany
    {
        return $this->hasMany(ProductionMaintenanceWorkOrderLog::class, 'downtime_id');
    }
}
