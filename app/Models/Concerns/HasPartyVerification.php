<?php

namespace App\Models\Concerns;

use App\Models\PartyVerificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared by Customer and Vendor (FRS 2.3, C-15).
 *
 * The one rule enforced here, so no code path can bypass it: a party that is
 * not verified can never be saved with status "active". Everything else (who
 * may verify, the no-self-approval rule, the audit trail) lives in
 * App\Services\PartyVerificationService.
 */
trait HasPartyVerification
{
    public const VERIFICATION_PENDING = 'pending_verification';
    public const VERIFICATION_VERIFIED = 'verified';
    public const VERIFICATION_RETURNED = 'returned';

    public static function bootHasPartyVerification(): void
    {
        static::creating(function ($party): void {
            if (empty($party->created_by) && auth()->check()) {
                $party->created_by = auth()->id();
            }
        });

        static::saving(function ($party): void {
            if (! config('party_verification.enabled', true)) {
                return;
            }

            $verification = $party->verification_status ?? self::VERIFICATION_VERIFIED;

            if ($verification !== self::VERIFICATION_VERIFIED && strtolower((string) $party->status) === 'active') {
                $party->status = 'inactive';
            }
        });
    }

    public function initializeHasPartyVerification(): void
    {
        $this->mergeFillable([
            'verification_status',
            'created_by',
            'submitted_by',
            'submitted_at',
            'verified_by',
            'verified_at',
            'verification_remarks',
            'verification_checks',
        ]);

        $this->mergeCasts([
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'verification_checks' => 'array',
        ]);
    }

    public function isVerified(): bool
    {
        return ($this->verification_status ?? self::VERIFICATION_VERIFIED) === self::VERIFICATION_VERIFIED;
    }

    public function isPendingVerification(): bool
    {
        return $this->verification_status === self::VERIFICATION_PENDING;
    }

    public function isReturnedForCorrection(): bool
    {
        return $this->verification_status === self::VERIFICATION_RETURNED;
    }

    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->whereIn($this->getTable() . '.verification_status', [
            self::VERIFICATION_PENDING,
            self::VERIFICATION_RETURNED,
        ]);
    }

    public function verificationLabel(): string
    {
        return match ($this->verification_status ?? self::VERIFICATION_VERIFIED) {
            self::VERIFICATION_PENDING => 'Pending verification',
            self::VERIFICATION_RETURNED => 'Returned for correction',
            default => 'Verified',
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** Party type key used in the audit log and routes. */
    public function verificationPartyType(): string
    {
        return $this instanceof \App\Domains\CRM\Models\Customer ? 'customer' : 'vendor';
    }

    public function verificationLogs()
    {
        return PartyVerificationLog::query()
            ->where('party_type', $this->verificationPartyType())
            ->where('party_id', $this->getKey())
            ->orderByDesc('id');
    }
}
