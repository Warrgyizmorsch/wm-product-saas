<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends BaseModel
{
    public const STATUS_CREATED = 'created';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',
        'amount',
        'currency',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
