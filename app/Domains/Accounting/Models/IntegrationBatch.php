<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One sync run for one entity type: the header of the sync log. */
class IntegrationBatch extends BaseModel
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ISSUES = 'completed_with_issues';

    protected $table = 'accounting_integration_batches';

    protected $fillable = [
        'tenant_id',
        'connection_id',
        'direction',
        'entity_type',
        'channel',
        'idempotency_key',
        'request_hash',
        'status',
        'total',
        'created_count',
        'updated_count',
        'unchanged_count',
        'pending_approval_count',
        'failed_count',
        'conflict_count',
        'created_by',
        'finished_at',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(IntegrationRecord::class, 'batch_id');
    }
}
