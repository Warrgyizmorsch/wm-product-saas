<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'products';

    public const MODEL_PURE_MANUFACTURING = 'pure_manufacturing';
    public const MODEL_SUBCONTRACT_COMPLETE = 'subcontract_complete';
    public const MODEL_SUBCONTRACT_COMPANY_MATERIAL = 'subcontract_company_material';
    public const MODEL_HYBRID = 'hybrid';

    public const PRODUCTION_MODELS = [
        self::MODEL_PURE_MANUFACTURING,
        self::MODEL_SUBCONTRACT_COMPLETE,
        self::MODEL_SUBCONTRACT_COMPANY_MATERIAL,
        self::MODEL_HYBRID,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'parent_id',
        'name',
        'sku',
        'type', // finished_good, raw_material, component, etc.
        'planning_type', // stock, manufacture, purchase, manual
        'default_production_model', // pure_manufacturing, subcontract_complete, subcontract_company_material, hybrid
        'supplier_method',
        'item_type', // Goods, Service
        'variation_type', // Single, Variant
        'uom_id',
        'status',
        'unit_cost',
        'hsn_sac',
        'gst_rate',
        'preferred_vendor_id',
        'selling_price',
        'cost_price',
        'sales_account',
        'purchase_account',
        'inventory_account',
        'reorder_point',
        'minimum_order_qty',
        'order_multiple',
        'opening_stock',
        'opening_stock_rate',
        'description',
        'attributes_config',
        'variant_values',
        'brand',
        'manufacturer',
        'mpn',
        'barcode',
        'upc',
        'ean',
        'isbn',
        'length',
        'width',
        'height',
        'weight',
        'dimension_unit',
        'weight_unit',
        'track_serial_number',
        'track_batch',
        'inventory_valuation_method',
        'image_path',
    ];

    protected $casts = [
        'unit_cost' => 'float',
        'gst_rate' => 'float',
        'selling_price' => 'float',
        'cost_price' => 'float',
        'reorder_point' => 'float',
        'minimum_order_qty' => 'float',
        'order_multiple' => 'float',
        'opening_stock' => 'float',
        'opening_stock_rate' => 'float',
        'length' => 'float',
        'width' => 'float',
        'height' => 'float',
        'weight' => 'float',
        'track_serial_number' => 'boolean',
        'track_batch' => 'boolean',
        'attributes_config' => 'array',
        'variant_values' => 'array',
    ];

    public function getDefaultProductionMode(): string
    {
        if ($this->track_batch && $this->track_serial_number) {
            return 'batch_and_serial';
        }
        if ($this->track_batch) {
            return 'batch';
        }
        if ($this->track_serial_number) {
            return 'serial';
        }
        return 'standard';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'product_id')->orderBy('created_at', 'desc');
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'product_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'product_id');
    }

    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class, 'product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'preferred_vendor_id');
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class, 'product_id');
    }

    /**
     * Helper to get total stock across all warehouses
     */
    public function getTotalStockAttribute(): float
    {
        if ($this->variation_type === 'Variant') {
            $variantWhSum = (float)$this->variants->sum(fn($v) => $v->warehouseStocks->sum('quantity'));
            return $variantWhSum > 0 ? $variantWhSum : (float)$this->variants->sum('opening_stock');
        }
        $whSum = (float)$this->warehouseStocks->sum('quantity');
        return $whSum > 0 ? $whSum : (float)$this->opening_stock;
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function primaryImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductImage::class, 'product_id')->where('is_primary', true);
    }

    public function detailImages(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->where('is_primary', false)->orderBy('sort_order', 'asc');
    }

    /**
     * Get Main Image URL with fallback to parent template or placeholder
     */
    public function getMainImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('primaryImage') && $this->primaryImage) {
            return $this->primaryImage->url;
        }
        
        $primary = $this->primaryImage()->first();
        if ($primary) {
            return $primary->url;
        }

        // Check if there is any image attached to this product
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            return $this->images->first()->url;
        }
        $firstImg = $this->images()->first();
        if ($firstImg) {
            return $firstImg->url;
        }

        // If legacy image_path exists
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }

        // If template master product has child variants, check first variant's image
        if ($this->variation_type === 'Variant' && !$this->parent_id) {
            if ($this->relationLoaded('variants')) {
                foreach ($this->variants as $variant) {
                    if ($variant->main_image_url) {
                        return $variant->main_image_url;
                    }
                }
            } else {
                $variantWithImg = $this->variants()->whereHas('images')->with(['primaryImage', 'images'])->first();
                if ($variantWithImg && $variantWithImg->main_image_url) {
                    return $variantWithImg->main_image_url;
                }
            }
        }

        // If child variant, fallback to parent master product primary image
        if ($this->parent_id && $this->parent) {
            return $this->parent->main_image_url;
        }

        return null;
    }

    /**
     * Get thumbnail URL with fallback placeholder
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->main_image_url ?: asset('assets/images/icons/default-product.svg');
    }

    /**
     * Scope query to only sellable / transactable products.
     * Excludes master variant parent templates (variation_type = 'Variant' AND parent_id IS NULL)
     * because abstract parent templates do not hold physical stock.
     */
    public function scopeSellable(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where(function($q) {
            $q->where('variation_type', '!=', 'Variant')
              ->orWhereNotNull('parent_id');
        });
    }
}
