<?php

namespace App\Domains\Accounting\Controllers\Api\Integration;

use App\Domains\Accounting\Models\IntegrationAgentJob;
use App\Domains\Accounting\Services\Integration\Tally\TallySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** The relay's calls, plus the file route for a site running without it. */
class TallyApiController extends IntegrationApiController
{
    /** Roughly 40 MB once in a JSON string; a month of Day Book fits well inside it. */
    private const MAX_RESPONSE_KB = 40960;

    public function __construct(private readonly TallySyncService $tally)
    {
    }

    public function plan(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        $data = $request->validate([
            'direction' => ['nullable', Rule::in(['both', 'inbound', 'outbound'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        try {
            return $this->ok(['jobs_queued' => $this->tally->plan($model, $data, $request->user()->id)]);
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }
    }

    public function poll(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'agent_version' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $jobs = $this->tally->poll($model, $data['limit'] ?? 10, $data['agent_version'] ?? null, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok($jobs->map(fn (IntegrationAgentJob $job) => [
            'id' => $job->id,
            'kind' => $job->kind,
            'request_xml' => $job->request_xml,
        ])->values());
    }

    public function result(Request $request, int $connection, int $job): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $row = IntegrationAgentJob::query()->where('connection_id', $model->id)->findOrFail($job);

        $data = $request->validate([
            'response_body' => ['nullable', 'string', 'max:'.(self::MAX_RESPONSE_KB * 1024)],
            'transport_error' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->ok($this->tally->complete($row, $data['response_body'] ?? null, $data['transport_error'] ?? null, $request->user()->id));
    }

    public function retryPushes(Request $request, int $connection): JsonResponse
    {
        return $this->ok(['released' => $this->tally->retryFailedPushes($this->connection($request, $connection))]);
    }

    /** Hand-carried Tally export, for a site without the relay. */
    public function importFile(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        $data = $request->validate([
            'entity' => ['required', Rule::in(['ledger-groups', 'ledgers', 'vouchers'])],
            'file' => ['required', 'file', 'max:'.self::MAX_RESPONSE_KB],
        ]);

        $xml = (string) file_get_contents($data['file']->getRealPath());
        $userId = $request->user()->id;

        try {
            $batch = match ($data['entity']) {
                'ledger-groups' => $this->tally->ingestGroupsXml($model, $xml, $userId, 'file'),
                'ledgers' => $this->tally->ingestLedgersXml($model, $xml, $userId, 'file'),
                default => $this->tally->ingestVouchersXml($model, $xml, $userId, 'file'),
            };
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }

        return $this->ok($batch->load('records'), 'File processed.', 201);
    }

    /** XML to import into Tally by hand. Confirm vouchers afterwards with the ack call. */
    public function exportFile(Request $request, int $connection): Response|JsonResponse
    {
        $model = $this->connection($request, $connection);

        $data = $request->validate([
            'entity' => ['required', Rule::in(['ledgers', 'vouchers'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        try {
            $export = $this->tally->exportXml($model, $data['entity'], (int) ($data['limit'] ?? 100));
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }

        return response($export['xml'], 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tally-'.$data['entity'].'.xml"',
            'X-Accounting-Local-Ids' => implode(',', $export['ids']),
        ]);
    }
}
