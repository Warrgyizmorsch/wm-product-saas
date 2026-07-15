<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\Journal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface JournalRepositoryInterface
{
    public function paginateAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Journal;

    public function findWithEntries(int $id): ?Journal;

    public function findByReference(string $referenceType, int $referenceId): Collection;

    public function nextJournalNumber(int $tenantId): string;

    public function create(array $data): Journal;

    public function createWithEntries(array $journalData, array $entryLines): Journal;

    /**
     * One row per account with summed debit/credit across every posted
     * journal in the given period, for a Trial Balance report.
     *
     * @return Collection<int, object{chart_of_account_id: int, debit: float, credit: float}>
     */
    public function trialBalance(int $periodId): Collection;

    /**
     * Every posted JournalEntry for one account within one period, for a
     * General Ledger report. Ordering by journal_date is done by the caller
     * since sorting a small in-memory Collection is simpler than a raw join.
     *
     * @return Collection<int, \App\Domains\Accounting\Models\JournalEntry>
     */
    public function ledgerEntries(int $chartOfAccountId, int $periodId): Collection;

    /**
     * Sum of all posted movements for an account strictly before a date,
     * used as the General Ledger's carried-forward opening balance.
     *
     * @return array{debit: float, credit: float}
     */
    public function openingBalance(int $chartOfAccountId, \DateTimeInterface $before): array;
}
