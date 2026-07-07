<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBatchGenealogy extends BaseModel
{
    use HasFactory;

    protected $table = 'production_batch_genealogies';

    protected $fillable = [
        'tenant_id',
        'parent_batch_id',
        'child_batch_id',
        'type',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    public function parentBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'parent_batch_id');
    }

    public function childBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'child_batch_id');
    }
}
