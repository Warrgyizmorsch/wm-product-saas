<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ExpensePolicyRule — Layer 2 of expense policy.
 * Stores the per-category spending limits within a named policy.
 */
class ExpensePolicyRule extends Model
{
    protected $table = 'expense_policy_rules';

    protected $fillable = [
        'expense_policy_id',
        'expense_category_id',
        'max_limit_per_claim',
        'max_daily_limit',
        'max_monthly_limit',
        'receipt_required_threshold',
        'receipt_required',
        'notes',
    ];

    protected $casts = [
        'max_limit_per_claim'        => 'decimal:2',
        'max_daily_limit'            => 'decimal:2',
        'max_monthly_limit'          => 'decimal:2',
        'receipt_required_threshold' => 'decimal:2',
        'receipt_required'           => 'boolean',
    ];

    /** Rule belongs to a named Expense Policy. */
    public function policy()
    {
        return $this->belongsTo(ExpensePolicy::class, 'expense_policy_id');
    }

    /** Rule belongs to an Expense Category. */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
