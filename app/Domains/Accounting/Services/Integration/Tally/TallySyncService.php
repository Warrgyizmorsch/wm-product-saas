<?php

namespace App\Domains\Accounting\Services\Integration\Tally;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationAgentJob;
use App\Domains\Accounting\Models\IntegrationBatch;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRecord;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\Integration\ConflictRecorder;
use App\Domains\Accounting\Services\Integration\IntegrationIngestService;
use App\Domains\Accounting\Services\Integration\IntegrationRefRepositoryInterface;
use App\Domains\Accounting\Services\Integration\OutboundFeedService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tally's interface has no documented authentication or TLS and sits on the
 * customer's own machine, so the ERP never calls it. It queues XML jobs, and a
 * small relay on the customer's network fetches them over HTTPS, hands them to
 * Tally and returns the raw reply. All mapping logic stays here on the server.
 *
 * Tally reports only totals per request, so each voucher is pushed in its own job.
 */
class TallySyncService
{
    public const MAX_ATTEMPTS = 3;
    private const LEASE_MINUTES = 5;
    private const MAX_PUSH_VOUCHERS = 200;

    private const TALLY_TYPES = [
        'payment' => 'Payment',
        'receipt' => 'Receipt',
        'contra' => 'Contra',
        'journal' => 'Journal',
        'sales' => 'Sales',
        'purchase' => 'Purchase',
        'credit_note' => 'Credit Note',
        'debit_note' => 'Debit Note',
    ];

    public function __construct(
        private readonly TallyXmlBuilder $xml,
        private readonly TallyXmlParser $parser,
        private readonly IntegrationIngestService $ingest,
        private readonly OutboundFeedService $outbound,
        private readonly IntegrationRefRepositoryInterface $refs,
        private readonly ConflictRecorder $conflicts,
    ) {
    }

    /**
     * Queues a sync cycle: pull groups, ledgers and vouchers, then push what's
     * pending. Does nothing while the previous cycle still has work open.
     *
     * @param array{direction?: string, from?: ?string, to?: ?string} $options
     * @return int jobs queued
     */
    public function plan(IntegrationConnection $connection, array $options, ?int $userId): int
    {
        $this->assertTally($connection);

        if (! $connection->isActive()) {
            throw new InvalidArgumentException('This connection is paused.');
        }

        if ($this->openJobs($connection)->exists()) {
            return 0;
        }

        $direction = $options['direction'] ?? $connection->setting('direction', 'both');
        $queued = 0;

        if ($direction !== 'outbound') {
            $queued += $this->planPull($connection, $options);
        }

        if ($direction !== 'inbound') {
            $queued += $this->planPush($connection, $userId);
        }

        $connection->update(['last_planned_at' => now()]);

        return $queued;
    }

    private function planPull(IntegrationConnection $connection, array $options): int
    {
        $this->queue($connection, IntegrationAgentJob::KIND_PULL_GROUPS, [], $this->xml->pullGroups($connection));
        $this->queue($connection, IntegrationAgentJob::KIND_PULL_LEDGERS, [], $this->xml->pullLedgers($connection));
        $queued = 2;

        $to = Carbon::parse($options['to'] ?? now()->toDateString());
        $from = Carbon::parse($options['from'] ?? $this->defaultFrom($connection));

        // A month per job keeps replies a sensible size. Later cycles re-read
        // recent weeks only, which is how edits and backdated entries get noticed.
        for ($start = $from->copy(); $start->lte($to); $start = $start->copy()->addMonthNoOverflow()->startOfMonth()) {
            $end = $start->copy()->endOfMonth()->min($to);
            $this->queue($connection, IntegrationAgentJob::KIND_PULL_VOUCHERS, [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ], $this->xml->pullVouchers($connection, $start->toDateString(), $end->toDateString()));
            $queued++;
        }

        return $queued;
    }

    private function defaultFrom(IntegrationConnection $connection): string
    {
        if ($connection->first_inbound_completed_at !== null) {
            return now()->subDays((int) $connection->setting('rescan_days', 45))->toDateString();
        }

        return (string) $connection->setting('sync_from', now()->subDays(30)->toDateString());
    }

