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
        $lead = $this->lead;
        if ($lead) {
            $customer = null;
            if ($lead->email) {
                $customer = Customer::where('email', $lead->email)->first();
            }
            if (!$customer && $lead->phone) {
                $customer = Customer::where('phone', $lead->phone)->first();
            }
            return $customer;
        }
        return null;
    }

    public function getCustomerIdAttribute(): ?int
    {
        return $this->customer?->id;
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
