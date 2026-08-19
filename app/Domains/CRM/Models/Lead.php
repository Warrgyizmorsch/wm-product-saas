<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($lead) {
            if (empty($lead->lead_owner_id) && auth()->check()) {
                $lead->lead_owner_id = auth()->id();
            }
            if (empty($lead->lead_number)) {
                $tenantId = $lead->tenant_id ?? (tenant_id() ?? 1);
                $year = date('Y');
                $count = static::where('tenant_id', $tenantId)->whereYear('created_at', $year)->count() + 1;
                $lead->lead_number = 'LD-' . $year . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'lead_number',
        'lead_owner_id',
        'call_date',
        'company_name',
        'company_email',
        'company_phone',
        'gstin',
        'lead_type',
        'contact_person',
        'designation',
        'email',
        'phone',
        'requirement',
        'crm_account_id',
        'crm_contact_id',
        'crm_deal_id',
        'converted_at',
        'expected_amount',
        'expected_sale_date',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'priority',
        'segment',
        'industry_type',
        'country',
        'state',
        'city',
        'address',
        'product_ids',
        'product_items',
        'status',
        'next_followup_date',
        'is_customer',
        'documents',
        'additional_contacts',
    ];

    protected $casts = [
        'call_date' => 'datetime',
        'expected_sale_date' => 'date',
        'expected_amount' => 'decimal:2',
        'next_followup_date' => 'datetime',
        'is_customer' => 'boolean',
        'product_ids' => 'array',
        'product_items' => 'array',
        'documents' => 'array',
        'additional_contacts' => 'array',
    ];

    /**
     * Get all interested products models.
     */
    public function getProductsAttribute()
    {
        $ids = $this->product_ids ?: [];
        if (empty($ids) || !is_array($ids)) {
            return collect();
        }
        return Product::whereIn('id', $ids)->get();
    }

    /**
     * Helper to get comma separated product names.
     */
    public function getProductNamesAttribute()
    {
        $products = $this->products;
        if ($products->isEmpty()) {
            return '—';
        }
        return $products->pluck('name')->implode(', ');
    }

    /**
     * Get the owner (user) of the lead.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_owner_id');
    }

    /**
     * Get the follow-ups for the lead.
     */
    public function followups(): HasMany
    {
        return $this->hasMany(LeadFollowup::class)->whereNull('crm_deal_id')->orderBy('followup_date', 'desc');
    }

    /**
     * Get the history entries for the lead.
     */
    public function histories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeadHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the documents uploaded for this lead.
     */
    public function leadDocuments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeadDocument::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all quotations belonging to this specific lead (by lead_id).
     * Using lead_id prevents same-email leads from sharing quotations.
     */
    public function quotations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Helper to get quotations as a collection (used in status checks).
     */
    public function getQuotations()
    {
        return Quotation::where('lead_id', $this->id)->latest()->get();
    }

    /**
     * Get the linked customer record for this lead.
     * Looks up by email first, then phone.
     */
    public function getCustomer()
    {
        if ($this->email) {
            $customer = Customer::where('email', $this->email)->first();
            if ($customer) return $customer;
        }
        if ($this->phone) {
            $customer = Customer::where('phone', $this->phone)->first();
            if ($customer) return $customer;
        }
        return null;
    }

    public function crmAccount(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    public function crmContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function crmDeal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    public function setSourceAttribute($value)
    {
        $this->attributes['source'] = (empty($value) || in_array($value, ['Select an Option', 'Select an option', 'Select Option'], true)) ? null : $value;
    }

    public function setPriorityAttribute($value)
    {
        $this->attributes['priority'] = (empty($value) || in_array($value, ['Select an Option', 'Select an option', 'Select Option'], true)) ? null : $value;
    }

    public function setSegmentAttribute($value)
    {
        $this->attributes['segment'] = (empty($value) || in_array($value, ['Select an Option', 'Select an option', 'Select Option'], true)) ? null : $value;
    }

    public function setIndustryTypeAttribute($value)
    {
        $this->attributes['industry_type'] = (empty($value) || in_array($value, ['Select an Option', 'Select an option', 'Select Option'], true)) ? null : $value;
    }
}
