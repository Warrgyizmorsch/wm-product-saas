<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "When a bank line's narration matches X, post it to ledger Y (party Z)".
 * Entered by users, or learned from entries they post while reconciling.
 * See BankReconciliationRuleService.
 */
class BankReconciliationRule extends BaseModel
{
    public const MATCH_CONTAINS = 'contains';
    public const MATCH_STARTS_WITH = 'starts_with';
    public const MATCH_EQUALS = 'equals';
    public const MATCH_REGEX = 'regex';
    public const MATCH_TYPES = [self::MATCH_CONTAINS, self::MATCH_STARTS_WITH, self::MATCH_EQUALS, self::MATCH_REGEX];

    public const DIRECTION_ANY = 'any';
    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';
    public const DIRECTIONS = [self::DIRECTION_ANY, self::DIRECTION_IN, self::DIRECTION_OUT];

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_LEARNED = 'learned';

    protected $table = 'bank_reconciliation_rules';

    protected $fillable = [
        'tenant_id',
        'bank_account_id',
        'name',
        'match_type',
        'pattern',
        'direction',
        'min_amount',
        'max_amount',
        'target_account_id',
        'party_name',
        'narration',
        'priority',
        'auto_post',
        'is_active',
        'source',
        'hits',
        'last_used_at',
        'created_by',
    ];

    protected $casts = [
        'min_amount' => 'float',
        'max_amount' => 'float',
        'priority' => 'integer',
        'auto_post' => 'boolean',
        'is_active' => 'boolean',
        'hits' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'bank_account_id');
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'target_account_id');
    }
}
