<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vendor-specific item catalog mapping (FRS 2.3 / Task C-19).
 * Maps internal Products to vendor catalog codes, descriptions, contracted rates, MOQ, and lead time.
 */
class VendorItem extends BaseModel
{
    use HasFactory, SoftDeletes, BelongsToCompany, BelongsToBranch;

    protected $table = 'vendor_items';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'vendor_id',
        'product_id',
        'vendor_item_code',
        'vendor_item_name',
        'unit_price',
        'moq',
        'lead_time_days',
        'is_preferred',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'moq' => 'float',
        'lead_time_days' => 'integer',
        'is_preferred' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(VendorItemPriceHistory::class, 'vendor_item_id')->orderByDesc('effective_from');
    }

    public function latestPriceHistory(): HasOne
    {
        return $this->hasOne(VendorItemPriceHistory::class, 'vendor_item_id')->latestOfMany('effective_from');
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
