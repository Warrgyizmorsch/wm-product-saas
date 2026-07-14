<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends BaseModel
{
    use HasFactory;

    protected $table = 'accounting_tax_rates';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'rate',
        'is_compound',
        'is_active',
        'tax_payable_account_id',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_compound' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function taxPayableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_payable_account_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function amountFor(float $baseAmount): float
    {
        return round($baseAmount * ($this->rate / 100), 2);
    }
}
