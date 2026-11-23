<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Bank-detail protection for vendors (FRS 2.3, C-16 bank part).
 *
 * Once a vendor's bank details are locked, they can only change inside
 * App\Services\PartyBankChangeService (which sets $bankChangeApproved while it
 * applies an approved request). Any other attempt to change them, from a form,
 * API, import or code, is reverted here so there is no side door.
 */
trait ProtectsBankDetails
{
    /** Set to true only by PartyBankChangeService while it applies an approved request. */
    public static bool $bankChangeApproved = false;

    public static function bootProtectsBankDetails(): void
    {
        static::creating(function ($vendor): void {
            // A vendor that starts unverified has no verified bank details to protect yet:
            // its first details are entered and verified normally, then it locks.
            if (($vendor->verification_status ?? 'verified') !== 'verified'
                && $vendor->getAttribute('bank_details_locked') === null) {
                $vendor->setAttribute('bank_details_locked', false);
            }
        });

        static::updating(function ($vendor): void {
            if (static::$bankChangeApproved
                || ! config('party_verification.enabled', true)
                || ! config('party_verification.bank_change_enabled', true)
                || ! ($vendor->getOriginal('bank_details_locked') ?? true)) {
                return;
            }

            $reverted = [];
            foreach (config('party_verification.bank_fields', []) as $field) {
                if ($vendor->isDirty($field)) {
                    $vendor->setAttribute($field, $vendor->getOriginal($field));
                    $reverted[] = $field;
                }
            }

            if ($reverted !== []) {
                Log::warning('Blocked a direct change to locked vendor bank details; use a bank change request.', [
                    'vendor_id' => $vendor->getKey(),
                    'fields' => $reverted,
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }
}
