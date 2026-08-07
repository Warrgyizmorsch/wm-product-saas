<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
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
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected function quotationNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => 'QT-' . $value,
            set: fn ($value) => str_replace('QT-', '', $value),
        );
    }

    protected $fillable = [
        'tenant_id',
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
            if ($account->customer_id && $account->customer) {
                return $account->customer;
            }

            $customer = null;
            if ($account->email) {
                $customer = Customer::where('email', $account->email)->first();
            }
            if (!$customer && $account->phone) {
                $customer = Customer::where('phone', $account->phone)->first();
            }
            if (!$customer && $account->name) {
                $customer = Customer::where('name', $account->name)->first();
            }

            if (!$customer && $account->name) {
                $customer = Customer::create([
                    'tenant_id' => $account->tenant_id ?? (auth()->check() ? auth()->user()->tenant_id : null),
                    'name'      => $account->name,
                    'email'     => $account->email,
                    'phone'     => $account->phone,
                    'gstin'     => $account->gstin,
                    'status'    => 'active',
                ]);
                $account->update(['customer_id' => $customer->id]);
            }

            return $customer;
        }

        // 2. Deal Contact link (if Deal has contact instead of account)
        if ($this->crmDeal && $this->crmDeal->contact) {
            $contact = $this->crmDeal->contact;
            $customer = null;
            if ($contact->email) {
                $customer = Customer::where('email', $contact->email)->first();
            }
            if (!$customer && $contact->phone) {
                $customer = Customer::where('phone', $contact->phone)->first();
            }
            if (!$customer && $contact->name) {
                $customer = Customer::where('name', $contact->name)->first();
            }

            if (!$customer && $contact->name) {
                $customer = Customer::create([
                    'tenant_id' => $contact->tenant_id ?? (auth()->check() ? auth()->user()->tenant_id : null),
                    'name'      => $contact->name,
                    'email'     => $contact->email,
                    'phone'     => $contact->phone,
                    'status'    => 'active',
                ]);
            }

            return $customer;
        }

        // 3. Lead link
        $lead = $this->lead;
        if ($lead) {
            $customer = null;
            if ($lead->email) {
                $customer = Customer::where('email', $lead->email)->first();
            }
            if (!$customer && $lead->phone) {
                $customer = Customer::where('phone', $lead->phone)->first();
            }
            if (!$customer && $lead->company_name) {
                $customer = Customer::where('name', $lead->company_name)->first();
            }
            if (!$customer && $lead->name) {
                $customer = Customer::where('name', $lead->name)->first();
            }

            if (!$customer && ($lead->company_name || $lead->name)) {
                $custName = $lead->company_name ?: $lead->name;
                $customer = Customer::create([
                    'tenant_id' => $lead->tenant_id ?? (auth()->check() ? auth()->user()->tenant_id : null),
                    'name'      => $custName,
                    'email'     => $lead->email,
                    'phone'     => $lead->phone,
                    'status'    => 'active',
                ]);
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
        }
        if ($this->crmAccount && $this->crmAccount->name) {
            return $this->crmAccount->name;
        }
        if ($this->lead) {
            if ($this->lead->company_name) return $this->lead->company_name;
            if ($this->lead->name) return $this->lead->name;
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
        }
        if ($this->crmAccount && $this->crmAccount->email) {
            return $this->crmAccount->email;
        }
        if ($this->lead && $this->lead->email) {
            return $this->lead->email;
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
        }
        if ($this->crmAccount && $this->crmAccount->phone) {
            return $this->crmAccount->phone;
        }
        if ($this->lead && $this->lead->phone) {
            return $this->lead->phone;
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

        if ($this->lead) {
            $parts = array_filter([$this->lead->address, $this->lead->city, $this->lead->state, $this->lead->country]);
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
