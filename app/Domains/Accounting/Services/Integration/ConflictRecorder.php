<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRecord;
use App\Domains\Accounting\Models\IntegrationRef;

class ConflictRecorder
{
    /**
     * Opens a conflict, or refreshes the open one for this record, so repeated
     * syncs never pile up copies.
     *
     * @return array{outcome: string, local_type: ?string, local_id: ?int, error_code: string, error: string}
     */
    public function record(
        IntegrationConnection $connection,
        string $entityType,
        string $externalId,
        string $reason,
        string $detail,
        array $incoming,
        ?string $incomingHash,
        ?IntegrationRef $ref,
    ): array {
        $open = IntegrationConflict::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->where('status', IntegrationConflict::STATUS_OPEN)
            ->first();

        $values = [
            'tenant_id' => $connection->tenant_id,
            'connection_id' => $connection->id,
            'ref_id' => $ref?->id,
            'entity_type' => $entityType,
            'external_id' => $externalId,
            'reason' => $reason,
            'detail' => $detail,
            'incoming_payload' => $incoming,
            'incoming_hash' => $incomingHash,
            'status' => IntegrationConflict::STATUS_OPEN,
        ];

        $open ? $open->update($values) : IntegrationConflict::create($values);

        return [
            'outcome' => IntegrationRecord::OUTCOME_CONFLICT,
            'local_type' => $ref?->local_type,
            'local_id' => $ref?->local_id,
            'error_code' => $reason,
            'error' => $detail,
        ];
    }

    /** Has a person already said to keep the local record against this exact incoming version? */
    public function keptLocal(IntegrationConnection $connection, string $entityType, string $externalId, ?string $incomingHash): bool
    {
        return $incomingHash !== null && IntegrationConflict::query()
            ->where('connection_id', $connection->id)
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->where('incoming_hash', $incomingHash)
            ->where('status', IntegrationConflict::STATUS_RESOLVED)
            ->where('resolution', IntegrationConflict::RESOLUTION_KEEP_LOCAL)
            ->exists();
    }
}
