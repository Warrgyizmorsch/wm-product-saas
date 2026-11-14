<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRecord;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\VoucherService;
use App\Domains\Accounting\Support\VoucherType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Posts incoming vouchers exactly once. The ref row is locked for the whole
 * decision, and the journal's unique idempotency key backs that up. Journals
 * are immutable, so a changed voucher is reversed on its own date and posted
 * again. Anything that prevents that becomes a conflict.
 *
 * Vouchers go through maker-checker via submit(), so a tenant's approval rules
 * apply to imports exactly as they do to manual entries.
 */
class VoucherIngestor
{
    private const JOURNAL = 'journal';

    public function __construct(
        private readonly JournalService $journals,
        private readonly VoucherService $vouchers,
        private readonly ConflictRecorder $conflicts,
        private readonly IntegrationRefRepositoryInterface $refs,
    ) {
    }

    public function ingest(IntegrationConnection $connection, array $item, ?int $userId, bool $force = false): array
    {
        $externalId = mb_substr(trim((string) ($item['external_id'] ?? '')), 0, 191);
        if ($externalId === '') {
            throw new IntegrationRecordException(IntegrationRecordException::INVALID, 'external_id is required. Without it a re-sync would duplicate the voucher.');
        }

        if (! empty($item['is_cancelled'])) {
            return $this->cancel($connection, $externalId, $item, $userId);
        }

        $voucher = $this->normalize($connection, $item);

        return DB::transaction(function () use ($connection, $externalId, $item, $voucher, $userId, $force) {
            $ref = $this->refs->lockOrCreate($connection, IntegrationRef::ENTITY_VOUCHER, $externalId, IntegrationRef::DIRECTION_INBOUND);
            $journal = $this->currentJournal($ref);

            if ($journal === null) {
                return $this->post($connection, $ref, $voucher, $userId);
            }

            if ($this->conflicts->keptLocal($connection, IntegrationRef::ENTITY_VOUCHER, $externalId, $voucher['hash'])) {
                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            $changed = $ref->payload_hash !== $voucher['hash'];

            return match ($journal->status) {
                Journal::STATUS_POSTED => $changed
                    ? $this->replace($connection, $ref, $journal, $voucher, $item, $userId)
                    : $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref),

                Journal::STATUS_PENDING_APPROVAL => $changed
                    ? $this->conflict($connection, $ref, IntegrationConflict::REASON_AWAITING_APPROVAL,
                        "{$journal->journal_number} is waiting for approval and the source has changed. Approve or reject it, then sync again.", $item, $voucher)
                    : $this->result(IntegrationRecord::OUTCOME_PENDING_APPROVAL, $ref),

                Journal::STATUS_REVERSED => $force
                    ? $this->post($connection, $ref, $voucher, $userId)
                    : $this->conflict($connection, $ref, IntegrationConflict::REASON_REVERSED_LOCALLY,
                        "{$journal->journal_number} was reversed here, but the voucher still exists in the source.", $item, $voucher),

                Journal::STATUS_REJECTED => $force
                    ? $this->repostRejected($connection, $ref, $journal, $voucher, $userId)
                    : $this->conflict($connection, $ref, IntegrationConflict::REASON_REJECTED_LOCALLY,
                        "{$journal->journal_number} was rejected by an approver, but the voucher still exists in the source.", $item, $voucher),

                default => throw new InvalidArgumentException("Journal {$journal->journal_number} is {$journal->status}."),
            };
        });
    }

