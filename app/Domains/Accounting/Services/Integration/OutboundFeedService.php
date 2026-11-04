<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LedgerGroup;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Support\PartyDirectory;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * What this ERP has that a connection hasn't received yet.
 *
 * Reversed journals and their reversals both go out, because both count in this
 * ledger and the other side's trial balance has to agree. Year-end closing and
 * opening-balance journals stay home: the other product closes its own years and
 * keeps openings on the ledger. Anything that came in through this connection
 * never goes back to it.
 */
class OutboundFeedService
{
    public function __construct(
        private readonly IntegrationRefRepositoryInterface $refs,
        private readonly BillAllocationService $bills,
        private readonly PartyDirectory $parties,
    ) {
    }

    /** @return Collection<int, Journal> */
    public function pendingJournals(IntegrationConnection $connection, int $limit = 100): Collection
    {
        return Journal::query()
            ->withoutGlobalScope('company')
            ->with(['entries', 'voucherDetail'])
            ->where('tenant_id', $connection->tenant_id)
            ->whereIn('status', Journal::LEDGER_STATUSES)
            ->where('source', '!=', Journal::SOURCE_YEAR_END_CLOSE)
            ->where(fn ($q) => $q->whereNull('reference_type')->orWhere('reference_type', 'not like', '%opening_balance%'))
            ->when($connection->company_id, fn ($q, $id) => $q->where('company_id', $id))
            ->when($connection->setting('export_from'), fn ($q, $from) => $q->whereDate('journal_date', '>=', $from))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('accounting_integration_refs as sent')
                ->whereColumn('sent.local_id', 'journals.id')
                ->where('sent.connection_id', $connection->id)
                ->where('sent.entity_type', IntegrationRef::ENTITY_VOUCHER)
                ->where('sent.local_type', IntegrationRef::LOCAL_JOURNAL))
            // Loop guard: the reversal of an imported journal carries the same reference.
            // Spelled out because most journals have a NULL reference_type, and a
            // plain NOT (type = x AND ...) would be NULL for them and drop them.
            ->where(fn ($q) => $q
                ->whereNull('reference_type')
                ->orWhere('reference_type', '!=', IntegrationRef::JOURNAL_REFERENCE_TYPE)
                ->orWhereNotIn('reference_id', IntegrationRef::query()
                    ->where('connection_id', $connection->id)
                    ->where('entity_type', IntegrationRef::ENTITY_VOUCHER)
                    ->select('id')))
            ->orderBy('journal_date')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, mixed> */
    public function canonicalVoucher(IntegrationConnection $connection, Journal $journal): array
    {
        return [
            'local_id' => $journal->id,
            'voucher_type' => $journal->voucher_type ?? 'journal',
            'date' => $journal->journal_date->toDateString(),
            'number' => $journal->journal_number,
            'narration' => $journal->memo,
            'status' => $journal->status,
            'source' => $journal->source,
            'is_reversal_of' => Journal::query()->withoutGlobalScope('company')->where('reversed_journal_id', $journal->id)->value('id'),
            'lines' => $journal->entries->map(function (JournalEntry $entry) use ($connection, $journal) {
                $ledger = $this->ledgerForEntry($connection, $entry, (int) $journal->tenant_id);

                return [
                    'ledger_name' => $ledger['name'],
                    'local_type' => $ledger['local_type'],
                    'local_id' => $ledger['local_id'],
                    'debit' => round((float) $entry->debit, 2),
                    'credit' => round((float) $entry->credit, 2),
                    'description' => $entry->description,
                ];
            })->values()->all(),
        ];
    }

    /**
     * The ledger a line belongs to on the other side. In this ERP a party's line
     * sits on a control account with a party id; products like Tally give each
     * party its own ledger.
     *
     * @return array{name: string, local_type: string, local_id: int, party_type: ?string, account: ?ChartOfAccount}
     */
    public function ledgerForEntry(IntegrationConnection $connection, JournalEntry $entry, int $tenantId): array
    {
        $controls = $this->bills->controlAccounts($tenantId);

        if ($entry->party_type !== null && $entry->party_id !== null && isset($controls[$entry->chart_of_account_id])) {
            $ref = $this->refs->forLocal($connection, IntegrationRef::ENTITY_LEDGER, $entry->party_type, (int) $entry->party_id);

            return [
                'name' => $ref?->external_name ?? $this->parties->name($entry->party_type, (int) $entry->party_id),
                'local_type' => $entry->party_type,
                'local_id' => (int) $entry->party_id,
                'party_type' => $entry->party_type,
                'account' => null,
            ];
        }

        $account = ChartOfAccount::query()->withoutGlobalScope('company')->findOrFail($entry->chart_of_account_id);
        $ref = $this->refs->forLocal($connection, IntegrationRef::ENTITY_LEDGER, IntegrationRef::LOCAL_ACCOUNT, (int) $account->id);

        return [
            'name' => $ref?->external_name ?? $account->name,
            'local_type' => IntegrationRef::LOCAL_ACCOUNT,
            'local_id' => (int) $account->id,
            'party_type' => null,
            'account' => $account,
        ];
    }

