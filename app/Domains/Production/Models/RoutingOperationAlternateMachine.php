<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperationAlternateMachine extends BaseModel
{
    use HasFactory;

    protected $table = 'production_routing_operation_alternate_machines';

    protected $fillable = [
        'tenant_id',
        'routing_operation_id',
        'machine_id',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
