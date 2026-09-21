<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

/**
 * ExpenseApprovalWorkflow — Standalone approval routing rule.
 * Configures 1-Level, 2-Level, or Amount-Threshold approval workflows
 * globally or assigned to a Designation / Department / Scope.
 */
class ExpenseApprovalWorkflow extends BaseModel
{
    protected $table = 'expense_approval_workflows';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'designation_id',
        'department_id',
        'company_id',
        'business_unit_id',
        'branch_id',
        'approval_type',
        'first_approver',
        'second_approver',
        'amount_threshold_for_2_level',
        'is_default',
        'status',
    ];

    protected $casts = [
        'status'                       => 'boolean',
        'is_default'                   => 'boolean',
        'amount_threshold_for_2_level' => 'decimal:2',
    ];

    /** Workflow is assigned to a Designation (optional). */
    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    /** Workflow is assigned to a Department (optional). */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /** Workflow belongs to a Company (optional scope). */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** Workflow belongs to a Business Unit (optional scope). */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    /** Workflow belongs to a Branch (optional scope). */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
