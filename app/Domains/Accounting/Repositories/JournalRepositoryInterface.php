<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\Journal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface JournalRepositoryInterface
{
    public function paginateAll(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Journal;

    public function findWithEntries(int $id): ?Journal;

    public function findByReference(string $referenceType, int $referenceId): Collection;

    public function nextJournalNumber(int $tenantId): string;

    public function create(array $data): Journal;

    public function createWithEntries(array $journalData, array $entryLines): Journal;
}
