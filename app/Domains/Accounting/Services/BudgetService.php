<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Repositories\BudgetRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BudgetService
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_OVER = 'over';

    private const WARNING_THRESHOLD = 0.8;
    private const OVER_THRESHOLD = 1.0;

    public function __construct(
        private readonly BudgetRepositoryInterface $budgets,
    ) {
    }

    /**
     * @param array<int, array{chart_of_account_id: int, cost_center_id?: ?int, department_id?: ?int, project_id?: ?int, amount: float}> $lines
     */
    public function create(array $data, array $lines): Budget
    {
        $this->assertLinesAreValid($lines);

        return DB::transaction(function () use ($data, $lines) {
            $budget = $this->budgets->create([
                'tenant_id' => $data['tenant_id'] ?? tenant_id(),
                'company_id' => $data['company_id'] ?? company_id(),
                'branch_id' => $data['branch_id'] ?? branch_id(),
                'fiscal_year_id' => $data['fiscal_year_id'],
                'name' => $data['name'],
                'status' => Budget::STATUS_DRAFT,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $this->budgets->syncLines($budget, $lines);

            return $budget;
        });
    }

    /**
     * @param array<int, array{chart_of_account_id: int, cost_center_id?: ?int, department_id?: ?int, project_id?: ?int, amount: float}> $lines
     */
    public function update(Budget $budget, array $data, array $lines): Budget
    {
        if (!$budget->isEditable()) {
            throw new InvalidArgumentException("Budget '{$budget->name}' is {$budget->status} and can no longer be edited.");
        }

        $this->assertLinesAreValid($lines);

        return DB::transaction(function () use ($budget, $data, $lines) {
            $budget->update([
                'name' => $data['name'],
                'fiscal_year_id' => $data['fiscal_year_id'],
            ]);

            $this->budgets->syncLines($budget, $lines);

            return $budget->fresh();
        });
    }

    public function delete(Budget $budget): bool
    {
        if (!$budget->isEditable()) {
            throw new InvalidArgumentException("Budget '{$budget->name}' is {$budget->status} and cannot be deleted.");
        }

        return $this->budgets->delete($budget->id);
    }

    public function approve(Budget $budget, int $approvedBy): Budget
    {
        if ($budget->status !== Budget::STATUS_DRAFT) {
            throw new InvalidArgumentException("Only a draft budget can be approved; '{$budget->name}' is {$budget->status}.");
        }

        if ($budget->fiscalYear && !$budget->fiscalYear->isOpen()) {
            throw new InvalidArgumentException("Cannot approve a budget against closed fiscal year '{$budget->fiscalYear->name}'.");
        }

        return $this->budgets->update($budget->id, [
            'status' => Budget::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }

    /**
     * Merge a budget's lines with actuals posted so far this fiscal year
     * (from the fiscal year's start date through $asOfDate) and flag each
     * line ok/warning/over against the 80%/100% thresholds.
     *
     * @return array<int, array{
     *     line: \App\Domains\Accounting\Models\BudgetLine,
     *     budgeted: float,
     *     actual: ?float,
     *     variance: ?float,
     *     percent_used: ?float,
     *     status: string,
     *     has_actuals: bool,
     * }>
     */
    public function actualVsBudget(Budget $budget, ?\DateTimeInterface $asOfDate = null): array
    {
        $fiscalYear = $budget->fiscalYear;

        if ($fiscalYear === null) {
            throw new InvalidArgumentException("Budget '{$budget->name}' has no fiscal year.");
        }

        $asOfDate = $asOfDate ?? now();
        $from = Carbon::parse($fiscalYear->start_date)->startOfDay();
        $to = Carbon::parse($asOfDate)->min(Carbon::parse($fiscalYear->end_date))->endOfDay();

        $actuals = $this->budgets->actualsByAccountAndCostCenter($budget->tenant_id, $from, $to)
            ->keyBy(fn ($row) => $row->chart_of_account_id . ':' . ($row->cost_center_id ?? 'null'));

        return $budget->lines->map(function ($line) use ($actuals) {
            $dimension = $line->dimension();
            $budgeted = (float) $line->amount;

            // department/project dimensions have no matching column on journal_entries
            // yet, so there is no way to compute actuals for those lines — surface
            // that explicitly rather than silently showing a false zero.
            if ($dimension !== null && $dimension['type'] !== 'cost_center') {
                return [
                    'line' => $line,
                    'budgeted' => $budgeted,
                    'actual' => null,
                    'variance' => null,
                    'percent_used' => null,
                    'status' => self::STATUS_OK,
                    'has_actuals' => false,
                ];
            }

            $key = $line->chart_of_account_id . ':' . ($line->cost_center_id ?? 'null');
            $row = $actuals->get($key);

            $actual = $row
                ? $line->account->signedMovement((float) $row->debit, (float) $row->credit)
                : 0.0;

            $variance = round($budgeted - $actual, 2);
            $percentUsed = $budgeted > 0 ? round($actual / $budgeted, 4) : ($actual > 0 ? 1.0 : 0.0);

            $status = match (true) {
                $percentUsed >= self::OVER_THRESHOLD => self::STATUS_OVER,
                $percentUsed >= self::WARNING_THRESHOLD => self::STATUS_WARNING,
                default => self::STATUS_OK,
            };

            return [
                'line' => $line,
                'budgeted' => $budgeted,
                'actual' => round($actual, 2),
                'variance' => $variance,
                'percent_used' => $percentUsed,
                'status' => $status,
                'has_actuals' => true,
            ];
        })->all();
    }

    /**
     * @param array<int, array{chart_of_account_id: int, cost_center_id?: ?int, department_id?: ?int, project_id?: ?int, amount: float}> $lines
     */
    private function assertLinesAreValid(array $lines): void
    {
        if (count($lines) < 1) {
            throw new InvalidArgumentException('A budget requires at least one line.');
        }

        $seen = [];

        foreach ($lines as $line) {
            if (empty($line['chart_of_account_id'])) {
                throw new InvalidArgumentException('Every budget line requires an account.');
            }

            if ((float) ($line['amount'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Every budget line requires a positive amount.');
            }

            $dimensionsSet = array_filter([
                $line['cost_center_id'] ?? null,
                $line['department_id'] ?? null,
                $line['project_id'] ?? null,
            ]);

            if (count($dimensionsSet) > 1) {
                throw new InvalidArgumentException('A budget line can be scoped to only one of cost center, department, or project.');
            }

            $key = implode(':', [
                $line['chart_of_account_id'],
                $line['cost_center_id'] ?? 'null',
                $line['department_id'] ?? 'null',
                $line['project_id'] ?? 'null',
            ]);

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate account/dimension combination in budget lines.');
            }

            $seen[$key] = true;
        }
    }
}
