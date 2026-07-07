<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

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
        'product_id',
        'status',
        'next_followup_date',
        'is_customer',
        'documents',
    ];

    protected $casts = [
        'call_date' => 'datetime',
        'expected_sale_date' => 'date',
        'expected_amount' => 'decimal:2',
        'next_followup_date' => 'datetime',
        'is_customer' => 'boolean',
        'product_id' => 'integer',
        'documents' => 'array',
    ];

    /**
     * Get the product interested.
     */
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'product_id');
    }

    /**
     * Get the owner (user) of the lead.
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'lead_owner_id');
    }

    /**
     * Get the follow-ups for the lead.
     */
    public function followups(): \Illuminate\Database\Eloquent\Relations\HasMany
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
