<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class JournalService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
        private readonly FiscalPeriodService $periods,
    ) {
    }

    /**
     * Post a balanced double-entry journal.
     *
     * @param array<int, array{chart_of_account_id: int, debit?: float, credit?: float, description?: string}> $lines
     * @param array{
     *     tenant_id?: int,
     *     journal_date?: string|\DateTimeInterface,
     *     source?: string,
     *     reference_type?: string,
     *     reference_id?: int,
     *     memo?: string,
     *     posted_by?: int,
     * } $meta
     */
    public function post(array $lines, array $meta = []): Journal
    {
        $tenantId = $meta['tenant_id'] ?? tenant_id();
        $journalDate = Carbon::parse($meta['journal_date'] ?? now());

        $this->assertLinesAreBalanced($lines);

        return DB::transaction(function () use ($lines, $meta, $tenantId, $journalDate) {
            $period = $this->periods->assertOpenPeriodForDate($journalDate);

            $totalDebit = array_sum(array_column($lines, 'debit'));
            $totalCredit = array_sum(array_column($lines, 'credit'));

            $journalNumber = $this->journals->nextJournalNumber($tenantId);

            return $this->journals->createWithEntries([
                'tenant_id' => $tenantId,
                'accounting_period_id' => $period->id,
                'journal_number' => $journalNumber,
                'journal_date' => $journalDate,
                'source' => $meta['source'] ?? Journal::SOURCE_MANUAL,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'memo' => $meta['memo'] ?? null,
                'status' => Journal::STATUS_POSTED,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'posted_by' => $meta['posted_by'] ?? null,
                'posted_at' => now(),
            ], $lines);
        });
    }

    /**
     * Create the mirror-image journal that cancels out an already-posted one,
     * rather than mutating or deleting it — journals are an immutable audit trail.
     */
    public function reverse(int $journalId, ?string $reason = null, ?int $postedBy = null): Journal
    {
        return DB::transaction(function () use ($journalId, $reason, $postedBy) {
            $original = $this->journals->findWithEntries($journalId);

            if ($original === null) {
                throw new InvalidArgumentException('Journal not found.');
            }

            if ($original->status !== Journal::STATUS_POSTED) {
                throw new InvalidArgumentException("Only posted journals can be reversed; journal is {$original->status}.");
            }

            $reversalLines = $original->entries->map(fn ($entry) => [
                'chart_of_account_id' => $entry->chart_of_account_id,
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'description' => $reason ?? "Reversal of {$original->journal_number}",
            ])->all();

            $reversal = $this->post($reversalLines, [
                'tenant_id' => $original->tenant_id,
                'journal_date' => now(),
                'source' => $original->source,
                'reference_type' => $original->reference_type,
                'reference_id' => $original->reference_id,
                'memo' => $reason ?? "Reversal of {$original->journal_number}",
                'posted_by' => $postedBy,
            ]);

            $original->update([
                'status' => Journal::STATUS_REVERSED,
                'reversed_journal_id' => $reversal->id,
            ]);

            return $reversal;
        });
    }

    public function findByReference(string $referenceType, int $referenceId): Collection
    {
        return $this->journals->findByReference($referenceType, $referenceId);
    }

    public function find(int $id): ?Journal
    {
        return $this->journals->findWithEntries($id);
    }

    public function paginate(array $filters = [], int $perPage = 15)
    {
        return $this->journals->paginateAll($filters, $perPage);
    }

    /**
     * @param array<int, array{chart_of_account_id: int, debit?: float, credit?: float}> $lines
     */
    private function assertLinesAreBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal requires at least two lines.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            if (empty($line['chart_of_account_id'])) {
                throw new InvalidArgumentException('Every journal line requires a chart_of_account_id.');
            }

            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Journal line amounts cannot be negative.');
            }

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('A journal line cannot have both a debit and a credit amount.');
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw new InvalidArgumentException('A journal line must have either a debit or a credit amount.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException(
                "Journal is not balanced: total debit {$totalDebit} does not equal total credit {$totalCredit}."
            );
        }
    }
}
