<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionMaintenanceWorkOrderAssignment extends BaseModel
{
    use HasFactory;

    protected $table = 'production_maintenance_work_order_assignments';

    public const TYPE_INTERNAL = 'internal';
    public const TYPE_EXTERNAL = 'external';

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'technician_id',
        'technician_name',
        'assignment_type',
        'assigned_at',
        'expected_work_hours',
        'worked_hours',
        'hourly_rate',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'expected_work_hours' => 'decimal:2',
        'worked_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionMaintenanceWorkOrder::class, 'work_order_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
