<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected function quotationNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'QT-' . $value,
            set: fn ($value) => str_replace('QT-', '', $value),
        );
    }

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'lead_id',        // Links this quotation to a specific lead (not just email)
        'crm_account_id',
        'crm_deal_id',
        'parent_id',      // Root parent quotation ID
        'revision_number',// Revision count (0 = original, 1 = R1, etc)
        'is_current',     // Active current revision status
        'sales_person_id',
        'quotation_number',
        'quotation_date',
        'expiry_date',
        'status',
        'subtotal',
        'tax',
        'discount',
        'total_amount',
        'terms_conditions',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'quotation_date'  => 'date',
        'expiry_date'     => 'date',
        'subtotal'        => 'decimal:2',
        'tax'             => 'decimal:2',
        'discount'        => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'is_current'      => 'boolean',
        'revision_number' => 'integer',
    ];

    public function getCustomerAttribute(): ?Customer
    {
        // 1. Direct CRM Account link or Account via Deal
        $account = $this->crmAccount;
        if (!$account && $this->crmDeal) {
            $account = $this->crmDeal->account;
        }

        if ($account) {
            // If the account already has a linked customer, use it directly
            if ($account->customer_id && $account->customer) {
                return $account->customer;
            }

            // READ-ONLY lookup — never create here; creation happens in QuotationService
            $tenantId = $account->tenant_id;
            $customer = null;
            if ($account->email) {
                $customer = Customer::where('tenant_id', $tenantId)->where('email', $account->email)->first();
            }
            if (!$customer && $account->phone) {
                $customer = Customer::where('tenant_id', $tenantId)->where('phone', $account->phone)->first();
            }
            if (!$customer && $account->name) {
                $customer = Customer::where('tenant_id', $tenantId)->where('name', $account->name)->first();
            }

            return $customer;
        }

        // 2. Deal Contact link (if Deal has contact instead of account)
        if ($this->crmDeal && $this->crmDeal->contact) {
            $contact = $this->crmDeal->contact;
            $tenantId = $contact->tenant_id;
            $customer = null;
            if ($contact->email) {
                $customer = Customer::where('tenant_id', $tenantId)->where('email', $contact->email)->first();
            }
            if (!$customer && $contact->phone) {
                $customer = Customer::where('tenant_id', $tenantId)->where('phone', $contact->phone)->first();
            }
            if (!$customer && $contact->name) {
                $customer = Customer::where('tenant_id', $tenantId)->where('name', $contact->name)->first();
            }

            return $customer;
        }

        // 3. Lead link — read-only lookup only
        $lead = $this->lead;
        if ($lead) {
            $tenantId = $lead->tenant_id;
            $customer = null;
            if ($lead->company_email) {
                $customer = Customer::where('tenant_id', $tenantId)->where('email', $lead->company_email)->first();
            }
            if (!$customer && $lead->email) {
                $customer = Customer::where('tenant_id', $tenantId)->where('email', $lead->email)->first();
            }
            if (!$customer && $lead->company_phone) {
                $customer = Customer::where('tenant_id', $tenantId)->where('phone', $lead->company_phone)->first();
            }
            if (!$customer && $lead->phone) {
                $customer = Customer::where('tenant_id', $tenantId)->where('phone', $lead->phone)->first();
            }
            if (!$customer && $lead->company_name) {
                $customer = Customer::where('tenant_id', $tenantId)->where('name', $lead->company_name)->first();
            }

            return $customer;
        }

        return null;
    }

    public function getCustomerIdAttribute(): ?int
    {
        return $this->customer?->id;
    }

    public function getTaxAmountAttribute(): float
    {
        return (float) ($this->attributes['tax'] ?? 0);
    }

    public function getPreparedForNameAttribute(): string
    {
        if ($this->customer && $this->customer->name) {
            return $this->customer->name;
        }
        if ($this->crmDeal) {
            if ($this->crmDeal->account && $this->crmDeal->account->name) {
                return $this->crmDeal->account->name;
            }
            if ($this->crmDeal->contact && $this->crmDeal->contact->name) {
                return $this->crmDeal->contact->name;
            }
            if ($this->crmDeal->lead) {
                if ($this->crmDeal->lead->company_name) return $this->crmDeal->lead->company_name;
                if ($this->crmDeal->lead->contact_person) return $this->crmDeal->lead->contact_person;
            }
        }
        if ($this->crmAccount && $this->crmAccount->name) {
            return $this->crmAccount->name;
        }
        $leadObj = $this->lead ?: $this->crmDeal?->lead;
        if ($leadObj) {
            if ($leadObj->company_name) return $leadObj->company_name;
            if ($leadObj->contact_person) return $leadObj->contact_person;
            if ($leadObj->name) return $leadObj->name;
        }
        return '—';
    }

    public function getPreparedForEmailAttribute(): string
    {
        if ($this->customer && $this->customer->email) {
            return $this->customer->email;
        }
        if ($this->crmDeal) {
            if ($this->crmDeal->contact && $this->crmDeal->contact->email) {
                return $this->crmDeal->contact->email;
            }
            if ($this->crmDeal->account && $this->crmDeal->account->email) {
                return $this->crmDeal->account->email;
            }
            if ($this->crmDeal->lead && ($this->crmDeal->lead->company_email || $this->crmDeal->lead->email)) {
                return $this->crmDeal->lead->company_email ?: $this->crmDeal->lead->email;
            }
        }
        if ($this->crmAccount && $this->crmAccount->email) {
            return $this->crmAccount->email;
        }
        $leadObj = $this->lead ?: $this->crmDeal?->lead;
        if ($leadObj && ($leadObj->company_email || $leadObj->email)) {
            return $leadObj->company_email ?: $leadObj->email;
        }
        return '—';
    }

    public function getPreparedForPhoneAttribute(): string
    {
        if ($this->customer && $this->customer->phone) {
            return $this->customer->phone;
        }
        if ($this->crmDeal) {
            if ($this->crmDeal->contact && $this->crmDeal->contact->phone) {
                return $this->crmDeal->contact->phone;
            }
            if ($this->crmDeal->account && $this->crmDeal->account->phone) {
                return $this->crmDeal->account->phone;
            }
            if ($this->crmDeal->lead && ($this->crmDeal->lead->company_phone || $this->crmDeal->lead->phone)) {
                return $this->crmDeal->lead->company_phone ?: $this->crmDeal->lead->phone;
            }
        }
        if ($this->crmAccount && $this->crmAccount->phone) {
            return $this->crmAccount->phone;
        }
        $leadObj = $this->lead ?: $this->crmDeal?->lead;
        if ($leadObj && ($leadObj->company_phone || $leadObj->phone)) {
            return $leadObj->company_phone ?: $leadObj->phone;
        }
        return '—';
    }

    public function getPreparedForAddressAttribute(): ?string
    {
        if ($this->customer && !empty($this->customer->billing_address)) {
            return $this->customer->billing_address;
        }

        $acc = $this->crmAccount ?: $this->crmDeal?->account;
        if ($acc) {
            $parts = array_filter([$acc->street, $acc->city, $acc->state, $acc->country, $acc->zip_code]);
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }

        $leadObj = $this->lead ?: $this->crmDeal?->lead;
        if ($leadObj) {
            $parts = array_filter([$leadObj->address, $leadObj->city, $leadObj->state, $leadObj->country]);
            if (!empty($parts)) {
                return implode(', ', $parts);
            }
        }

        return null;
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id');
    }

    /**
     * The specific lead this quotation was created for.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function crmAccount(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    public function account(): BelongsTo
    {
        return $this->crmAccount();
    }

    public function crmDeal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function deal(): BelongsTo
    {
        return $this->crmDeal();
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'parent_id');
    }

    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'parent_id');
    }

    /**
     * Get the entire revision history chain (original + all revisions) sorted chronologically.
     */
    public function getRevisionHistory()
    {
        $rootId = $this->parent_id ?: $this->id;
        return self::query()
            ->where('id', $rootId)
            ->orWhere('parent_id', $rootId)
            ->orderBy('revision_number', 'desc')
            ->get();
    }
}