    private function planPush(IntegrationConnection $connection, ?int $userId): int
    {
        $journals = $this->outbound->pendingJournals($connection, self::MAX_PUSH_VOUCHERS);
        if ($journals->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($connection, $journals, $userId) {
            $queued = $this->planLedgers($connection, $journals, $userId);
            $batch = $this->outboundBatch($connection, IntegrationRef::ENTITY_VOUCHER, $userId, $journals->count());

            foreach ($journals as $journal) {
                $remoteId = $this->remoteId($connection, $journal);
                $voucher = $this->tallyVoucher($connection, $journal, $remoteId);

                $ref = IntegrationRef::create([
                    'tenant_id' => $connection->tenant_id,
                    'connection_id' => $connection->id,
                    'direction' => IntegrationRef::DIRECTION_OUTBOUND,
                    'entity_type' => IntegrationRef::ENTITY_VOUCHER,
                    'external_id' => $remoteId,
                    'external_number' => $journal->journal_number,
                    'external_date' => $journal->journal_date,
                    'local_type' => IntegrationRef::LOCAL_JOURNAL,
                    'local_id' => $journal->id,
                    'status' => IntegrationRef::STATUS_PENDING,
                ]);

                $this->queue($connection, IntegrationAgentJob::KIND_PUSH_VOUCHER, ['ref_id' => $ref->id], $this->xml->importVouchers($connection, [$voucher]), $batch->id);
                $queued++;
            }

            return $queued;
        });
    }

    /** Creates, in Tally, any ledger these journals use that it doesn't have yet. */
    private function planLedgers(IntegrationConnection $connection, Collection $journals, ?int $userId): int
    {
        $missing = $this->missingLedgers($connection, $journals);
        if ($missing === []) {
            return 0;
        }

        $batch = $this->outboundBatch($connection, IntegrationRef::ENTITY_LEDGER, $userId, count($missing));

        foreach ($missing as $ledger) {
            $ref = $this->createLedgerRef($connection, $ledger);
            $this->queue($connection, IntegrationAgentJob::KIND_PUSH_LEDGER, ['ref_id' => $ref->id], $this->xml->importLedgers($connection, [[
                'name' => $ledger['name'],
                'parent' => $ledger['parent'],
            ]]), $batch->id);
        }

        return count($missing);
    }

    /** @return array<string, array{name: string, parent: string, local_type: string, local_id: int}> */
    private function missingLedgers(IntegrationConnection $connection, Collection $journals): array
    {
        $missing = [];

        foreach ($journals as $journal) {
            foreach ($journal->entries as $entry) {
                $ledger = $this->outbound->ledgerForEntry($connection, $entry, (int) $journal->tenant_id);
                $key = $ledger['local_type'].':'.$ledger['local_id'];

                if (isset($missing[$key])
                    || $this->refs->forLocal($connection, IntegrationRef::ENTITY_LEDGER, $ledger['local_type'], $ledger['local_id']) !== null
                    || $this->refs->ledgerByName($connection, $ledger['name']) !== null) {
                    continue;
                }

                $missing[$key] = [
                    'name' => $ledger['name'],
                    'parent' => TallyGroupMap::parentFor($ledger['account'], $ledger['party_type']),
                    'local_type' => $ledger['local_type'],
                    'local_id' => $ledger['local_id'],
                ];
            }
        }

        return $missing;
    }

