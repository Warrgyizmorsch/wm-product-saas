<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Concerns\HasPartyVerification;
use App\Models\Concerns\NormalizesTaxIdentity;
use App\Services\TaxIdentityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Polymorphic branch / site / billing / delivery location for a Customer or Vendor (FRS 2.3 / C-17).
 *
 * Intentionally NOT scoped by BelongsToBranch so a customer's or vendor's branches remain visible
 * across all of our own company branches within the same tenant.
 */
class PartyBranch extends BaseModel
{
    use SoftDeletes, NormalizesTaxIdentity, HasPartyVerification;

    public const PARTY_CUSTOMER = 'customer';
    public const PARTY_VENDOR = 'vendor';

    public const TYPES = [
        'head_office' => 'Head Office',
        'branch' => 'Branch',
        'site' => 'Site',
        'warehouse' => 'Warehouse',
        'billing' => 'Billing',
        'delivery' => 'Delivery',
    ];

    protected $table = 'party_branches';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'party_type',
        'party_id',
        'name',
        'code',
        'branch_type',
        'gstin',
        'gst_state_code',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'is_billing',
        'is_shipping',
        'is_default_billing',
        'is_default_shipping',
        'is_head_office',
        'status',
    ];

    protected $casts = [
        'party_id' => 'integer',
        'is_billing' => 'boolean',
        'is_shipping' => 'boolean',
        'is_default_billing' => 'boolean',
        'is_default_shipping' => 'boolean',
        'is_head_office' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (PartyBranch $branch): void {
            if (! empty($branch->gstin)) {
                $normalized = TaxIdentityService::normalizeGstin($branch->gstin);
                $branch->gstin = $normalized;
                if ($normalized && empty($branch->gst_state_code)) {
                    $branch->gst_state_code = TaxIdentityService::extractStateCode($normalized);
                }
            }
        });
    }

    public function verificationPartyType(): string
    {
        return $this->party_type === self::PARTY_VENDOR ? 'vendor_branch' : 'customer_branch';
    }

    public function parentParty(): Customer|Vendor|null
    {
        if ($this->party_type === self::PARTY_CUSTOMER) {
            return Customer::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->find($this->party_id);
        }

        if ($this->party_type === self::PARTY_VENDOR) {
            return Vendor::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->find($this->party_id);
        }

        return null;
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class, 'party_branch_id');
    }

    public function scopeForParty(Builder $query, string $partyType, int $partyId): Builder
    {
        return $query->where('party_type', $partyType)->where('party_id', $partyId);
    }

    public function scopeActiveVerified(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->where('verification_status', self::VERIFICATION_VERIFIED)
                    ->orWhereNull('verification_status');
            });
    }

    public function effectiveGstin(): ?string
    {
        $own = TaxIdentityService::normalizeGstin($this->gstin);
        if ($own) {
            return $own;
        }

        return TaxIdentityService::normalizeGstin($this->parentParty()?->gstin);
    }

    public function effectiveStateCode(): ?string
    {
        if (! empty($this->gst_state_code)) {
            return str_pad((string) $this->gst_state_code, 2, '0', STR_PAD_LEFT);
        }

        $fromGstin = TaxIdentityService::extractStateCode($this->gstin);
        if ($fromGstin) {
            return $fromGstin;
        }

        if (! empty($this->state)) {
            $found = \App\Domains\Accounting\Support\GstStates::findInText($this->state);
            if ($found) {
                return $found;
            }
        }

        return TaxIdentityService::extractStateCode($this->parentParty()?->gstin);
    }

    public function formattedAddress(): string
    {
        $parts = array_filter([
            trim((string) ($this->address_line1 ?? '')),
            trim((string) ($this->address_line2 ?? '')),
            trim((string) ($this->city ?? '')),
            trim((string) ($this->state ?? '')),
            trim((string) ($this->postal_code ?? '')),
            trim((string) ($this->country ?? '')),
        ], fn (string $v) => $v !== '');

        return implode(', ', $parts);
    }
}
