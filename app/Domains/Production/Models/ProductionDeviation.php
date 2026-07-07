<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionDeviation extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_deviations';

    protected $fillable = [
        'tenant_id',
        'deviation_number',
        'type',
        'description',
        'expiration_date',
        'expiration_quantity',
        'status',
        'approved_by',
        'approved_at',
        'esignature',
    ];

    protected $casts = [
        'expiration_date'     => 'date',
        'expiration_quantity' => 'float',
        'approved_at'         => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
