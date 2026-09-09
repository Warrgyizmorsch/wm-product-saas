<?php

namespace App\Domains\HRMS\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'broadcast_number',
        'title',
        'category',
        'priority',
        'content',
        'target_type',
        'target_ids',
        'attachment_path',
        'banner_image_path',
        'is_acknowledgement_required',
        'allow_comments',
        'send_email',
        'send_push',
        'show_banner',
        'scheduled_at',
        'published_at',
        'expires_at',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'target_ids'                   => 'array',
        'is_acknowledgement_required' => 'boolean',
        'allow_comments'               => 'boolean',
        'send_email'                   => 'boolean',
        'send_push'                    => 'boolean',
        'show_banner'                  => 'boolean',
        'scheduled_at'                 => 'datetime',
        'published_at'                 => 'datetime',
        'expires_at'                   => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(BroadcastReceipt::class, 'broadcast_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BroadcastComment::class, 'broadcast_id');
    }

    /**
     * Compute read percentage.
     */
    public function getReadPercentageAttribute(): int
    {
        $total = $this->receipts()->count();
        if ($total === 0) {
            return 0;
        }
        $read = $this->receipts()->whereNotNull('read_at')->count();
        return (int) round(($read / $total) * 100);
    }

    /**
     * Compute acknowledgement percentage.
     */
    public function getAcknowledgementPercentageAttribute(): int
    {
        $total = $this->receipts()->count();
        if ($total === 0) {
            return 0;
        }
        $ack = $this->receipts()->whereNotNull('acknowledged_at')->count();
        return (int) round(($ack / $total) * 100);
    }
}
