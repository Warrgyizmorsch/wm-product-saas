<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of the sync log. Keeps the payload so a failed record can be retried. */
class IntegrationRecord extends BaseModel
{
    public const OUTCOME_CREATED = 'created';
    public const OUTCOME_UPDATED = 'updated';
    public const OUTCOME_UNCHANGED = 'unchanged';
    public const OUTCOME_PENDING_APPROVAL = 'pending_approval';
    public const OUTCOME_FAILED = 'failed';
    public const OUTCOME_CONFLICT = 'conflict';

    protected $table = 'accounting_integration_records';

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'entity_type',
        'external_id',
        'local_type',
        'local_id',
        'outcome',
        'error_code',
        'error',
        'attempts',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(IntegrationBatch::class, 'batch_id');
    }
}
