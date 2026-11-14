<?php

namespace App\Domains\Accounting\Controllers\Api\Integration;

use App\Domains\Accounting\Models\IntegrationBatch;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Services\Integration\IdempotencyKeyReusedException;
use App\Domains\Accounting\Services\Integration\IntegrationConnectionService;
use App\Domains\Accounting\Services\Integration\IntegrationIngestService;
use App\Domains\Accounting\Services\Integration\OutboundFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** The product-neutral REST surface: push batches in, read the feed out, see the log. */
class SyncApiController extends IntegrationApiController
{
    private const MAX_BATCH = 500;

    public function __construct(
        private readonly IntegrationIngestService $ingest,
        private readonly OutboundFeedService $outbound,
        private readonly IntegrationConnectionService $connections,
    ) {
    }

    public function ingestBatch(Request $request, int $connection, string $entity): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $entityType = $this->entityType($entity);

        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '' || mb_strlen($key) > 191) {
            return $this->refused('An Idempotency-Key header of up to 191 characters is required.', 400);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'items.*' => ['array'],
        ]);

        try {
            $result = $this->ingest->ingest($model, $entityType, $data['items'], $request->user()->id, 'api', $key);
        } catch (IdempotencyKeyReusedException $e) {
            return $this->refused($e->getMessage(), 409);
        }

        return $this->ok(
            $result['batch']->load('records'),
            $result['replayed'] ? 'Already processed; returning the original result.' : 'Batch processed.',
            $result['replayed'] ? 200 : 201,
            $result['replayed'] ? ['X-Idempotent-Replay' => 'true'] : [],
        );
    }

    public function feed(Request $request, int $connection, string $entity): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $limit = max(1, min((int) $request->query('limit', 100), 500));

        $items = match ($this->entityType($entity)) {
            IntegrationRef::ENTITY_GROUP => $this->outbound->pendingGroups($model, $limit),
            IntegrationRef::ENTITY_LEDGER => $this->outbound->pendingLedgers($model, $limit),
            default => $this->outbound->pendingJournals($model, $limit)
                ->map(fn ($journal) => $this->outbound->canonicalVoucher($model, $journal))
                ->values()
                ->all(),
        };

        return $this->ok($items);
    }

    public function acknowledge(Request $request, int $connection, string $entity): JsonResponse
    {
        $model = $this->connection($request, $connection);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'items.*.local_id' => ['required', 'integer'],
            'items.*.external_id' => ['nullable', 'string', 'max:191'],
            'items.*.external_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return $this->ok(['acknowledged' => $this->outbound->acknowledge($model, $this->entityType($entity), $data['items'])]);
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }
    }

    public function batches(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        return $this->paginated(IntegrationBatch::query()
            ->where('connection_id', $model->id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate($this->perPage($request)));
    }

    public function batch(Request $request, int $connection, int $batch): JsonResponse
    {
        $model = $this->connection($request, $connection);

        return $this->ok(IntegrationBatch::query()->where('connection_id', $model->id)->with('records')->findOrFail($batch));
    }

    public function retryBatch(Request $request, int $connection, int $batch): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $row = IntegrationBatch::query()->where('connection_id', $model->id)->findOrFail($batch);

        try {
            return $this->ok($this->ingest->retryFailed($row, $request->user()->id)->load('records'), 'Failed records retried.');
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }
    }

    public function conflicts(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        return $this->paginated(IntegrationConflict::query()
            ->where('connection_id', $model->id)
            ->where('status', $request->query('status', IntegrationConflict::STATUS_OPEN))
            ->orderBy('id')
            ->paginate($this->perPage($request)));
    }

    public function resolveConflict(Request $request, int $connection, int $conflict): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $row = IntegrationConflict::query()->where('connection_id', $model->id)->findOrFail($conflict);

        $data = $request->validate([
            'resolution' => ['required', Rule::in([IntegrationConflict::RESOLUTION_APPLY_INCOMING, IntegrationConflict::RESOLUTION_KEEP_LOCAL])],
        ]);

        try {
            return $this->ok($this->connections->resolveConflict($row, $data['resolution'], $request->user()->id), 'Conflict resolved.');
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }
    }

    private function entityType(string $entity): string
    {
        return match ($entity) {
            'ledger-groups' => IntegrationRef::ENTITY_GROUP,
            'ledgers' => IntegrationRef::ENTITY_LEDGER,
            'vouchers' => IntegrationRef::ENTITY_VOUCHER,
            default => abort(404),
        };
    }
}