    private function cancel(IntegrationConnection $connection, string $externalId, array $item, ?int $userId): array
    {
        return DB::transaction(function () use ($connection, $externalId, $item, $userId) {
            $ref = $this->refs->lockOrCreate($connection, IntegrationRef::ENTITY_VOUCHER, $externalId, IntegrationRef::DIRECTION_INBOUND);
            $journal = $this->currentJournal($ref);

            if ($journal === null || $journal->status !== Journal::STATUS_POSTED) {
                if ($journal?->status === Journal::STATUS_PENDING_APPROVAL) {
                    return $this->conflict($connection, $ref, IntegrationConflict::REASON_AWAITING_APPROVAL,
                        "{$journal->journal_number} is waiting for approval and was cancelled in the source. Reject it here.", $item, ['hash' => null]);
                }

                $ref->update(['status' => IntegrationRef::STATUS_SYNCED, 'last_synced_at' => now()]);

                return $this->result(IntegrationRecord::OUTCOME_UNCHANGED, $ref);
            }

            try {
                $this->journals->reverseOnOriginalDate($journal->id, 'Cancelled in '.$connection->name, $userId);
            } catch (InvalidArgumentException $e) {
                return $this->conflict($connection, $ref, IntegrationConflict::REASON_CANNOT_REVERSE, $e->getMessage(), $item, ['hash' => null]);
            }

            $ref->update(['status' => IntegrationRef::STATUS_SYNCED, 'last_synced_at' => now()]);

            return $this->result(IntegrationRecord::OUTCOME_UPDATED, $ref);
        });
    }

    private function replace(IntegrationConnection $connection, IntegrationRef $ref, Journal $journal, array $voucher, array $item, ?int $userId): array
    {
        try {
            return DB::transaction(function () use ($connection, $ref, $journal, $voucher, $userId) {
                $this->journals->reverseOnOriginalDate($journal->id, "Changed in {$connection->name}", $userId);

                $result = $this->post($connection, $ref, $voucher, $userId);

                return $result['outcome'] === IntegrationRecord::OUTCOME_CREATED
                    ? ['outcome' => IntegrationRecord::OUTCOME_UPDATED] + $result
                    : $result;
            });
        } catch (InvalidArgumentException $e) {
            return $this->conflict($connection, $ref, IntegrationConflict::REASON_CANNOT_REVERSE,
                "Couldn't replace {$journal->journal_number}: {$e->getMessage()}", $item, $voucher);
        }
    }

    /** A rejected journal keeps its idempotency key, so release it before posting anew. */
    private function repostRejected(IntegrationConnection $connection, IntegrationRef $ref, Journal $journal, array $voucher, ?int $userId): array
    {
        $journal->update(['idempotency_key' => null]);

        return $this->post($connection, $ref, $voucher, $userId);
    }

    private function post(IntegrationConnection $connection, IntegrationRef $ref, array $voucher, ?int $userId): array
    {
        $meta = [
            'tenant_id' => (int) $connection->tenant_id,
            'company_id' => $connection->company_id,
            'branch_id' => $connection->branch_id,
            'journal_date' => $voucher['date'],
            'source' => Journal::SOURCE_INTEGRATION,
            'reference_type' => IntegrationRef::JOURNAL_REFERENCE_TYPE,
            'reference_id' => $ref->id,
            'idempotency_key' => JournalService::idempotencyKey(IntegrationRef::JOURNAL_REFERENCE_TYPE, $ref->id),
            'memo' => $voucher['narration'] ?? ($voucher['number'] ? "{$connection->name} {$voucher['number']}" : null),
            'posted_by' => $userId,
        ];

        $journal = $voucher['type'] === self::JOURNAL
            ? $this->journals->submit($voucher['lines'], $meta)
            : $this->vouchers->post($voucher['type'], $voucher['lines'], $meta + [
                'through_approval' => true,
                'party_name' => $voucher['party_name'],
                'reference_no' => $voucher['number'],
            ]);

        $ref->update([
            'external_number' => $voucher['number'],
            'external_date' => $voucher['date'],
            'local_type' => IntegrationRef::LOCAL_JOURNAL,
            'local_id' => $journal->id,
            'payload_hash' => $voucher['hash'],
            'status' => IntegrationRef::STATUS_SYNCED,
            'last_error' => null,
            'last_synced_at' => now(),
        ]);

        return $this->result(
            $journal->status === Journal::STATUS_PENDING_APPROVAL ? IntegrationRecord::OUTCOME_PENDING_APPROVAL : IntegrationRecord::OUTCOME_CREATED,
            $ref,
        );
    }

