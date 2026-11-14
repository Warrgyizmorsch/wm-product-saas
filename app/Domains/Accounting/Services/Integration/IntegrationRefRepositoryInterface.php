<?php

namespace App\Domains\Accounting\Services\Integration;

use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;

interface IntegrationRefRepositoryInterface
{
    /** Finds or creates the row and locks it. Must be called inside a transaction. */
    public function lockOrCreate(IntegrationConnection $connection, string $entityType, string $externalId, string $direction): IntegrationRef;

    /** A ledger ref the ERP pushed under this name, re-keyed to the source's own id. */
    public function adoptByName(IntegrationConnection $connection, string $externalId, string $name): ?IntegrationRef;

    public function ledgerByExternalId(IntegrationConnection $connection, string $externalId): ?IntegrationRef;

    public function ledgerByName(IntegrationConnection $connection, string $name): ?IntegrationRef;

    public function forLocal(IntegrationConnection $connection, string $entityType, string $localType, int $localId): ?IntegrationRef;
}
