<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use App\Models\Concerns\HasPartyStatusControl;
use App\Models\Concerns\HasPartyVerification;
use App\Models\Concerns\NormalizesTaxIdentity;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Models\User;
use App\Services\PartyStructureService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, NormalizesTaxIdentity, HasPartyVerification, HasPartyStatusControl;

    protected ?string $rbacPermission = 'crm.customers.view';

    public function applyRbacScope(Builder $builder, User $user): void
    {
        $builder->whereHas('crmAccount', fn($aq) => $aq->where('owner_id', $user->id));
    }

    /**
     * Set to TRUE before Customer::create() inside deal conversion
     * to prevent the boot hook from auto-creating a duplicate CrmAccount.
     * Reset to FALSE immediately after. Direct customer creation leaves
     * this as FALSE so the hook runs normally.
     */
    public static bool $skipAccountAutoCreate = false;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'company_name',
        'email',
        'phone',
        'gstin',
        'pan',
        'bank_name',
        'account_number',
        'ifsc_code',
        'status',
        'billing_address',
        'shipping_address',
        'opening_balance',
        'opening_balance_type',
        'opening_bills',
        'merged_into_id',
        'merged_at',
        'merged_by',
    ];

    protected $casts = [
        'opening_balance' => 'float',
        'opening_bills' => 'array',
        'merged_into_id' => 'integer',
        'merged_at' => 'datetime',
        'merged_by' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (Customer $customer): void {
            app(\App\Services\PartyDeletionGuard::class)->assertCanDelete($customer);

            foreach ($customer->crmAccounts as $account) {
                $account->delete();
            }
            $customer->branches()->delete();
            $customer->contacts()->delete();
        });

        static::created(function ($customer) {
            // Skip when called from deal conversion or direct account creation
            if (static::$skipAccountAutoCreate) {
                return;
            }

            $alreadyExists = CrmAccount::where('tenant_id', $customer->tenant_id)
                ->where('customer_id', $customer->id)
                ->exists();

            if (!$alreadyExists) {
                CrmAccount::create([
                    'tenant_id' => $customer->tenant_id,
                    'company_id' => $customer->company_id,
                    'branch_id' => $customer->branch_id,
                    'customer_id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'gstin' => $customer->gstin,
                    'billing_address' => $customer->billing_address ?? null,
                    'shipping_address' => $customer->shipping_address ?? null,
                    'status' => strtolower($customer->status ?: 'active') === 'active' ? 'active' : 'inactive',
                    'owner_id' => auth()->id() ?? 1,
                ]);
            }
        });
        static::saved(function (Customer $customer): void {
            app(PartyStructureService::class)->syncPrimaryBranch($customer);
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(PartyBranch::class, 'party_id')
            ->where('party_type', PartyBranch::PARTY_CUSTOMER);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartyContact::class, 'party_id')
            ->where('party_type', PartyContact::PARTY_CUSTOMER);
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

    public function crmAccount()
    {
        return $this->hasOne(CrmAccount::class, 'customer_id');
    }

    public function crmAccounts(): HasMany
    {
        return $this->hasMany(CrmAccount::class, 'customer_id');
    }

    public function deals()
    {
        return $this->hasManyThrough(
            CrmDeal::class,
            CrmAccount::class,
            'customer_id',
            'crm_account_id',
            'id',
            'id'
        );
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'customer_id');
    }

    /**
     * Opening balance as this party's statements show it: an amount owed by the customer (debit) is positive; an advance received (credit) is negative.
     */
    public function signedOpeningBalance(): float
    {
        $amount = (float) ($this->opening_balance ?? 0);

        return ($this->opening_balance_type ?: 'debit') === 'debit' ? $amount : -$amount;
    }

    public function mergedInto()
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    public function mergedDuplicates()
    {
        return $this->hasMany(self::class, 'merged_into_id');
    }

    public function merger()
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function isMerged(): bool
    {
        return ! empty($this->merged_into_id);
    }
}
