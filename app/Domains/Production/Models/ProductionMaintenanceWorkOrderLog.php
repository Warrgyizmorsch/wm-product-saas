<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaintenanceWorkOrderLog extends BaseModel
{
    use HasFactory;

    protected $table = 'production_maintenance_work_order_logs';

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'downtime_id',
        'machine_id',
        'user_id',
        'event_type',
        'summary',
        'details',
        'source',
        'status',
        'logged_at',
    ];

    protected $casts = [
        'details'   => 'array',
        'logged_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionMaintenanceWorkOrder::class, 'work_order_id');
    }

    public function downtime(): BelongsTo
    {
        return $this->belongsTo(ProductionMachineDowntime::class, 'downtime_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
