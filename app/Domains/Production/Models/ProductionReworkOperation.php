<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReworkOperation extends BaseModel
{
    use HasFactory;

    protected $table = 'production_rework_operations';

    protected $fillable = [
        'tenant_id',
        'rework_order_id',
        'sequence',
        'name',
        'work_center_id',
        'machine_id',
        'status',
        'setup_time_actual',
        'processing_time_actual',
        'actual_start',
        'actual_end',
    ];

    protected $casts = [
        'sequence'               => 'integer',
        'setup_time_actual'      => 'float',
        'processing_time_actual' => 'float',
        'actual_start'           => 'datetime',
        'actual_end'             => 'datetime',
    ];

    public function reworkOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionReworkOrder::class, 'rework_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
