<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One check of a Quality Plan. QR-06: min/max/unit only for numeric rows, "basis" (acceptance rule) for pass_fail rows.
 * QR-10: never hard-deleted — inspection results keep pointing to the parameter they were recorded against.
 */
class ProductionQualityPlanParameter extends BaseModel
{
    use HasFactory, SoftDeletes;

    public const TYPE_NUMERIC   = 'numeric';
    public const TYPE_PASS_FAIL = 'pass_fail';
    public const TYPE_TEXT      = 'text';

    public const TYPES = [
        self::TYPE_NUMERIC   => 'Numeric value',
        self::TYPE_PASS_FAIL => 'Pass / fail',
        self::TYPE_TEXT      => 'Text notes',
    ];

    protected $table = 'production_quality_plan_parameters';

    protected $fillable = [
        'tenant_id',
        'quality_plan_id',
        'name',
        'type',
        'min_value',
        'max_value',
        'uom_id',          // UOM master link
        'unit_of_measure', // saved UOM code (label shown on every screen)
        'basis',
        'sampling_type',
        'sampling_value',
        'is_mandatory',
        'sort_order',
    ];

    protected $casts = [
        'min_value'      => 'float',
        'max_value'      => 'float',
        'sampling_value' => 'float',
        'is_mandatory'   => 'boolean',
        'sort_order'     => 'integer',
        'uom_id'         => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id')->withTrashed();
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Uom::class, 'uom_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    /** "19 – 21 g/m²" for numeric rows, the basis for pass/fail rows, "—" otherwise. No rounding: trailing zeros trimmed only. */
    public function limitsLabel(): string
    {
        if ($this->type === self::TYPE_NUMERIC) {
            $fmt = static fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
            $min = $fmt($this->min_value);
            $max = $fmt($this->max_value);
            $unit = $this->unit_of_measure ? ' ' . $this->unit_of_measure : '';
            if ($min !== null && $max !== null) {
                return "{$min} – {$max}{$unit}";
            }
            if ($min !== null) {
                return "≥ {$min}{$unit}";
            }
            if ($max !== null) {
                return "≤ {$max}{$unit}";
            }

            return 'No limits' . ($unit ? " ({$this->unit_of_measure})" : '');
        }

        if ($this->type === self::TYPE_PASS_FAIL) {
            return $this->basis ? 'Basis: ' . $this->basis : '—';
        }

        return '—';
    }
}
