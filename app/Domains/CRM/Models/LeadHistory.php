<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class LeadHistory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'user_id',
        'event_type',
        'old_value',
        'new_value',
        'notes',
    ];

    /**
     * Get the lead associated with this history event.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who performed this event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log a lead history event.
     */
    public static function logEvent(Lead $lead, string $eventType, ?string $oldValue = null, ?string $newValue = null, ?string $notes = null): void
    {
        self::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'event_type' => $eventType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'notes' => $notes,
        ]);
    }
}
