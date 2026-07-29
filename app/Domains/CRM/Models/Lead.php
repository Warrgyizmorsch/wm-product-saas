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
        });
    }

    protected $fillable = [
        'tenant_id',
        'lead_owner_id',
        'call_date',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'requirement',
        'expected_amount',
        'expected_sale_date',
        'source',
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
        return $this->hasMany(LeadFollowup::class)->orderBy('followup_date', 'desc');
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
}
