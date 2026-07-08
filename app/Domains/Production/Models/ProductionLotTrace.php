<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionLotTrace extends BaseModel
{
    use HasFactory;

    protected $table = 'production_lot_traces';

    protected $fillable = [
        'tenant_id',
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'quantity',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
    ];

    /**
     * Get the parent source model (Batch, Serial, Order, or Lot).
     */
    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('source');
    }

    /**
     * Get the parent target model (Batch, Serial, Order, or Lot).
     */
    public function target(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('target');
    }
}
