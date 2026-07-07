<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSerialNumber extends BaseModel
{
    use HasFactory;

    protected $table = 'production_serial_numbers';

    public const STATUS_PLANNED   = 'planned';
    public const STATUS_PRODUCED  = 'produced';
    public const STATUS_PACKED    = 'packed';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_RETURNED  = 'returned';
    public const STATUS_SCRAPPED  = 'scrapped';
    public const STATUS_REWORKED  = 'reworked';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'batch_id',
        'product_id',
        'serial_number',
        'manufactured_at',
        'status',
        'barcode',
        'qr_code',
    ];

    protected $casts = [
        'manufactured_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
