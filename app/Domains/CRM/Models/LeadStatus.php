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

    /** Default masters are seeded without a tenant/company/branch and shown in every one. */
    public bool $sharedAcrossTenants = true;
    public bool $sharedAcrossCompanies = true;

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
    public const PROTECTED_STATUSES = ['New', 'Qualified', 'Dealing', 'Won', 'Lost'];

    /**
     * Helper method to retrieve ordered statuses for the active tenant context.
     * System statuses (tenant_id IS NULL) and tenant custom statuses will both be returned.
     */
    public static function getOrderedStatuses(?int $tenantId = null): Collection
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Seed default core statuses for the whole system (tenant_id = null).
     */
    public static function seedSystemDefaults(): void
    {
        $defaults = [
            ['name' => 'New',       'sort_order' => 1, 'color' => 'bg-primary'],
            ['name' => 'Qualified', 'sort_order' => 2, 'color' => 'bg-teal'],
            ['name' => 'Dealing',   'sort_order' => 3, 'color' => 'bg-info'],
            ['name' => 'Won',       'sort_order' => 4, 'color' => 'bg-success'],
            ['name' => 'Lost',      'sort_order' => 5, 'color' => 'bg-danger'],
        ];

        foreach ($defaults as $def) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => null, 'name' => $def['name']],
                [
                    'company_id'   => null,
                    'branch_id'    => null,
                    'sort_order'   => $def['sort_order'],
                    'color'        => $def['color'],
                    'is_protected' => true,
                    'is_active'    => true,
                ]
            );
        }
    }

    /**
     * Check if this status is a system protected status.
     */
    public function isProtected(): bool
    {
        return $this->tenant_id === null || $this->is_protected || in_array(trim($this->name), self::PROTECTED_STATUSES, true);
    }
}
