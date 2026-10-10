<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class ExpenseCategory extends BaseModel
{
    protected $table = 'expense_categories';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Category has many policy rules (limits).
     */
    public function policyRules()
    {
        return $this->hasMany(ExpensePolicyRule::class, 'expense_category_id');
    }

    /**
     * Category belongs to many expense policies through policy rules.
     */
    public function policies()
    {
        return $this->belongsToMany(ExpensePolicy::class, 'expense_policy_rules', 'expense_category_id', 'expense_policy_id');
    }

    /**
     * Category has many claims.
     */
    public function claims()
    {
        return $this->hasMany(ExpenseClaim::class, 'expense_category_id');
    }
}
