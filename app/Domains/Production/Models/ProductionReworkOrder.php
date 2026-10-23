<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionReworkOrder extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_rework_orders';

    protected $fillable = [
        'tenant_id',
        'rework_number',
        'ncr_id',
        'original_production_order_id',
        'status',
        'cost_estimate',
        'actual_cost',
        'labor_hours_actual',
        'machine_hours_actual',
    ];

    protected $casts = [
        'cost_estimate' => 'float',
        'actual_cost' => 'float',
        'labor_hours_actual' => 'float',
        'machine_hours_actual' => 'float',
    ];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(ProductionNcr::class, 'ncr_id');
    }

    public function originalOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'original_production_order_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionReworkOperation::class, 'rework_order_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProductionOrderIssue::class, 'rework_order_id');
    }

    public function requisitionSlips(): HasMany
    {
        return $this->hasMany(ProductionRequisitionSlip::class, 'rework_order_id');
    }

    public function getMaterialCostAttribute(): float
    {
        $issues = $this->relationLoaded('issues')
            ? $this->issues
            : $this->issues()->with(['product', 'batches.stockTransaction'])->get();

        return (float) $issues->sum(fn($issue) => $issue->total_cost);
    }

    /**
     * Format decimal hours to clock-based HH.MM hours display (e.g. 0.75 hrs [45 mins] => "0.45 hrs")
     */
    public function getFormattedLaborHoursAttribute(): string
    {
        $totalMins = (int) round(((float) $this->labor_hours_actual) * 60);
        $h = intdiv($totalMins, 60);
        $m = $totalMins % 60;

        return sprintf('%d.%02d hrs', $h, $m);
    }

    /**
     * Format decimal hours to clock-based HH.MM hours display (e.g. 0.75 hrs [45 mins] => "0.45 hrs")
     */
    public function getFormattedMachineHoursAttribute(): string
    {
        $totalMins = (int) round(((float) $this->machine_hours_actual) * 60);
        $h = intdiv($totalMins, 60);
        $m = $totalMins % 60;

        return sprintf('%d.%02d hrs', $h, $m);
    }
}
