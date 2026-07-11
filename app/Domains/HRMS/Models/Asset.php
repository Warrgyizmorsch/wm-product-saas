<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $table = 'assets';

    protected $fillable = [
        'company_id',
        'asset_category_id',
        'asset_code',
        'name',
        'brand',
        'model_number',
        'serial_number',
        'purchase_date',
        'purchase_cost',
        'status',
        'assigned_employee_id',
        'allocated_at',
        'expected_return_date',
        'notes',
        'condition',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'allocated_at' => 'date',
        'expected_return_date' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    /**
     * Get the company owning the asset.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the category of the asset.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Get the employee currently assigned to this asset.
     */
    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /**
     * Get the history of allocations for this asset.
     */
    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AssetAllocation::class)->orderBy('allocated_at', 'desc');
    }
}
