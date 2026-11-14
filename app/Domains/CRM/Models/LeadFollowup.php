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
        'assigned_to_id',
        'google_event_id',
        'google_meet_link',
        'is_google_meet',
        'title',
        'duration_minutes',
        'guest_emails',
        'followup_date',
        'type',
        'status',
        'notes',
        'tagged_user_id',
        'tagged_user_ids',
        'rescheduled_from_id',
        'original_followup_date',
        'recording_url',
        'audio_duration',
        'latitude',
        'longitude',
        'location_address',
        'checkin_latitude',
        'checkin_longitude',
        'checkin_address',
        'checkin_distance_meters',
        'checkin_status',
        'checkin_at',
        'selfie_path',
        'location_verified_at',
        'advance_reminder_sent_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'assigned_to_id' => 'integer',
        'followup_date' => 'datetime',
        'original_followup_date' => 'datetime',
        'advance_reminder_sent_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'tagged_user_ids' => 'array',
        'is_google_meet' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'checkin_latitude' => 'float',
        'checkin_longitude' => 'float',
        'checkin_distance_meters' => 'integer',
        'checkin_at' => 'datetime',
        'location_verified_at' => 'datetime',
    ];

    protected $appends = [
        'selfie_url',
    ];

    public function getSelfieUrlAttribute(): ?string
    {
        if (empty($this->selfie_path)) {
            return null;
        }

        if (str_starts_with($this->selfie_path, 'http://') || str_starts_with($this->selfie_path, 'https://')) {
            return $this->selfie_path;
        }

        $path = ltrim($this->selfie_path, '/');
        while (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        return asset('storage/' . $path);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (LeadFollowup $model) {
            if (empty($model->assigned_to_id)) {
                if (!empty($model->lead_id)) {
                    $lead = $model->lead ?: Lead::find($model->lead_id);
                    if ($lead && !empty($lead->lead_owner_id)) {
                        $model->assigned_to_id = $lead->lead_owner_id;
                    }
                } elseif (!empty($model->crm_deal_id)) {
                    $deal = $model->deal ?: CrmDeal::find($model->crm_deal_id);
                    if ($deal && !empty($deal->owner_id)) {
                        $model->assigned_to_id = $deal->owner_id;
                    }
                }
                if (empty($model->assigned_to_id) && auth()->check()) {
                    $model->assigned_to_id = auth()->id();
                }
            }
        });
    }

    /**
     * Get the primary assigned user (host / responsible executive).
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_id');
    }

    /**
     * Get the effective assigned user (falls back to lead/deal owner or tagged user).
     */
    public function getAssignedUserAttribute(): ?\App\Models\User
    {
        return $this->assignedTo ?: ($this->lead?->owner ?: ($this->deal?->owner ?: $this->taggedUser));
    }

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
