<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEventTimeline extends BaseModel
{
    use HasFactory;

    protected $table = 'production_event_timelines';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'production_order_operation_id',
        'production_batch_id',
        'production_serial_number_id',
        'machine_id',
        'operator_id',
        'event_type',
        'title',
        'description',
        'severity',
        'event_source',
        'event_time',
        'triggered_by',
        'metadata',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'metadata'   => 'json',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(ProductionSerialNumber::class, 'production_serial_number_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function triggerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