    /** @return array<int, array<string, mixed>> accounts the connection has no ref for */
    public function pendingLedgers(IntegrationConnection $connection, int $limit = 100): array
    {
        return ChartOfAccount::query()
            ->withoutGlobalScope('company')
            ->with('ledgerGroup')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('accounting_integration_refs as sent')
                ->whereColumn('sent.local_id', 'chart_of_accounts.id')
                ->where('sent.connection_id', $connection->id)
                ->where('sent.entity_type', IntegrationRef::ENTITY_LEDGER)
                ->where('sent.local_type', IntegrationRef::LOCAL_ACCOUNT))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (ChartOfAccount $account) => [
                'local_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'group_name' => $account->ledgerGroup?->name,
                'is_cash_or_bank' => (bool) $account->is_cash_or_bank,
                'is_active' => (bool) $account->is_active,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function pendingGroups(IntegrationConnection $connection, int $limit = 100): array
    {
        return LedgerGroup::query()
            ->withoutGlobalScope('company')
            ->with('parent')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('accounting_integration_refs as sent')
                ->whereColumn('sent.local_id', 'ledger_groups.id')
                ->where('sent.connection_id', $connection->id)
                ->where('sent.entity_type', IntegrationRef::ENTITY_GROUP))
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (LedgerGroup $group) => [
                'local_id' => $group->id,
                'code' => $group->code,
                'name' => $group->name,
                'nature' => $group->nature,
                'parent_name' => $group->parent?->name,
            ])
            ->all();
    }

    /**
     * Client confirms it stored these. Without an ack a record stays in the feed,
     * so a crash on the client's side only repeats, it never loses anything.
     *
     * @param array<int, array{local_id: int, external_id?: string, external_name?: string}> $items
     */
    public function acknowledge(IntegrationConnection $connection, string $entityType, array $items): int
    {
        $localType = match ($entityType) {
            IntegrationRef::ENTITY_VOUCHER => IntegrationRef::LOCAL_JOURNAL,
            IntegrationRef::ENTITY_LEDGER => IntegrationRef::LOCAL_ACCOUNT,
            IntegrationRef::ENTITY_GROUP => IntegrationRef::LOCAL_GROUP,
            default => throw new InvalidArgumentException("Unknown entity type: {$entityType}"),
        };

        $count = 0;
        foreach ($items as $item) {
            $localId = (int) $item['local_id'];
            $local = match ($localType) {
                IntegrationRef::LOCAL_JOURNAL => Journal::query()->withoutGlobalScope('company')->find($localId),
                IntegrationRef::LOCAL_ACCOUNT => ChartOfAccount::query()->withoutGlobalScope('company')->find($localId),
                default => LedgerGroup::query()->withoutGlobalScope('company')->find($localId),
            };

            if ($local === null) {
                throw new InvalidArgumentException("No {$entityType} with id {$localId} in this workspace.");
            }

            IntegrationRef::query()->firstOrCreate(
                ['connection_id' => $connection->id, 'entity_type' => $entityType, 'external_id' => (string) ($item['external_id'] ?? "erp-{$entityType}-{$localId}")],
                [
                    'tenant_id' => $connection->tenant_id,
                    'direction' => IntegrationRef::DIRECTION_OUTBOUND,
                    'external_name' => $item['external_name'] ?? ($local->name ?? null),
                    'external_number' => $local->journal_number ?? null,
                    'external_date' => $local->journal_date ?? null,
                    'local_type' => $localType,
                    'local_id' => $localId,
                    'status' => IntegrationRef::STATUS_SYNCED,
                    'last_synced_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }
}