    /** @return array{type: string, date: string, number: ?string, narration: ?string, party_name: ?string, lines: array, hash: string} */
    private function normalize(IntegrationConnection $connection, array $item): array
    {
        $type = $item['voucher_type'] ?? null;
        if ($type !== self::JOURNAL && ! VoucherType::isValid((string) $type)) {
            $name = $item['source_type_name'] ?? $type ?? 'unknown';
            throw new IntegrationRecordException(
                IntegrationRecordException::UNMAPPED_VOUCHER_TYPE,
                "Voucher type '{$name}' isn't mapped. Add it to the connection's voucher_type_map."
            );
        }

        try {
            $date = Carbon::parse((string) ($item['date'] ?? ''))->toDateString();
            if (empty($item['date'])) {
                throw new InvalidArgumentException();
            }
        } catch (\Throwable) {
            throw new IntegrationRecordException(IntegrationRecordException::INVALID, 'A valid voucher date is required.');
        }

        $rawLines = $item['lines'] ?? [];
        if (! is_array($rawLines) || count($rawLines) < 2) {
            throw new IntegrationRecordException(IntegrationRecordException::INVALID, 'A voucher needs at least two lines.');
        }

        $lines = [];
        $debitMinor = 0;
        $creditMinor = 0;

        foreach ($rawLines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            $debitMinor += (int) round($debit * 100);
            $creditMinor += (int) round($credit * 100);

            $lines[] = [
                'chart_of_account_id' => $this->resolveAccount($connection, (array) $line),
                'debit' => $debit,
                'credit' => $credit,
                'description' => isset($line['description']) ? mb_substr((string) $line['description'], 0, 255) : null,
            ];
        }

        if ($debitMinor !== $creditMinor) {
            throw new IntegrationRecordException(
                IntegrationRecordException::UNBALANCED,
                sprintf('Voucher is not balanced: debit %.2f, credit %.2f.', $debitMinor / 100, $creditMinor / 100)
            );
        }

        // Hashed on resolved accounts, so renaming a ledger in the source doesn't
        // look like a changed voucher. Narration and number are left out: editing
        // them shouldn't reverse and repost an entry.
        $fingerprint = array_map(fn ($l) => [$l['chart_of_account_id'], $l['debit'], $l['credit']], $lines);
        sort($fingerprint);

        return [
            'type' => $type,
            'date' => $date,
            'number' => isset($item['number']) ? mb_substr((string) $item['number'], 0, 191) : null,
            'narration' => $item['narration'] ?? null,
            'party_name' => isset($item['party_name']) ? mb_substr((string) $item['party_name'], 0, 255) : null,
            'lines' => $lines,
            'hash' => hash('sha256', json_encode([$type, $date, $fingerprint])),
        ];
    }

    private function resolveAccount(IntegrationConnection $connection, array $line): int
    {
        $ref = null;
        if (! empty($line['ledger_external_id'])) {
            $ref = $this->refs->ledgerByExternalId($connection, (string) $line['ledger_external_id']);
        } elseif (! empty($line['ledger_name'])) {
            $ref = $this->refs->ledgerByName($connection, (string) $line['ledger_name']);
        } elseif (! empty($line['account_code'])) {
            $account = ChartOfAccount::query()->withoutGlobalScope('company')->where('code', (string) $line['account_code'])->first();
            if ($account !== null) {
                return $account->id;
            }
        }

        if ($ref === null || $ref->local_type !== IntegrationRef::LOCAL_ACCOUNT) {
            $label = $line['ledger_name'] ?? $line['ledger_external_id'] ?? $line['account_code'] ?? 'unnamed';
            throw new IntegrationRecordException(
                IntegrationRecordException::UNMAPPED_LEDGER,
                "Ledger '{$label}' isn't mapped to an account here. Sync ledgers or add a mapping, then retry."
            );
        }

        return (int) $ref->local_id;
    }

    private function currentJournal(IntegrationRef $ref): ?Journal
    {
        return $ref->local_id
            ? Journal::query()->withoutGlobalScope('company')->find($ref->local_id)
            : null;
    }

    private function conflict(IntegrationConnection $connection, IntegrationRef $ref, string $reason, string $detail, array $item, array $voucher): array
    {
        return $this->conflicts->record($connection, IntegrationRef::ENTITY_VOUCHER, $ref->external_id, $reason, $detail, $item, $voucher['hash'] ?? null, $ref);
    }

    private function result(string $outcome, IntegrationRef $ref): array
    {
        return ['outcome' => $outcome, 'local_type' => $ref->local_type, 'local_id' => $ref->local_id];
    }
}
