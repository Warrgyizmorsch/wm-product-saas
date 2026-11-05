<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\IntegrationBatch;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRecord;
use App\Domains\Accounting\Models\IntegrationRef;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

/**
 * Takes canonical records from any source (REST client, Tally relay, uploaded
 * file), runs each through its ingestor and writes the sync log. One bad record
 * never stops the others.
 */
class IntegrationIngestService
{
    public function __construct(
        private readonly MasterIngestor $masters,
        private readonly VoucherIngestor $vouchers,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{batch: IntegrationBatch, replayed: bool}
     */
    public function ingest(
        IntegrationConnection $connection,
        string $entityType,
        array $items,
        ?int $userId,
        string $channel = 'api',
        ?string $idempotencyKey = null,
    ): array {
        $requestHash = hash('sha256', json_encode([$entityType, $items]));

        if ($idempotencyKey !== null) {
            $existing = $this->findByKey($connection, $idempotencyKey);
            if ($existing !== null) {
                return ['batch' => $this->assertSameRequest($existing, $requestHash), 'replayed' => true];
            }
        }

        try {
            $batch = IntegrationBatch::create([
                'tenant_id' => $connection->tenant_id,
                'connection_id' => $connection->id,
                'direction' => IntegrationRef::DIRECTION_INBOUND,
                'entity_type' => $entityType,
                'channel' => $channel,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => IntegrationBatch::STATUS_PROCESSING,
                'total' => count($items),
                'created_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Two identical retries at once: the first one owns the batch.
            $existing = $this->findByKey($connection, (string) $idempotencyKey) ?? throw $e;

            return ['batch' => $this->assertSameRequest($existing, $requestHash), 'replayed' => true];
        }

        foreach ($items as $item) {
            $this->process($connection, $batch, $entityType, $item, $userId);
        }

        return ['batch' => $this->finalize($batch), 'replayed' => false];
    }

    /** Runs a batch's failed records again, in the same batch, with their saved payloads. */
    public function retryFailed(IntegrationBatch $batch, ?int $userId): IntegrationBatch
    {
        if ($batch->direction !== IntegrationRef::DIRECTION_INBOUND) {
            throw new InvalidArgumentException('Only inbound batches can be retried here; run a sync for outbound records.');
        }

        $connection = $batch->connection;

        foreach ($batch->records()->where('outcome', IntegrationRecord::OUTCOME_FAILED)->get() as $record) {
            $this->process($connection, $batch, $batch->entity_type, (array) $record->payload, $userId, record: $record);
        }

        return $this->finalize($batch);
    }

    /** Applies one record outside any retry, for a person's conflict decision. */
    public function applyForced(IntegrationConnection $connection, string $entityType, array $item, ?int $userId): IntegrationBatch
    {
        $batch = IntegrationBatch::create([
            'tenant_id' => $connection->tenant_id,
            'connection_id' => $connection->id,
            'direction' => IntegrationRef::DIRECTION_INBOUND,
            'entity_type' => $entityType,
            'channel' => 'conflict',
            'status' => IntegrationBatch::STATUS_PROCESSING,
            'total' => 1,
            'created_by' => $userId,
        ]);

        $this->process($connection, $batch, $entityType, $item, $userId, force: true);

        return $this->finalize($batch);
    }

    private function process(
        IntegrationConnection $connection,
        IntegrationBatch $batch,
        string $entityType,
        array $item,
        ?int $userId,
        bool $force = false,
        ?IntegrationRecord $record = null,
    ): void {
        try {
            $result = $this->dispatch($connection, $entityType, $item, $userId, $force);
        } catch (IntegrationRecordException $e) {
            $result = ['outcome' => IntegrationRecord::OUTCOME_FAILED, 'error_code' => $e->errorCode, 'error' => $e->getMessage()];
        } catch (InvalidArgumentException|\DomainException $e) {
            $result = ['outcome' => IntegrationRecord::OUTCOME_FAILED, 'error_code' => 'posting_failed', 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);
            $result = ['outcome' => IntegrationRecord::OUTCOME_FAILED, 'error_code' => 'internal_error', 'error' => 'Unexpected error while processing this record.'];
        }

        $values = [
            'tenant_id' => $connection->tenant_id,
            'batch_id' => $batch->id,
            'entity_type' => $entityType,
            'external_id' => isset($item['external_id']) ? mb_substr((string) $item['external_id'], 0, 191) : null,
            'local_type' => $result['local_type'] ?? null,
            'local_id' => $result['local_id'] ?? null,
            'outcome' => $result['outcome'],
            'error_code' => $result['error_code'] ?? null,
            'error' => $result['error'] ?? null,
            'payload' => $item,
        ];

        if ($record !== null) {
            $record->update($values + ['attempts' => $record->attempts + 1]);
        } else {
            IntegrationRecord::create($values);
        }
    }

    private function dispatch(IntegrationConnection $connection, string $entityType, array $item, ?int $userId, bool $force): array
    {
        return match ($entityType) {
            IntegrationRef::ENTITY_GROUP => $this->masters->ingestGroup($connection, $item),
            IntegrationRef::ENTITY_LEDGER => $this->masters->ingestLedger($connection, $item, $force),
            IntegrationRef::ENTITY_VOUCHER => $this->vouchers->ingest($connection, $item, $userId, $force),
            default => throw new InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    public function finalize(IntegrationBatch $batch): IntegrationBatch
    {
        $counts = $batch->records()->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total', 'outcome');

        $failed = (int) ($counts[IntegrationRecord::OUTCOME_FAILED] ?? 0);
        $conflicts = (int) ($counts[IntegrationRecord::OUTCOME_CONFLICT] ?? 0);

        $batch->update([
            'status' => $failed + $conflicts > 0 ? IntegrationBatch::STATUS_COMPLETED_WITH_ISSUES : IntegrationBatch::STATUS_COMPLETED,
            'created_count' => (int) ($counts[IntegrationRecord::OUTCOME_CREATED] ?? 0),
            'updated_count' => (int) ($counts[IntegrationRecord::OUTCOME_UPDATED] ?? 0),
            'unchanged_count' => (int) ($counts[IntegrationRecord::OUTCOME_UNCHANGED] ?? 0),
            'pending_approval_count' => (int) ($counts[IntegrationRecord::OUTCOME_PENDING_APPROVAL] ?? 0),
            'failed_count' => $failed,
            'conflict_count' => $conflicts,
            'finished_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function findByKey(IntegrationConnection $connection, string $key): ?IntegrationBatch
    {
        return IntegrationBatch::query()
            ->where('connection_id', $connection->id)
            ->where('idempotency_key', $key)
            ->first();
    }

    private function assertSameRequest(IntegrationBatch $batch, string $requestHash): IntegrationBatch
    {
        if (! hash_equals((string) $batch->request_hash, $requestHash)) {
            throw new IdempotencyKeyReusedException('This Idempotency-Key was already used with a different request.');
        }

        return $batch;
    }
}
