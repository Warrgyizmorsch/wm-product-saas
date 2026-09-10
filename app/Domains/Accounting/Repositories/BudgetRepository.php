<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Collection;

class BudgetRepository implements BudgetRepositoryInterface
{
    public function all(): Collection
    {
        return Budget::with('fiscalYear')->orderByDesc('id')->get();
    }

    public function find(int $id): ?Budget
    {
        return Budget::find($id);
    }

    public function findWithLines(int $id): ?Budget
    {
        return Budget::with(['fiscalYear', 'lines.account', 'lines.costCenter'])->find($id);
    }

    public function create(array $data): Budget
    {
        return Budget::create($data);
    }

    public function update(int $id, array $data): Budget
    {
        $budget = Budget::findOrFail($id);
        $budget->update($data);

        return $budget;
    }

    public function delete(int $id): bool
    {
        return (bool) Budget::destroy($id);
    }

    public function syncLines(Budget $budget, array $lines): void
    {
        $budget->lines()->delete();

        foreach ($lines as $line) {
            $budget->lines()->create([
                'tenant_id' => $budget->tenant_id,
                'company_id' => $budget->company_id,
                'branch_id' => $budget->branch_id,
                'chart_of_account_id' => $line['chart_of_account_id'],
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'department_id' => $line['department_id'] ?? null,
                'project_id' => $line['project_id'] ?? null,
                'amount' => $line['amount'],
            ]);
        }
    }

    public function actualsByAccountAndCostCenter(int $tenantId, \DateTimeInterface $from, \DateTimeInterface $to): Collection
    {
        return JournalEntry::query()
            ->select('chart_of_account_id', 'cost_center_id')
            ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')
            ->whereHas('journal', fn ($q) => $q
                ->where('tenant_id', $tenantId)
                ->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
                ->whereBetween('journal_date', [$from, $to]))
            ->groupBy('chart_of_account_id', 'cost_center_id')
            ->get();
    }
}
