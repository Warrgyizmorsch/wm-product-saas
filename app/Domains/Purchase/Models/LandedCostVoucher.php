<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandedCostVoucher extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'landed_cost_vouchers';

    protected $fillable = [
        'tenant_id',
        'voucher_number',
        'voucher_date',
        'status', // Draft, Posted, Cancelled
        'posting_date',
        'total_expenses',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'posting_date' => 'date',
        'posted_at' => 'datetime',
        'total_expenses' => 'float',
    ];

    public function receipts(): HasMany
    {
        return $this->hasMany(LandedCostReceipt::class, 'landed_cost_voucher_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(LandedCostExpense::class, 'landed_cost_voucher_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandedCostItem::class, 'landed_cost_voucher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isDraft(): bool
    {
        return strtolower($this->status) === 'draft';
    }

    public function isPosted(): bool
    {
        return strtolower($this->status) === 'posted';
    }

    public function isCancelled(): bool
    {
        return strtolower($this->status) === 'cancelled';
    }
}
