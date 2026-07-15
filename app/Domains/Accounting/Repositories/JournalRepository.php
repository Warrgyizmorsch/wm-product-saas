<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class JournalRepository implements JournalRepositoryInterface
{
    public function paginateAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Journal::query()->with('period');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('journal_number', 'like', "%{$search}%")
                  ->orWhere('memo', 'like', "%{$search}%");
            });
        }

        $sortable = ['journal_number', 'journal_date', 'status', 'total_debit', 'total_credit'];
        $sort = in_array($filters['sort'] ?? null, $sortable, true) ? $filters['sort'] : 'journal_date';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): ?Journal
    {
        return Journal::find($id);
    }

    public function findWithEntries(int $id): ?Journal
    {
        return Journal::with(['entries.account'])->find($id);
    }

    public function findByReference(string $referenceType, int $referenceId): Collection
    {
        return Journal::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->get();
    }

    /**
     * Must be called inside the same DB transaction that will insert the journal,
     * so the count-based sequence and the unique (tenant_id, journal_number)
     * constraint stay consistent under concurrent posting.
     */
    public function nextJournalNumber(int $tenantId): string
    {
        $yearMonth = now()->format('Ym');

        $count = Journal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('journal_number', 'like', "JNL-{$yearMonth}-%")
            ->lockForUpdate()
            ->count();

        $sequence = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);

        return "JNL-{$yearMonth}-{$sequence}";
    }

    public function create(array $data): Journal
    {
        return Journal::create($data);
    }

    public function createWithEntries(array $journalData, array $entryLines): Journal
    {
        $journal = Journal::create($journalData);

        foreach ($entryLines as $line) {
            $journal->entries()->create([
                'tenant_id' => $journal->tenant_id,
                'chart_of_account_id' => $line['chart_of_account_id'],
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'description' => $line['description'] ?? null,
            ]);
        }

        return $journal->load(['entries.account']);
    }

    public function trialBalance(int $periodId): Collection
    {
        return JournalEntry::query()
            ->select('chart_of_account_id')
            ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')
            ->whereHas('journal', fn ($q) => $q->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])->where('accounting_period_id', $periodId))
            ->groupBy('chart_of_account_id')
            ->with('account')
            ->get();
    }

    public function ledgerEntries(int $chartOfAccountId, int $periodId): Collection
    {
        return JournalEntry::query()
            ->where('chart_of_account_id', $chartOfAccountId)
            ->whereHas('journal', fn ($q) => $q->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])->where('accounting_period_id', $periodId))
            ->with('journal')
            ->get()
            ->sortBy(fn ($entry) => $entry->journal->journal_date)
            ->values();
    }

    public function openingBalance(int $chartOfAccountId, \DateTimeInterface $before): array
    {
        $totals = JournalEntry::query()
            ->where('chart_of_account_id', $chartOfAccountId)
            ->whereHas('journal', fn ($q) => $q->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])->where('journal_date', '<', $before))
            ->selectRaw('SUM(debit) as debit, SUM(credit) as credit')
            ->first();

        return [
            'debit' => (float) ($totals->debit ?? 0),
            'credit' => (float) ($totals->credit ?? 0),
        ];
    }
}
