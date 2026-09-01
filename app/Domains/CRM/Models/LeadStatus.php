<?php

namespace App\Domains\CRM\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LeadStatus extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'lead_statuses';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'sort_order',
        'color',
        'is_protected',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_protected' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Default protected statuses that can never be deleted or renamed.
     */
    public const PROTECTED_STATUSES = ['New', 'Qualified', 'Won', 'Lost'];

    /**
     * Helper method to retrieve ordered statuses for a tenant.
     * Auto-seeds defaults if table is empty for the tenant.
     */
    public static function getOrderedStatuses(?int $tenantId = null): Collection
    {
        $tenantId = $tenantId ?? (tenant_id() ?? 1);

        $statuses = static::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($statuses->isEmpty()) {
            static::seedDefaultsForTenant($tenantId);
            $statuses = static::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        return $statuses;
    }

    /**
     * Seed default core statuses for a given tenant.
     */
    public static function seedDefaultsForTenant(int $tenantId): void
    {
        $defaults = [
            ['name' => 'New',       'sort_order' => 1, 'color' => 'bg-primary'],
            ['name' => 'Qualified', 'sort_order' => 2, 'color' => 'bg-teal'],
            ['name' => 'Won',       'sort_order' => 3, 'color' => 'bg-success'],
            ['name' => 'Lost',      'sort_order' => 4, 'color' => 'bg-danger'],
        ];

        foreach ($defaults as $def) {
            static::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $def['name']],
                [
                    'sort_order' => $def['sort_order'],
                    'color' => $def['color'],
                    'is_protected' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Check if this status is a system protected status.
     */
    public function isProtected(): bool
    {
        return $this->is_protected || in_array(trim($this->name), self::PROTECTED_STATUSES, true);
    }
}
