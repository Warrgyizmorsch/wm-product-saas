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

    public const SUBTYPE_CURRENT_ASSET = 'current_asset';
    public const SUBTYPE_FIXED_ASSET = 'fixed_asset';
    public const SUBTYPE_SECURITY_DEPOSIT = 'security_deposit';
    public const SUBTYPE_LOANS_ADVANCES = 'loans_advances';
    public const SUBTYPE_CURRENT_LIABILITY = 'current_liability';
    public const SUBTYPE_LONG_TERM_LIABILITY = 'long_term_liability';
    public const SUBTYPE_CAPITAL = 'capital';
    public const SUBTYPE_RESERVES_SURPLUS = 'reserves_surplus';
    public const SUBTYPE_DIRECT_INCOME = 'direct_income';
    public const SUBTYPE_INDIRECT_INCOME = 'indirect_income';
    public const SUBTYPE_COGS = 'cogs';
    public const SUBTYPE_OPERATING_EXPENSE = 'operating_expense';

    /**
     * Valid subtypes per top-level type.
     */
    public const SUBTYPES_BY_TYPE = [
        self::TYPE_ASSET => [
            self::SUBTYPE_CURRENT_ASSET,
            self::SUBTYPE_FIXED_ASSET,
            self::SUBTYPE_SECURITY_DEPOSIT,
            self::SUBTYPE_LOANS_ADVANCES,
        ],
        self::TYPE_LIABILITY => [
            self::SUBTYPE_CURRENT_LIABILITY,
            self::SUBTYPE_LONG_TERM_LIABILITY,
        ],
        self::TYPE_EQUITY => [
            self::SUBTYPE_CAPITAL,
            self::SUBTYPE_RESERVES_SURPLUS,
        ],
        self::TYPE_INCOME => [
            self::SUBTYPE_DIRECT_INCOME,
            self::SUBTYPE_INDIRECT_INCOME,
        ],
        self::TYPE_EXPENSE => [
            self::SUBTYPE_COGS,
            self::SUBTYPE_OPERATING_EXPENSE,
        ],
    ];

    public const SUBTYPE_LABELS = [
        self::SUBTYPE_CURRENT_ASSET => 'Current Asset',
        self::SUBTYPE_FIXED_ASSET => 'Fixed Asset',
        self::SUBTYPE_SECURITY_DEPOSIT => 'Security & Deposits',
        self::SUBTYPE_LOANS_ADVANCES => 'Loans & Advances',
        self::SUBTYPE_CURRENT_LIABILITY => 'Current Liability',
        self::SUBTYPE_LONG_TERM_LIABILITY => 'Long-term Liability',
        self::SUBTYPE_CAPITAL => 'Capital',
        self::SUBTYPE_RESERVES_SURPLUS => 'Reserves & Surplus',
        self::SUBTYPE_DIRECT_INCOME => 'Direct Income',
        self::SUBTYPE_INDIRECT_INCOME => 'Indirect Income',
        self::SUBTYPE_COGS => 'Cost of Goods Sold',
        self::SUBTYPE_OPERATING_EXPENSE => 'Operating Expense',
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

    /**
     * @return array<int, string>
     */
    public static function allSubtypes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::SUBTYPES_BY_TYPE))));
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
