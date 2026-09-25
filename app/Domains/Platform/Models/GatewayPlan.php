<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache of plans created on a payment gateway (Razorpay plans are immutable,
 * so one exists per period + per-seat amount and is reused). Platform-wide.
 */
class GatewayPlan extends Model
{
    protected $fillable = [
        'gateway',
        'period',
        'amount',
        'currency',
        'gateway_plan_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
