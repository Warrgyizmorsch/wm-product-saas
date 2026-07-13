<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionQualityPlan extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_quality_plans';

    protected $fillable = [
        'tenant_id',
        'name',
        'version',
        'status',
        'type',
        'product_id',
        'product_category_id',
        'work_center_id',
        'created_by',
        'approved_by',
        'approved_at',
        'esignature',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ProductionQualityPlanParameter::class, 'quality_plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
