<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionCostAdjustment extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_cost_adjustments';

    public const COMPONENT_MATERIAL = 'material';
    public const COMPONENT_LABOR = 'labor';
    public const COMPONENT_MACHINE = 'machine';
    public const COMPONENT_OVERHEAD = 'overhead';
    public const COMPONENT_OTHER = 'other';

    public const CATEGORY_ADDITIONAL_LABOUR = 'Additional Labour';
    public const CATEGORY_MACHINE_BREAKDOWN = 'Machine Breakdown';
    public const CATEGORY_EMERGENCY_MAINTENANCE = 'Emergency Maintenance';
    public const CATEGORY_OUTSOURCING = 'Outsourcing';
    public const CATEGORY_TRANSPORT = 'Transport';
    public const CATEGORY_ELECTRICITY = 'Electricity';
    public const CATEGORY_FUEL = 'Fuel';
    public const CATEGORY_TOOL_DAMAGE = 'Tool Damage';
    public const CATEGORY_PACKAGING = 'Packaging';
    public const CATEGORY_QUALITY_FAILURE = 'Quality Failure';
    public const CATEGORY_REWORK_EXPENSE = 'Rework Expense';
    public const CATEGORY_MISCELLANEOUS = 'Miscellaneous';
    public const CATEGORY_OTHER = 'Other';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'adjustment_date',
        'cost_component',
        'category',
        'description',
        'amount',
        'attachment_path',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'amount'          => 'decimal:2',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
        'production_order_id' => 'integer',
    ];

    public static function getCostComponents(): array
    {
        return [
            self::COMPONENT_MATERIAL => 'Material',
            self::COMPONENT_LABOR    => 'Labor',
            self::COMPONENT_MACHINE  => 'Machine',
            self::COMPONENT_OVERHEAD => 'Overhead',
            self::COMPONENT_OTHER    => 'Other',
        ];
    }

    public static function getCategories(): array
    {
        return [
            self::CATEGORY_ADDITIONAL_LABOUR     => 'Additional Labour',
            self::CATEGORY_MACHINE_BREAKDOWN     => 'Machine Breakdown',
            self::CATEGORY_EMERGENCY_MAINTENANCE => 'Emergency Maintenance',
            self::CATEGORY_OUTSOURCING           => 'Outsourcing',
            self::CATEGORY_TRANSPORT             => 'Transport',
            self::CATEGORY_ELECTRICITY           => 'Electricity',
            self::CATEGORY_FUEL                  => 'Fuel',
            self::CATEGORY_TOOL_DAMAGE           => 'Tool Damage',
            self::CATEGORY_PACKAGING             => 'Packaging',
            self::CATEGORY_QUALITY_FAILURE       => 'Quality Failure',
            self::CATEGORY_REWORK_EXPENSE        => 'Rework Expense',
            self::CATEGORY_MISCELLANEOUS         => 'Miscellaneous',
            self::CATEGORY_OTHER                 => 'Other',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
