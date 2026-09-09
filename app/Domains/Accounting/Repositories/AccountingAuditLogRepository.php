<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\AccountingAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AccountingAuditLogRepository implements AccountingAuditLogRepositoryInterface
{
    public function create(array $data): AccountingAuditLog
    {
        return AccountingAuditLog::create($data);
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return AccountingAuditLog::query()
            ->with(['subject', 'triggeredBy'])
            ->when($filters['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['event_type'] ?? null, fn ($query, $eventType) => $query->where('event_type', $eventType))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
