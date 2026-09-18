<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastReceipt extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'broadcast_id',
        'employee_id',
        'delivered_at',
        'read_at',
        'acknowledged_at',
        'ip_address',
        'device_type',
    ];

    protected $casts = [
        'delivered_at'    => 'datetime',
        'read_at'         => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class, 'broadcast_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
