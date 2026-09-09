<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountingAuditLog extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    public const UPDATED_AT = null;

    protected $table = 'accounting_audit_logs';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'subject_type',
        'subject_id',
        'event_type',
        'title',
        'description',
        'triggered_by',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
