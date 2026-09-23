<?php

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide per-user price of a module sold as an add-on (whole rupees
 * per user per month; yearly_* is the per-month price when billed yearly).
 * Not tenant-owned — same as Plan.
 */
class ModulePrice extends Model
{
    protected $fillable = [
        'module',
        'monthly_price_per_user',
        'yearly_price_per_user',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price_per_user' => 'integer',
            'yearly_price_per_user' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Per user per month on $cycle, or null when not sold on it. */
    public function pricePerUser(string $cycle): ?int
    {
        return $cycle === 'yearly' ? $this->yearly_price_per_user : $this->monthly_price_per_user;
    }
}
