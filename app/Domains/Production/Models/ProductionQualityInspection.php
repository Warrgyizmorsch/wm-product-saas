<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionQualityInspection extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_quality_inspections';

    protected $fillable = [
        'tenant_id',
        'quality_plan_id',
        'stage',
        'status',
        'result',
        'production_order_id',
        'production_order_operation_id',
        'machine_id',
        'operator_id',
        'batch_id',
        'serial_number_id',
        'audited_by',
        'audited_at',
        'esignature',
        'attachments_json',
    ];

    protected $casts = [
        'attachments_json' => 'array',
        'audited_at'       => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ProductionQualityInspectionResult::class, 'quality_inspection_id');
    }
}
