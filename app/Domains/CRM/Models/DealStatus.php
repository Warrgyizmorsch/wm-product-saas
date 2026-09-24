<?php

namespace App\Domains\CRM\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DealStatus extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'deal_statuses';

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
        'probability',
        'is_protected',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'probability' => 'integer',
        'is_protected' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Default protected statuses that can never be deleted or renamed.
     */
    public const PROTECTED_STATUSES = ['Won', 'Lost', 'Closed Won', 'Closed Lost'];

    /**
     * Helper method to retrieve ordered deal statuses for the active tenant context.
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
     * Seed default core deal statuses for the whole system (tenant_id = null).
     */
    public static function seedSystemDefaults(): void
    {
        $defaults = [
            ['name' => 'Qualification',  'sort_order' => 1, 'color' => 'bg-primary', 'probability' => 10,  'is_protected' => false],
            ['name' => 'Needs Analysis', 'sort_order' => 2, 'color' => 'bg-info',    'probability' => 30,  'is_protected' => false],
            ['name' => 'Proposal',       'sort_order' => 3, 'color' => 'bg-warning', 'probability' => 60,  'is_protected' => false],
            ['name' => 'Negotiation',    'sort_order' => 4, 'color' => 'bg-dark',    'probability' => 80,  'is_protected' => false],
            ['name' => 'Won',            'sort_order' => 5, 'color' => 'bg-success', 'probability' => 100, 'is_protected' => true],
            ['name' => 'Lost',           'sort_order' => 6, 'color' => 'bg-danger',  'probability' => 0,   'is_protected' => true],
        ];

        foreach ($defaults as $def) {
            static::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => null, 'name' => $def['name']],
                [
                    'company_id'   => null,
                    'branch_id'    => null,
                    'sort_order'   => $def['sort_order'],
                    'color'        => $def['color'],
                    'probability'  => $def['probability'],
                    'is_protected' => $def['is_protected'],
                    'is_active'    => true,
                ]
            );
        }
    }

    /**
     * Check if this status is a system protected status (Won or Lost).
     */
    public function isProtected(): bool
    {
        return $this->tenant_id === null || $this->is_protected || in_array(trim($this->name), self::PROTECTED_STATUSES, true);
    }
}
