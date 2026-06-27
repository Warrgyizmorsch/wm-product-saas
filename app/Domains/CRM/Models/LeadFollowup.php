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
    ];

    protected $casts = [
        'followup_date' => 'datetime',
    ];

    /**
     * Get the lead that owns the followup.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
