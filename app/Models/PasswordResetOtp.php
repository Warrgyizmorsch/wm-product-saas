<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $table = 'password_reset_otps';

    protected $fillable = [
        'email',
        'otp_hash',
        'reset_token',
        'attempts',
        'expires_at',
        'reset_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
        ];
    }

    public function isOtpExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isResetTokenExpired(): bool
    {
        return $this->reset_token_expires_at ? $this->reset_token_expires_at->isPast() : true;
    }
}