    private function createLedgerRef(IntegrationConnection $connection, array $ledger, string $status = IntegrationRef::STATUS_PENDING): IntegrationRef
    {
        return IntegrationRef::create([
            'tenant_id' => $connection->tenant_id,
            'connection_id' => $connection->id,
            'direction' => IntegrationRef::DIRECTION_OUTBOUND,
            'entity_type' => IntegrationRef::ENTITY_LEDGER,
            'external_id' => 'erp-ledger-'.$ledger['local_type'].'-'.$ledger['local_id'],
            'external_name' => $ledger['name'],
            'local_type' => $ledger['local_type'],
            'local_id' => $ledger['local_id'],
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    private function tallyVoucher(IntegrationConnection $connection, Journal $journal, string $remoteId): array
    {
        $canonical = $this->outbound->canonicalVoucher($connection, $journal);

        return [
            'remote_id' => $remoteId,
            'tally_type' => self::TALLY_TYPES[$canonical['voucher_type']] ?? 'Journal',
            'date' => $canonical['date'],
            'number' => $canonical['number'],
            'narration' => $canonical['narration'],
            'lines' => $canonical['lines'],
        ];
    }

    private function remoteId(IntegrationConnection $connection, Journal $journal): string
    {
        return 'erp-'.$connection->tenant_id.'-'.$journal->id;
    }

    /**
     * The relay's call: records that it is alive, starts a new cycle when one is
     * due, and leases out the next jobs in order.
     *
     * @return Collection<int, IntegrationAgentJob>
     */
    public function poll(IntegrationConnection $connection, int $limit, ?string $agentVersion, ?int $userId): Collection
    {
        $this->assertTally($connection);
        $connection->update(['last_agent_seen_at' => now(), 'agent_version' => $agentVersion]);

        if (! $connection->isActive()) {
            return collect();
        }

        $this->releaseExpiredLeases($connection);

        $interval = (int) $connection->setting('auto_sync_minutes', 15);
        if ($interval > 0 && ($connection->last_planned_at === null || $connection->last_planned_at->lte(now()->subMinutes($interval)))) {
            $this->plan($connection, [], $userId);
        }

        return DB::transaction(function () use ($connection, $limit) {
            $jobs = IntegrationAgentJob::query()
                ->where('connection_id', $connection->id)
                ->where('status', IntegrationAgentJob::STATUS_QUEUED)
                ->orderBy('id')
                ->limit(min($limit, 50))
                ->lockForUpdate()
                ->get();

            foreach ($jobs as $job) {
                $job->update([
                    'status' => IntegrationAgentJob::STATUS_LEASED,
                    'attempts' => $job->attempts + 1,
                    'leased_until' => now()->addMinutes(self::LEASE_MINUTES),
                ]);
            }

            return $jobs;
        });
    }

    /**
     * What the relay saw. $body is Tally's raw reply; $transportError means Tally
     * couldn't be reached, so nothing is known to have happened yet.
     *
     * @return array{status: string, detail: ?string}
     */
    public function complete(IntegrationAgentJob $job, ?string $body, ?string $transportError, ?int $userId): array
    {
        if ($job->status !== IntegrationAgentJob::STATUS_LEASED) {
            return ['status' => $job->status, 'detail' => 'Already processed.'];
        }

        if ($transportError !== null || $body === null) {
            return $this->transportFailure($job, $transportError ?? 'The relay sent no response from Tally.');
        }

        try {
            return match ($job->kind) {
                IntegrationAgentJob::KIND_PULL_GROUPS,
                IntegrationAgentJob::KIND_PULL_LEDGERS,
                IntegrationAgentJob::KIND_PULL_VOUCHERS => $this->completePull($job, $body, $userId),
                default => $this->completePush($job, $body),
            };
        } catch (InvalidArgumentException $e) {
            return $this->transportFailure($job, $e->getMessage());
        }
    }

    private function transportFailure(IntegrationAgentJob $job, string $error): array
    {
        if ($job->attempts < self::MAX_ATTEMPTS) {
            $job->update(['status' => IntegrationAgentJob::STATUS_QUEUED, 'leased_until' => null, 'last_error' => $error]);

            return ['status' => IntegrationAgentJob::STATUS_QUEUED, 'detail' => $error];
        }

        return $this->failJob($job, $error);
    }

    private function failJob(IntegrationAgentJob $job, string $error): array
    {
        $job->update(['status' => IntegrationAgentJob::STATUS_FAILED, 'leased_until' => null, 'last_error' => $error, 'completed_at' => now()]);

        if ($ref = $this->jobRef($job)) {
            $ref->update(['status' => IntegrationRef::STATUS_FAILED, 'last_error' => $error]);
            $this->recordPush($job, $ref, IntegrationRecord::OUTCOME_FAILED, $error);
        }

        return ['status' => IntegrationAgentJob::STATUS_FAILED, 'detail' => $error];
    }

    private function completePull(IntegrationAgentJob $job, string $body, ?int $userId): array
    {
        $connection = $job->connection;

        $batch = match ($job->kind) {
            IntegrationAgentJob::KIND_PULL_GROUPS => $this->ingestGroupsXml($connection, $body, $userId, 'agent'),
            IntegrationAgentJob::KIND_PULL_LEDGERS => $this->ingestLedgersXml($connection, $body, $userId, 'agent'),
            default => $this->ingestVouchersXml($connection, $body, $userId, 'agent', $job->params ?? []),
        };

        $job->update([
            'status' => IntegrationAgentJob::STATUS_COMPLETED,
            'batch_id' => $batch->id,
            'leased_until' => null,
            'completed_at' => now(),
            'last_error' => null,
        ]);

        if ($connection->first_inbound_completed_at === null
            && ! IntegrationAgentJob::query()
                ->where('connection_id', $connection->id)
                ->where('kind', IntegrationAgentJob::KIND_PULL_VOUCHERS)
                ->whereIn('status', [IntegrationAgentJob::STATUS_QUEUED, IntegrationAgentJob::STATUS_LEASED, IntegrationAgentJob::STATUS_FAILED])
                ->exists()) {
            $connection->update(['first_inbound_completed_at' => now()]);
        }

        return ['status' => IntegrationAgentJob::STATUS_COMPLETED, 'detail' => null];
    }

    private function completePush(IntegrationAgentJob $job, string $body): array
    {
        $response = $this->parser->importResponse($body);
        $ref = $this->jobRef($job);

        // A ledger that's already there is what we wanted.
        $alreadyThere = $job->kind === IntegrationAgentJob::KIND_PUSH_LEDGER
            && ! $response['ok']
            && preg_match('/already exists|duplicate/i', (string) $response['error']);

        if (! $response['ok'] && ! $alreadyThere) {
            // Tally answered and refused. Trying again would get the same answer.
            return $this->failJob($job, (string) $response['error']);
        }

        $ref?->update([
            'status' => IntegrationRef::STATUS_SYNCED,
            'last_error' => null,
            'last_synced_at' => now(),
            'meta' => array_merge($ref->meta ?? [], array_filter(['tally_last_voucher_id' => $response['last_voucher_id']])),
        ]);

        $job->update(['status' => IntegrationAgentJob::STATUS_COMPLETED, 'leased_until' => null, 'completed_at' => now(), 'last_error' => null]);

        if ($ref) {
            $this->recordPush($job, $ref, IntegrationRecord::OUTCOME_CREATED, null);
        }

        return ['status' => IntegrationAgentJob::STATUS_COMPLETED, 'detail' => null];
    }

    public function ingestGroupsXml(IntegrationConnection $connection, string $xml, ?int $userId, string $channel): IntegrationBatch
    {
        return $this->ingest->ingest($connection, IntegrationRef::ENTITY_GROUP, $this->parser->groups($xml), $userId, $channel)['batch'];
    }

    public function ingestLedgersXml(IntegrationConnection $connection, string $xml, ?int $userId, string $channel): IntegrationBatch
    {
        $items = $this->parser->ledgers($xml, $this->groupRoots($connection));

        return $this->ingest->ingest($connection, IntegrationRef::ENTITY_LEDGER, $items, $userId, $channel)['batch'];
    }

    /** @param array{from?: string, to?: string} $window set when this is one whole date range */
    public function ingestVouchersXml(IntegrationConnection $connection, string $xml, ?int $userId, string $channel, array $window = []): IntegrationBatch
    {
        $typeMap = collect((array) $connection->setting('voucher_type_map', []))
            ->mapWithKeys(fn ($type, $name) => [mb_strtolower((string) $name) => $type])
            ->all();

        $parsed = $this->parser->vouchers($xml, $typeMap);
        $items = array_values(array_filter($parsed, fn ($voucher) => ! $this->isOurOwnEcho($connection, $voucher)));
        $batch = $this->ingest->ingest($connection, IntegrationRef::ENTITY_VOUCHER, $items, $userId, $channel)['batch'];

        if (isset($window['from'], $window['to'])) {
            $this->flagMissing($connection, $parsed, $window['from'], $window['to']);
        }

        return $batch;
    }

    /**
     * A voucher this ERP pushed comes back in the Day Book. Importing it would
     * double the entry. Checked on REMOTEID, then on number and date, in case
     * Tally doesn't hand our REMOTEID back.
     */
    private function isOurOwnEcho(IntegrationConnection $connection, array $voucher): bool
    {
        if (str_starts_with((string) ($voucher['remote_id'] ?? ''), 'erp-'.$connection->tenant_id.'-')) {
            return true;
        }

        return ! empty($voucher['number']) && ! empty($voucher['date']) && IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', IntegrationRef::ENTITY_VOUCHER)
            ->where('direction', IntegrationRef::DIRECTION_OUTBOUND)
            ->where('external_number', $voucher['number'])
            ->whereDate('external_date', $voucher['date'])
            ->exists();
    }

    /**
     * Tally's export doesn't list deleted vouchers, so a deletion shows up only as
     * an absence. An absence could also be the wrong company being open, so it's
     * raised for a person and never reversed automatically. An empty reply isn't
     * treated as evidence at all.
     */
    private function flagMissing(IntegrationConnection $connection, array $parsed, string $from, string $to): void
    {
        if ($parsed === []) {
            return;
        }

        $seen = array_column($parsed, 'external_id');

        IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', IntegrationRef::ENTITY_VOUCHER)
            ->where('direction', IntegrationRef::DIRECTION_INBOUND)
            ->whereNotNull('local_id')
            ->whereDate('external_date', '>=', $from)
            ->whereDate('external_date', '<=', $to)
            ->whereNotIn('external_id', $seen)
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('journals')
                ->whereColumn('journals.id', 'accounting_integration_refs.local_id')
                ->where('journals.status', Journal::STATUS_POSTED))
            ->each(function (IntegrationRef $ref) use ($connection) {
                $hash = hash('sha256', 'missing:'.$ref->external_id);

                if ($this->conflicts->keptLocal($connection, IntegrationRef::ENTITY_VOUCHER, $ref->external_id, $hash)) {
                    return;
                }

                $this->conflicts->record(
                    $connection, IntegrationRef::ENTITY_VOUCHER, $ref->external_id, IntegrationConflict::REASON_MISSING_IN_SOURCE,
                    "Voucher {$ref->external_number} is no longer in the source. It may have been deleted there.",
                    ['external_id' => $ref->external_id, 'is_cancelled' => true], $hash, $ref,
                );
            });
    }

    /** @return array<string, string> */
    private function groupRoots(IntegrationConnection $connection): array
    {
        return IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', IntegrationRef::ENTITY_GROUP)
            ->get(['external_name', 'meta'])
            ->filter(fn ($ref) => ! empty($ref->meta['root_group']) && $ref->external_name)
            ->mapWithKeys(fn ($ref) => [mb_strtolower($ref->external_name) => $ref->meta['root_group']])
            ->all();
    }

    /**
     * XML for hand import into Tally, for a site without the relay. Ledgers are
     * marked as sent straight away because creating one twice is harmless.
     * Vouchers stay in the feed until confirmed through the ack call.
     *
     * @return array{xml: string, ids: array<int, int>}
     */
    public function exportXml(IntegrationConnection $connection, string $entity, int $limit = 100): array
    {
        $this->assertTally($connection);
        $journals = $this->outbound->pendingJournals($connection, $limit);

        if ($entity === 'ledgers') {
            $missing = $this->missingLedgers($connection, $journals);

            DB::transaction(function () use ($connection, $missing) {
                foreach ($missing as $ledger) {
                    $this->createLedgerRef($connection, $ledger, IntegrationRef::STATUS_SYNCED);
                }
            });

            return [
                'xml' => $this->xml->importLedgers($connection, array_values($missing)),
                'ids' => array_column($missing, 'local_id'),
            ];
        }

        return [
            'xml' => $this->xml->importVouchers($connection, $journals->map(
                fn (Journal $journal) => $this->tallyVoucher($connection, $journal, $this->remoteId($connection, $journal))
            )->all()),
            'ids' => $journals->pluck('id')->all(),
        ];
    }

    /** Lets refused or failed pushes go again on the next cycle. @return int refs released */
    public function retryFailedPushes(IntegrationConnection $connection): int
    {
        return IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('direction', IntegrationRef::DIRECTION_OUTBOUND)
            ->where('status', IntegrationRef::STATUS_FAILED)
            ->delete();
    }

    private function releaseExpiredLeases(IntegrationConnection $connection): void
    {
        IntegrationAgentJob::query()
            ->where('connection_id', $connection->id)
            ->where('status', IntegrationAgentJob::STATUS_LEASED)
            ->where('leased_until', '<', now())
            ->each(function (IntegrationAgentJob $job) {
                $this->transportFailure($job, 'The relay took the job but never reported back.');
            });
    }

    private function recordPush(IntegrationAgentJob $job, IntegrationRef $ref, string $outcome, ?string $error): void
    {
        if (! $job->batch_id) {
            return;
        }

        IntegrationRecord::create([
            'tenant_id' => $job->tenant_id,
            'batch_id' => $job->batch_id,
            'entity_type' => $ref->entity_type,
            'external_id' => $ref->external_id,
            'local_type' => $ref->local_type,
            'local_id' => $ref->local_id,
            'outcome' => $outcome,
            'error_code' => $error !== null ? 'tally_rejected' : null,
            'error' => $error,
            'attempts' => $job->attempts,
        ]);

        $batch = IntegrationBatch::query()->find($job->batch_id);
        if ($batch !== null) {
            $this->ingest->finalize($batch);

            if (IntegrationAgentJob::query()->where('batch_id', $batch->id)->whereIn('status', [IntegrationAgentJob::STATUS_QUEUED, IntegrationAgentJob::STATUS_LEASED])->exists()) {
                $batch->update(['status' => IntegrationBatch::STATUS_PROCESSING, 'finished_at' => null]);
            }
        }
    }

    private function outboundBatch(IntegrationConnection $connection, string $entityType, ?int $userId, int $total): IntegrationBatch
    {
        return IntegrationBatch::create([
            'tenant_id' => $connection->tenant_id,
            'connection_id' => $connection->id,
            'direction' => IntegrationRef::DIRECTION_OUTBOUND,
            'entity_type' => $entityType,
            'channel' => 'agent',
            'status' => IntegrationBatch::STATUS_PROCESSING,
            'total' => $total,
            'created_by' => $userId,
        ]);
    }

    private function queue(IntegrationConnection $connection, string $kind, array $params, string $xml, ?int $batchId = null): IntegrationAgentJob
    {
        return IntegrationAgentJob::create([
            'tenant_id' => $connection->tenant_id,
            'connection_id' => $connection->id,
            'batch_id' => $batchId,
            'kind' => $kind,
            'params' => $params,
            'request_xml' => $xml,
            'status' => IntegrationAgentJob::STATUS_QUEUED,
        ]);
    }

    private function openJobs(IntegrationConnection $connection)
    {
        return IntegrationAgentJob::query()
            ->where('connection_id', $connection->id)
            ->whereIn('status', [IntegrationAgentJob::STATUS_QUEUED, IntegrationAgentJob::STATUS_LEASED]);
    }

    private function jobRef(IntegrationAgentJob $job): ?IntegrationRef
    {
        $refId = $job->params['ref_id'] ?? null;

        return $refId ? IntegrationRef::query()->find($refId) : null;
    }

    private function assertTally(IntegrationConnection $connection): void
    {
        if ($connection->product !== IntegrationConnection::PRODUCT_TALLY) {
            throw new InvalidArgumentException('This call is for Tally connections.');
        }
    }
}
