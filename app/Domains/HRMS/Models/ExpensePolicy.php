<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

/**
 * ExpensePolicy — Layer 1 (Policy Header).
 * A named policy (e.g. "Manager Travel Policy") that is assigned
 * to a Designation or Department and contains category-wise rules.
 */
class ExpensePolicy extends BaseModel
{
    protected $table = 'expense_policies';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'designation_id',
        'department_id',
        'company_id',
        'business_unit_id',
        'branch_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /** Policy has many category-level rules. */
    public function rules()
    {
        return $this->hasMany(ExpensePolicyRule::class, 'expense_policy_id');
    }

    /** Policy is assigned to a Designation (optional). */
    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    /** Policy is assigned to a Department (optional). */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Policy belongs to a Company (optional scope). */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** Policy belongs to a Business Unit (optional scope). */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    /** Policy belongs to a Branch (optional scope). */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
