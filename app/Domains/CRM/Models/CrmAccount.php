<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\Invoice;
use App\Models\Concerns\NormalizesTaxIdentity;
use App\Models\PartyContact;
use App\Services\PartyStructureService;

class CrmAccount extends BaseModel
{
    use HasFactory, SoftDeletes, BelongsToCompany, BelongsToBranch, NormalizesTaxIdentity;

    protected ?string $rbacPermission = 'crm.customers.view';
    protected ?string $rbacOwnerColumn = 'owner_id';

    protected $table = 'crm_accounts';

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (CrmAccount $account): void {
            app(\App\Services\PartyDeletionGuard::class)->assertCanDelete($account);
        });

        static::creating(function ($account) {
            if (empty($account->account_number)) {
                $account->account_number = 'ACC-' . date('Y') . '-' . str_pad((string)(static::whereYear('created_at', date('Y'))->count() + 1), 4, '0', STR_PAD_LEFT);
            }
            if (empty($account->owner_id) && auth()->check()) {
                $account->owner_id = auth()->id();
            }
        });

        static::saved(function (CrmAccount $account): void {
            if ($account->customer_id && $account->wasChanged('customer_id')) {
                $customer = Customer::withoutGlobalScopes()->find($account->customer_id);
                if ($customer) {
                    app(PartyStructureService::class)->migrateAccountContactsToCustomer($account, $customer);
                }
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_id',
        'account_number',
        'name',
        'gstin',
        'email',
        'phone',
        'website',
        'industry_type',
        'credit_limit',
        'street',
        'city',
        'state',
        'country',
        'zip_code',
        'status',
        'owner_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function getOpeningBalanceAttribute(): float
    {
        return (float) ($this->customer?->signedOpeningBalance() ?? 0.00);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function contacts(): HasMany
    {
        if ($this->customer_id) {
            return $this->hasMany(PartyContact::class, 'party_id', 'customer_id')
                ->where('party_type', PartyContact::PARTY_CUSTOMER);
        }

        return $this->hasMany(PartyContact::class, 'party_id', 'id')
            ->where('party_type', PartyContact::PARTY_CRM_ACCOUNT);
    }

    public function primaryContact()
    {
        if ($this->customer_id) {
            return $this->hasOne(PartyContact::class, 'party_id', 'customer_id')
                ->where('party_type', PartyContact::PARTY_CUSTOMER)
                ->where('is_primary', true);
        }

        return $this->hasOne(PartyContact::class, 'party_id', 'id')
            ->where('party_type', PartyContact::PARTY_CRM_ACCOUNT)
            ->where('is_primary', true);
    }

    public function legacyContacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'crm_account_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CrmDeal::class, 'crm_account_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'crm_account_id');
    }

    public function salesOrders(): HasMany
    {
        if ($this->customer_id) {
            return $this->hasMany(SalesOrder::class, 'customer_id', 'customer_id');
        }
        return $this->hasMany(SalesOrder::class, 'crm_account_id', 'id');
    }

    public function getLifetimeRevenueAttribute(): float
    {
        if ($this->customer_id) {
            return (float) SalesOrder::where('customer_id', $this->customer_id)
                ->whereNotIn('status', ['Cancelled', 'Draft'])
                ->sum('total_amount');
        }
        return (float) $this->deals()->where('stage', 'Closed Won')->sum('estimated_value');
    }

    public function getOpenDealsCountAttribute(): int
    {
        return $this->deals()->whereNotIn('stage', ['Closed Won', 'Closed Lost'])->count();
    }

    public function getWonDealsCountAttribute(): int
    {
        return $this->deals()->where('stage', 'Closed Won')->count();
    }

    public function getLastPurchaseDateAttribute()
    {
        if ($this->customer_id) {
            $so = SalesOrder::where('customer_id', $this->customer_id)->latest()->first();
            return $so ? $so->created_at : null;
        }
        return null;
    }
}
