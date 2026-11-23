<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unified contact master shared across Customers, Vendors, and pre-conversion CRM Accounts (FRS 2.3 / C-17).
 *
 * Intentionally NOT scoped by BelongsToBranch so party contacts remain visible across all own branches.
 */
class PartyContact extends BaseModel
{
    use SoftDeletes;

    public const PARTY_CUSTOMER = 'customer';
    public const PARTY_VENDOR = 'vendor';
    public const PARTY_CRM_ACCOUNT = 'crm_account';

    protected $table = 'contacts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'party_type',
        'party_id',
        'party_branch_id',
        'legacy_crm_contact_id',
        'name',
        'designation',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'status',
    ];

    protected $casts = [
        'party_id' => 'integer',
        'party_branch_id' => 'integer',
        'legacy_crm_contact_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function partyBranch(): BelongsTo
    {
        return $this->belongsTo(PartyBranch::class, 'party_branch_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(ContactRole::class, 'contact_role_assignments', 'contact_id', 'contact_role_id')
            ->withTimestamps();
    }

    public function scopeForParty(Builder $query, string $partyType, int $partyId): Builder
    {
        return $query->where('party_type', $partyType)->where('party_id', $partyId);
    }

    /**
     * Backward-compatible accessor for views/JSON that previously read CrmContact::$role.
     */
    public function getRoleAttribute(): string
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        return $roles->pluck('name')->implode(', ');
    }
}
