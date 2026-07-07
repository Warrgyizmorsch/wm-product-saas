<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperatorAssignmentLog extends BaseModel
{
    use HasFactory;

    protected $table = 'production_operator_assignment_logs';

    protected $fillable = [
        'tenant_id',
        'operator_assignment_id',
        'previous_operator_id',
        'new_operator_id',
        'action',
        'remarks',
        'changed_by',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProductionOperatorAssignment::class, 'operator_assignment_id');
    }

    public function previousOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_operator_id');
    }

    public function newOperator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_operator_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
