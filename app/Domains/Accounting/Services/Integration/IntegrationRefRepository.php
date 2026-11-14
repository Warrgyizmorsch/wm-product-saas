<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;
use Illuminate\Database\UniqueConstraintViolationException;

class IntegrationRefRepository implements IntegrationRefRepositoryInterface
{
    public function lockOrCreate(IntegrationConnection $connection, string $entityType, string $externalId, string $direction): IntegrationRef
    {
        $find = fn () => IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->lockForUpdate()
            ->first();

        $ref = $find();
        if ($ref !== null) {
            return $ref;
        }

        try {
            return IntegrationRef::create([
                'tenant_id' => $connection->tenant_id,
                'connection_id' => $connection->id,
                'direction' => $direction,
                'entity_type' => $entityType,
                'external_id' => $externalId,
                'status' => IntegrationRef::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return $find() ?? throw $e;
        }
    }

    public function adoptByName(IntegrationConnection $connection, string $externalId, string $name): ?IntegrationRef
    {
        $alreadyKeyed = IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', IntegrationRef::ENTITY_LEDGER)
            ->where('external_id', $externalId)
            ->exists();

        if ($alreadyKeyed) {
            return null;
        }

        $pushed = IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', IntegrationRef::ENTITY_LEDGER)
            ->where('direction', IntegrationRef::DIRECTION_OUTBOUND)
            ->whereRaw('LOWER(external_name) = ?', [mb_strtolower($name)])
            ->lockForUpdate()
            ->first();

        $pushed?->update(['external_id' => $externalId]);

        return $pushed;
    }

    public function ledgerByExternalId(IntegrationConnection $connection, string $externalId): ?IntegrationRef
    {
        return $this->ledgers($connection)->where('external_id', $externalId)->first();
    }

    public function ledgerByName(IntegrationConnection $connection, string $name): ?IntegrationRef
    {
        return $this->ledgers($connection)->whereRaw('LOWER(external_name) = ?', [mb_strtolower($name)])->first();
    }

    public function forLocal(IntegrationConnection $connection, string $entityType, string $localType, int $localId): ?IntegrationRef
    {
        return IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', $entityType)
            ->where('local_type', $localType)
            ->where('local_id', $localId)
            ->first();
    }

    private function ledgers(IntegrationConnection $connection)
    {
        return IntegrationRef::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', IntegrationRef::ENTITY_LEDGER)
            ->whereNotNull('local_id');
    }
}
