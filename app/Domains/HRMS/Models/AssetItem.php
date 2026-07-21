<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetItem extends BaseModel
{
    protected $table = 'asset_items';

    protected $fillable = [
        'company_id',
        'asset_category_id',
        'name',
        'description',
    ];

    /**
     * Get the company that owns the item.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the category of the item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Get the physical asset units belonging to this item type.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_item_id');
    }
}
