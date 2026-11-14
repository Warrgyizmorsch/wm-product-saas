<?php

namespace App\Domains\Accounting\Controllers\Api\Integration;

use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Services\Integration\IntegrationConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ConnectionApiController extends IntegrationApiController
{
    public function __construct(private readonly IntegrationConnectionService $connections)
    {
    }

    public function index(Request $request): JsonResponse
    {
        // Same rule as connection(): a connector token sees its own connection only.
        $abilities = (array) ($request->user()->currentAccessToken()->abilities ?? ['*']);

        return $this->paginated(IntegrationConnection::query()
            ->when(! in_array('*', $abilities, true), fn ($q) => $q->whereIn('id', $this->connectionIds($abilities)))
            ->orderBy('id')
            ->paginate($this->perPage($request)));
    }

    public function show(Request $request, int $connection): JsonResponse
    {
        return $this->ok($this->connection($request, $connection));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'name' => ['required', 'string', 'max:191', Rule::unique('accounting_integration_connections', 'name')->where('tenant_id', tenant_id())->whereNull('deleted_at')],
            'product' => ['required', Rule::in(IntegrationConnection::PRODUCTS)],
        ]);

        return $this->ok($this->connections->create($data, $request->user()), 'Connection created.', 201);
    }

    public function update(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        $data = $request->validate($this->rules() + [
            'name' => ['sometimes', 'string', 'max:191', Rule::unique('accounting_integration_connections', 'name')->where('tenant_id', tenant_id())->whereNull('deleted_at')->ignore($model->id)],
            'status' => ['sometimes', Rule::in([IntegrationConnection::STATUS_ACTIVE, IntegrationConnection::STATUS_PAUSED])],
        ]);

        $model->update($data);

        return $this->ok($model->fresh(), 'Connection updated.');
    }

    public function destroy(Request $request, int $connection): JsonResponse
    {
        $this->connection($request, $connection)->delete();

        return $this->ok(null, 'Connection removed. Posted journals are not affected.');
    }

    public function mintToken(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        return $this->ok($this->connections->mintToken($model, $request->user(), $data['name']), 'Copy this token now. It is not shown again.', 201);
    }

    public function revokeToken(Request $request, int $connection, int $token): JsonResponse
    {
        $revoked = $this->connections->revokeToken($this->connection($request, $connection), $request->user(), $token);

        return $revoked ? $this->ok(null, 'Token revoked.') : $this->refused('Token not found.', 404);
    }

    public function setMapping(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);
        $data = $request->validate([
            'entity_type' => ['required', Rule::in([IntegrationRef::ENTITY_LEDGER, IntegrationRef::ENTITY_GROUP])],
            'external_id' => ['required', 'string', 'max:191'],
            'external_name' => ['nullable', 'string', 'max:255'],
            'local_id' => ['required', 'integer'],
        ]);

        try {
            return $this->ok($this->connections->setMapping($model, $data['entity_type'], $data['external_id'], $data['external_name'] ?? null, $data['local_id']), 'Mapping saved.');
        } catch (InvalidArgumentException $e) {
            return $this->refused($e->getMessage());
        }
    }

    public function mappings(Request $request, int $connection): JsonResponse
    {
        $model = $this->connection($request, $connection);

        return $this->paginated(IntegrationRef::query()
            ->where('connection_id', $model->id)
            ->when($request->query('entity_type'), fn ($q, $type) => $q->where('entity_type', $type))
            ->orderBy('id')
            ->paginate($this->perPage($request)));
    }

    private function connectionIds(array $abilities): array
    {
        $prefix = IntegrationConnection::abilityPrefix();

        return collect($abilities)
            ->filter(fn ($ability) => str_starts_with((string) $ability, $prefix))
            ->map(fn ($ability) => (int) substr((string) $ability, strlen($prefix)))
            ->values()
            ->all();
    }

    private function rules(): array
    {
        return [
            'external_company' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('tenant_id', tenant_id())],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', tenant_id())],
            'settings' => ['nullable', 'array'],
            'settings.direction' => ['nullable', Rule::in(['both', 'inbound', 'outbound'])],
            'settings.sync_from' => ['nullable', 'date'],
            'settings.export_from' => ['nullable', 'date'],
            'settings.rescan_days' => ['nullable', 'integer', 'min:1', 'max:400'],
            'settings.auto_sync_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'settings.voucher_type_map' => ['nullable', 'array'],
            'settings.voucher_entries_tag' => ['nullable', Rule::in(['LEDGERENTRIES.LIST', 'ALLLEDGERENTRIES.LIST'])],
        ];
    }
}
