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
        'is_verified_on_exit',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity'            => 'integer',
            'is_verified_on_exit' => 'boolean',
        ];
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(VisitorPass::class, 'visitor_pass_id');
    }
}
