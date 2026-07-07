<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionScrapDisposal extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_scrap_disposals';

    protected $fillable = [
        'tenant_id',
        'ncr_id',
        'category',
        'reason_code',
        'quantity',
        'cost',
        'status',
        'disposed_at',
        'disposed_by',
    ];

    protected $casts = [
        'quantity'    => 'float',
        'cost'        => 'float',
        'disposed_at' => 'datetime',
    ];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(ProductionNcr::class, 'ncr_id');
    }

    public function disposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposed_by');
    }
}
