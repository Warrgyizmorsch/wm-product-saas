<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAllocation extends BaseModel
{
    protected $fillable = [
        'asset_id',
        'employee_id',
        'allocated_at',
        'returned_at',
        'allocation_condition',
        'return_condition',
        'notes',
    ];

    protected $casts = [
        'allocated_at' => 'date',
        'returned_at' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
