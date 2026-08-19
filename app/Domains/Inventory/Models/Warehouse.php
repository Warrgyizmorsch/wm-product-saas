<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends BaseModel
{
    use HasFactory;

    protected $table = 'warehouses';

    public const TYPE_STANDARD = 'standard';
    public const TYPE_SUBCONTRACTOR = 'subcontractor';
    public const TYPE_VIRTUAL = 'virtual';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'vendor_id',
        'status',
        'address',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Vendor::class, 'vendor_id');
    }
}
