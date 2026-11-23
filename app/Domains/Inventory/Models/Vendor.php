<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasPartyStatusControl;
use App\Models\Concerns\HasPartyVerification;
use App\Models\Concerns\NormalizesTaxIdentity;
use App\Models\Concerns\ProtectsBankDetails;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Services\PartyStructureService;

class Vendor extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, NormalizesTaxIdentity, HasPartyVerification, HasPartyStatusControl, ProtectsBankDetails;

    protected $table = 'vendors';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'company_name',
        'code',
        'email',
        'phone',
        'address',
        'billing_address',
        'shipping_address',
        'gstin',
        'pan',
        'bank_name',
        'account_number',
        'ifsc_code',
        'payment_terms',
        'opening_balance',
        'opening_balance_type',
        'opening_bills',
        'status',
        'merged_into_id',
        'merged_at',
        'merged_by',
        // TDS (see App\Domains\Accounting\Services\Tds\TdsCalculator)
        'tds_applicable',
        'tds_section_id',
        'deductee_type',
        'ldc_number',
        'ldc_rate',
        'ldc_valid_from',
        'ldc_valid_to',
        'ldc_limit',
    ];

    protected $casts = [
        'opening_bills' => 'array',
        'merged_into_id' => 'integer',
        'merged_at' => 'datetime',
        'merged_by' => 'integer',
        'tds_applicable' => 'boolean',
        'ldc_rate' => 'float',
        'ldc_limit' => 'float',
        'ldc_valid_from' => 'date',
        'ldc_valid_to' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Vendor $vendor): void {
            app(\App\Services\PartyDeletionGuard::class)->assertCanDelete($vendor);

            $vendor->branches()->delete();
            $vendor->contacts()->delete();
        });

        static::saved(function (Vendor $vendor): void {
            app(PartyStructureService::class)->syncPrimaryBranch($vendor);
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(PartyBranch::class, 'party_id')
            ->where('party_type', PartyBranch::PARTY_VENDOR);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class, 'party_id')
            ->where('party_type', PartyContact::PARTY_VENDOR);
    }

    public function headOfficeBranch(): ?PartyBranch
    {
        return $this->branches()->where('is_head_office', true)->first();
    }

    public function defaultBillingBranch(): ?PartyBranch
    {
        return $this->branches()->activeVerified()->where('is_billing', true)->orderByDesc('is_default_billing')->orderByDesc('is_head_office')->first();
    }

    public function defaultShippingBranch(): ?PartyBranch
    {
        return $this->branches()->activeVerified()->where('is_shipping', true)->orderByDesc('is_default_shipping')->orderByDesc('is_head_office')->first();
    }

    public function operations(): HasMany
    {
        return $this->hasMany(\App\Domains\Production\Models\ProductionOrderOperation::class, 'vendor_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\VendorBill::class, 'vendor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\VendorPayment::class, 'vendor_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\PurchaseOrder::class, 'vendor_id');
    }

    public function advancePayments(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\PurchaseAdvancePayment::class, 'vendor_id');
    }

    /**
     * Opening balance as this party's statements show it: an amount owed to the vendor (credit) is positive; an advance paid (debit) is negative.
     */
    public function signedOpeningBalance(): float
    {
        $amount = (float) ($this->opening_balance ?? 0);

        return ($this->opening_balance_type ?: 'credit') === 'credit' ? $amount : -$amount;
    }

    public function mergedInto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedDuplicates(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    public function merger(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'merged_by');
    }

    public function isMerged(): bool
    {
        return ! empty($this->merged_into_id);
    }

    public function vendorItems(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\VendorItem::class, 'vendor_id');
    }

    public function vendorDocuments(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\VendorDocument::class, 'vendor_id')->orderBy('expiry_date');
    }

    public function expiredDocuments()
    {
        return $this->vendorDocuments()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->get();
    }

    public function expiringDocuments(int $days = 30)
    {
        return $this->vendorDocuments()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->get();
    }

    public function hasDocumentAlerts(): bool
    {
        return $this->expiredDocuments()->isNotEmpty() || $this->expiringDocuments(30)->isNotEmpty();
    }
}
