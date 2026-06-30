<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingApproval extends BaseModel
{
    protected $table = 'production_routing_approvals';

    // Immutable audit log — no updated_at, no SoftDeletes
    const UPDATED_AT = null;

    // Action constants — match BOM approval pattern for consistency
    public const ACTION_CREATED          = 'Created';
    public const ACTION_SUBMITTED        = 'Submitted';
    public const ACTION_APPROVED         = 'Approved';
    public const ACTION_REJECTED         = 'Rejected';
    public const ACTION_CANCELLED        = 'Cancelled';
    public const ACTION_REVISION_CREATED = 'Revision Created';

    protected $fillable = [
        'tenant_id',
        'routing_id',
        'user_id',
        'action',
        'comments',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
