<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRequest extends BaseModel
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'asset_category_id',
        'asset_item_id',
        'quantity',
        'requested_asset_id',
        'reason',
        'request_date',
        'status',
        'allocated_asset_id',
        'admin_notes',
    ];

    protected $casts = [
        'request_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AssetItem::class, 'asset_item_id');
    }

    public function allocatedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'allocated_asset_id');
    }

    public function requestedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'requested_asset_id');
    }

    public function allocatedAssets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Asset::class, 'asset_request_id');
    }
}
