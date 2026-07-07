<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOperatorAssignment extends BaseModel
{
    use HasFactory;

    protected $table = 'production_operator_assignments';

    public const STATUS_ASSIGNED  = 'assigned';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'tenant_id',
        'production_order_operation_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'accepted_at',
        'completed_at',
        'status',
        'remarks',
    ];

    protected $casts = [
        'assigned_at'  => 'datetime',
        'accepted_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionOperatorAssignmentLog::class, 'operator_assignment_id');
    }
}
