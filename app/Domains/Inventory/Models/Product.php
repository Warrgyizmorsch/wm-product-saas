<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'sku',
        'type', // finished_good, raw_material, component, etc.
        'planning_type', // stock, manufacture, purchase, manual
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
            return $this->variants->sum(fn($v) => $v->warehouseStocks->sum('quantity'));
        }
        return $this->warehouseStocks->sum('quantity');
    }
}
