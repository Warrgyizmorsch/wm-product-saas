<?php

namespace App\Domains\CRM\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CrmLossReason extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'crm_loss_reasons';

    public bool $sharedAcrossTenants = true;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'requires_competitor',
        'follow_up_days',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_competitor' => 'boolean',
        'follow_up_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function getFollowUpDaysAttribute($value): int
    {
        return $value ? (int) $value : 30;
    }

    /**
     * Retrieve active loss reasons for the active tenant context.
     * System-wide reasons (tenant_id IS NULL) are returned along with tenant-specific reasons.
     */
    public static function getActiveReasons(?int $tenantId = null): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if (!empty($tenantId)) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }
}
