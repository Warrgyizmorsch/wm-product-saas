<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical rate audit log for vendor-supplied items (FRS 2.3 / Task C-19).
 * Captures old rate, new rate, effective date, reason, and approving manager.
 */
class VendorItemPriceHistory extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'vendor_item_price_histories';

    protected $fillable = [
        'tenant_id',
        'vendor_item_id',
        'old_price',
        'new_price',
        'effective_from',
        'reason',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'old_price' => 'float',
        'new_price' => 'float',
        'effective_from' => 'date',
    ];

    public function vendorItem(): BelongsTo
    {
        return $this->belongsTo(VendorItem::class, 'vendor_item_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
