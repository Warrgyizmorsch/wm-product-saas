<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderProgressLog extends BaseModel
{
    protected $table = 'production_order_progress_logs';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'operation_id',
        'quantity_produced',
        'quantity_rejected',
        'quantity_scrapped',
        'setup_minutes_logged',
        'run_minutes_logged',
        'recorded_by',
        'recorded_at',
        'machine_id',
        'start_time',
        'stop_time',
        'remarks',
    ];

    protected $casts = [
        'quantity_produced'    => 'float',
        'quantity_rejected'    => 'float',
        'quantity_scrapped'    => 'float',
        'setup_minutes_logged' => 'float',
        'run_minutes_logged'   => 'float',
        'recorded_at' => 'datetime',
        'start_time'  => 'datetime',
        'stop_time'   => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
