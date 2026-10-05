<?php

namespace App\Domains\Visitor\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToTenant;

class VisitorBelonging extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'visitor_belongings';

    protected $fillable = [
        'tenant_id',
        'visitor_pass_id',
        'item_type',
        'brand_model',
        'serial_number',
        'quantity',
        'is_returnable',
        'gate_pass_number',
        'is_verified_on_exit',
        'exit_verified_by',
        'exit_verified_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity'            => 'integer',
            'is_returnable'       => 'boolean',
            'is_verified_on_exit' => 'boolean',
            'exit_verified_at'    => 'datetime',
        ];
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(VisitorPass::class, 'visitor_pass_id');
    }
}
