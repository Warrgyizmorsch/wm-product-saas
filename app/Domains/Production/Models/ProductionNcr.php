<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionNcr extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_ncrs';

    protected $fillable = [
        'tenant_id',
        'ncr_number',
        'category',
        'status',
        'disposition_type',
        'quality_inspection_id',
        'production_order_id',
        'production_order_operation_id',
        'machine_id',
        'operator_id',
        'batch_id',
        'serial_number_id',
        'description',
        'esignature_closed',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityInspection::class, 'quality_inspection_id');
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

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reworkOrder(): HasOne
    {
        return $this->hasOne(ProductionReworkOrder::class, 'ncr_id');
    }

    public function scrapDisposal(): HasOne
    {
        return $this->hasOne(ProductionScrapDisposal::class, 'ncr_id');
    }
}
