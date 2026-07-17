<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequirement extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'material_requirements';

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'requirement_number',
        'requirement_date',
        'status',
        'carrier',
        'tracking_number',
        'notes',
    ];

    protected $casts = [
        'requirement_date' => 'date',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequirementItem::class, 'material_requirement_id');
    }
}
