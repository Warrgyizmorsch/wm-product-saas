<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\AccountingAuditLog;
use App\Domains\Accounting\Repositories\AccountingAuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class AccountingAuditLogService
{
    public function __construct(
        private readonly AccountingAuditLogRepositoryInterface $logs,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(Model $subject, string $eventType, string $title, array $metadata = [], ?string $description = null): AccountingAuditLog
    {
        return $this->logs->create([
            'tenant_id' => $subject->tenant_id ?? tenant_id(),
            'company_id' => $subject->company_id ?? company_id(),
            'branch_id' => $subject->branch_id ?? branch_id(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event_type' => $eventType,
            'title' => $title,
            'description' => $description,
            'triggered_by' => auth()->id(),
            'metadata' => $metadata,
        ]);
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->logs->paginate($filters, $perPage);
    }
}
