<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\ExpenseApprovalWorkflow;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpensePolicyRule;
use Illuminate\Database\Eloquent\Collection;

interface ExpensePolicyRepositoryInterface
{
    public function getIndexData(array $filters, string $activeTab): array;

    public function storePolicy(array $data): ExpensePolicy;

    public function updatePolicy(ExpensePolicy $policy, array $data): bool;

    public function deletePolicy(ExpensePolicy $policy): bool;

    public function storeRule(ExpensePolicy $policy, array $data): ExpensePolicyRule;

    public function deleteRule(ExpensePolicyRule $rule): bool;

    public function getPolicyRulesData(ExpensePolicy $policy, array $filters): array;

    public function storeWorkflow(array $data): ExpenseApprovalWorkflow;

    public function updateWorkflow(ExpenseApprovalWorkflow $workflow, array $data): bool;

    public function deleteWorkflow(ExpenseApprovalWorkflow $workflow): bool;

    public function storeCategory(array $data): ExpenseCategory;

    public function updateCategory(ExpenseCategory $category, array $data): bool;

    public function deleteCategory(ExpenseCategory $category): bool;
}
