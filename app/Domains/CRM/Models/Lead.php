<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use HasFactory, SoftDeletes;

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
        'product',
        'status',
        'next_followup_date',
        'is_customer',
    ];

    protected $casts = [
        'call_date' => 'datetime',
        'expected_sale_date' => 'date',
        'expected_amount' => 'decimal:2',
        'next_followup_date' => 'datetime',
        'is_customer' => 'boolean',
    ];

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
}
