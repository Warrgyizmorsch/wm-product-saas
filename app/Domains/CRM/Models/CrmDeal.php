<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Sales\Models\SalesOrder;

class CrmDeal extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_deals';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($deal) {
            if (empty($deal->deal_number)) {
                $deal->deal_number = 'DL-' . date('Y') . '-' . str_pad((string)(static::whereYear('created_at', date('Y'))->count() + 1), 5, '0', STR_PAD_LEFT);
            }
            if (empty($deal->owner_id) && auth()->check()) {
                $deal->owner_id = auth()->id();
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'crm_account_id',
        'crm_contact_id',
        'deal_number',
        'title',
        'estimated_value',
        'stage', // Qualification, Needs Analysis, Proposal, Negotiation, Closed Won, Closed Lost
        'close_reason', // Lost: Competitor, Price, Budget. Won: Lowest Price, Relationship, Best Delivery
        'closing_date',
        'lead_source',
        'probability',
        'owner_id',
        'notes',
    ];

    protected $casts = [
        'closing_date'    => 'date',
        'estimated_value' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'crm_deal_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'crm_deal_id');
    }

    public function getActualValueAttribute(): float
    {
        $acceptedQuote = $this->quotations()->where('status', 'Accepted')->first();
        if ($acceptedQuote) {
            return (float) $acceptedQuote->total_amount;
        }
        return (float) $this->estimated_value;
    }
}
