<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\LedgerGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Connections, their tokens, manual mappings and conflict decisions. */
class IntegrationConnectionService
{
    public function __construct(
        private readonly IntegrationRefRepositoryInterface $refs,
        private readonly IntegrationIngestService $ingest,
    ) {
    }

    public function create(array $data, User $user): IntegrationConnection
    {
        return IntegrationConnection::create($data + [
            'tenant_id' => require_tenant_id(),
            'status' => IntegrationConnection::STATUS_ACTIVE,
            'created_by' => $user->id,
        ]);
    }

    /**
     * A token tied to one connection and to the integration API alone. It acts as
     * the person who mints it, so mint it signed in as a dedicated service user.
     *
     * @return array{id: int, token: string}
     */
    public function mintToken(IntegrationConnection $connection, User $user, string $name): array
    {
        $token = $user->createToken($name, [$connection->tokenAbility()]);

        return ['id' => $token->accessToken->id, 'token' => $token->plainTextToken];
    }

    public function revokeToken(IntegrationConnection $connection, User $user, int $tokenId): bool
    {
        return $user->tokens()
            ->whereKey($tokenId)
            ->get()
            ->filter(fn ($token) => in_array($connection->tokenAbility(), (array) $token->abilities, true))
            ->each->delete()
            ->isNotEmpty();
    }

    /** Points an external record at a chosen local one, for names that don't match. */
    public function setMapping(IntegrationConnection $connection, string $entityType, string $externalId, ?string $externalName, int $localId): IntegrationRef
    {
        [$localType, $local] = match ($entityType) {
            IntegrationRef::ENTITY_LEDGER => [IntegrationRef::LOCAL_ACCOUNT, ChartOfAccount::query()->withoutGlobalScope('company')->find($localId)],
            IntegrationRef::ENTITY_GROUP => [IntegrationRef::LOCAL_GROUP, LedgerGroup::query()->withoutGlobalScope('company')->find($localId)],
            default => throw new InvalidArgumentException('Only ledgers and ledger groups can be mapped by hand.'),
        };

        if ($local === null) {
            throw new InvalidArgumentException("No {$entityType} with id {$localId} in this workspace.");
        }

        return DB::transaction(function () use ($connection, $entityType, $externalId, $externalName, $localType, $localId) {
            $ref = $this->refs->lockOrCreate($connection, $entityType, $externalId, IntegrationRef::DIRECTION_INBOUND);
            $ref->update([
                'external_name' => $externalName ?? $ref->external_name,
                'local_type' => $localType,
                'local_id' => $localId,
                'meta' => array_merge($ref->meta ?? [], ['manual' => true]),
                'status' => IntegrationRef::STATUS_SYNCED,
                'last_error' => null,
                'last_synced_at' => now(),
            ]);

            return $ref->fresh();
        });
    }

    public function resolveConflict(IntegrationConflict $conflict, string $resolution, ?int $userId): IntegrationConflict
    {
        if ($conflict->status !== IntegrationConflict::STATUS_OPEN) {
            throw new InvalidArgumentException('This conflict has already been resolved.');
        }

        if ($resolution === IntegrationConflict::RESOLUTION_APPLY_INCOMING) {
            $batch = $this->ingest->applyForced(
                $conflict->connection,
                $conflict->entity_type,
                (array) $conflict->incoming_payload,
                $userId,
            );

            if ($batch->failed_count > 0 || $batch->conflict_count > 0) {
                $reason = $batch->records()->latest('id')->value('error');
                throw new InvalidArgumentException("The incoming version couldn't be applied: {$reason}");
            }
        }

        $conflict->update([
            'status' => IntegrationConflict::STATUS_RESOLVED,
            'resolution' => $resolution,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        return $conflict->fresh();
    }
}
