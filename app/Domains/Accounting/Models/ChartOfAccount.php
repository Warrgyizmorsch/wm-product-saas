<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends BaseModel
{
    use HasFactory, SoftDeletes;

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_INCOME = 'income';
    public const TYPE_EXPENSE = 'expense';

    public const TYPES = [
        self::TYPE_ASSET,
        self::TYPE_LIABILITY,
        self::TYPE_EQUITY,
        self::TYPE_INCOME,
        self::TYPE_EXPENSE,
    ];

    public const BALANCE_DEBIT = 'debit';
    public const BALANCE_CREDIT = 'credit';

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'subtype',
        'normal_balance',
        'parent_id',
        'description',
        'is_system',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'parent_id' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'chart_of_account_id');
    }

    public function isDebitNormal(): bool
    {
        return $this->normal_balance === self::BALANCE_DEBIT;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Signed movement for this account's normal balance direction:
     * a debit increases a debit-normal account and decreases a credit-normal one, and vice versa.
     */
    public function signedMovement(float $debit, float $credit): float
    {
        $net = $debit - $credit;

        return $this->isDebitNormal() ? $net : -$net;
    }
}
