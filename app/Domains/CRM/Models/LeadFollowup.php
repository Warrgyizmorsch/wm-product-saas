<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowup extends Model
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'lead_id',
        'crm_deal_id',
        'followup_date',
        'type',
        'status',
        'notes',
        'tagged_user_id',
        'tagged_user_ids',
        'rescheduled_from_id',
        'original_followup_date',
    ];

    protected $casts = [
        'followup_date' => 'datetime',
        'original_followup_date' => 'datetime',
        'tagged_user_ids' => 'array',
    ];

    /**
     * Get all users tagged on this followup/activity (multi-select & single select compatible).
     */
    public function getTaggedUsersAttribute()
    {
        $ids = is_array($this->tagged_user_ids) ? array_filter($this->tagged_user_ids) : [];
        if (empty($ids) && $this->tagged_user_id) {
            $ids = [$this->tagged_user_id];
        }
        if (empty($ids)) {
            return collect();
        }
        return \App\Models\User::whereIn('id', $ids)->get();
    }

    /**
     * Get the user tagged on this followup/activity.
     */
    public function taggedUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'tagged_user_id');
    }

    /**
     * Get the lead that owns the followup.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the deal that owns the followup.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(CrmDeal::class, 'crm_deal_id');
    }

    /**
     * Get the original followup from which this was rescheduled.
     */
    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    /**
     * Get subsequent followups rescheduled from this one.
     */
    public function rescheduledTo()
    {
        return $this->hasMany(self::class, 'rescheduled_from_id');
    }
}
