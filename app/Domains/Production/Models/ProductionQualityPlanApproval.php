<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QR-07: immutable audit log of a Quality Plan's lifecycle (same pattern as RoutingApproval).
 */
class ProductionQualityPlanApproval extends BaseModel
{
    protected $table = 'production_quality_plan_approvals';

    const UPDATED_AT = null;

    public const ACTION_CREATED          = 'Created';
    public const ACTION_VERSION_CREATED  = 'Version Created';
    public const ACTION_UPDATED          = 'Updated';
    public const ACTION_SUBMITTED        = 'Submitted';
    public const ACTION_APPROVED         = 'Approved';
    public const ACTION_REJECTED         = 'Rejected';
    public const ACTION_HISTORICAL       = 'Marked Historical';
    public const ACTION_DELETED          = 'Deleted';

    protected $fillable = [
        'tenant_id',
        'quality_plan_id',
        'user_id',
        'action',
        'comments',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id')->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
