<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\AccountingAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccountingAuditLogRepositoryInterface
{
    public function create(array $data): AccountingAuditLog;

    /**
     * @param array{subject_type?: string, event_type?: string, from?: string, to?: string, search?: string, sort?: string, direction?: string} $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;
}
