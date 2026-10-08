<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDeviceToken extends BaseModel
{
    protected $table = 'user_device_tokens';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'fcm_token',
        'token_hash',
        'device_type',
        'device_name',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $model) {
            if (!empty($model->fcm_token)) {
                $model->token_hash = hash('sha256', trim($model->fcm_token));
            }
        });
    }
}
