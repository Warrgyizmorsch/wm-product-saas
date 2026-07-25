<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFollowup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'followup_date',
        'type',
        'status',
        'notes',
        'rescheduled_from_id',
        'original_followup_date',
    ];

    protected $casts = [
        'followup_date' => 'datetime',
        'original_followup_date' => 'datetime',
    ];

    /**
     * Get the lead that owns the followup.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
