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
     * Category has many policies.
     */
    public function policies()
    {
        return $this->hasMany(ExpensePolicy::class, 'expense_category_id');
    }

    /**
     * Category has many claims.
     */
    public function claims()
    {
        return $this->hasMany(ExpenseClaim::class, 'expense_category_id');
    }
}
