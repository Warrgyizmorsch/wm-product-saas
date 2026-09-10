<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\Budget;
use Illuminate\Database\Eloquent\Collection;

interface BudgetRepositoryInterface
{
    public function all(): Collection;

    public function find(int $id): ?Budget;

    public function findWithLines(int $id): ?Budget;

    public function create(array $data): Budget;

    public function update(int $id, array $data): Budget;

    public function delete(int $id): bool;

    public function syncLines(Budget $budget, array $lines): void;

    /**
     * Actuals posted against the given account/cost-center combinations,
     * summed from the fiscal year's start date through $asOfDate. One row
     * per (chart_of_account_id, cost_center_id) pair — department_id and
     * project_id are not accounting dimensions on journal_entries yet, so
     * they cannot be aggregated this way (see BudgetService::actualVsBudget()).
     *
     * @return Collection<int, object{chart_of_account_id: int, cost_center_id: ?int, debit: float, credit: float}>
     */
    public function actualsByAccountAndCostCenter(int $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): Collection;
}
