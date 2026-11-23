<?php

namespace App\Models\Concerns;

use App\Models\PartyVerificationLog;
use Illuminate\Support\Facades\Log;

/**
 * Inactive reasons and reactivation control for customers and vendors
 * (FRS 2.3, C-16).
 *
 * Applies only to verified parties; the "unverified can never be active" rule
 * lives in HasPartyVerification. Rules enforced here for every code path:
 *  - becoming inactive always records a reason (service-supplied, or "other"
 *    when a legacy path changes status without one);
 *  - a party made inactive for a RISK reason cannot be reactivated except
 *    through App\Services\PartyStatusService (different authorised person);
 *  - every change is written to party_verification_logs.
 */
trait HasPartyStatusControl
{
    /** True only while PartyStatusService reactivates a risk-stopped party. */
    public static bool $riskReactivationApproved = false;

    /** Note supplied by the service for the audit row of the change being saved. */
    public static ?string $statusChangeNote = null;

    public function initializeHasPartyStatusControl(): void
    {
        $this->mergeFillable(['inactive_reason', 'inactive_note', 'inactivated_by', 'inactivated_at']);
        $this->mergeCasts(['inactivated_at' => 'datetime']);
    }

    public static function bootHasPartyStatusControl(): void
    {
        static::updating(function ($party): void {
            if (! self::partyStatusControlApplies($party) || ! $party->isDirty('status')) {
                return;
            }

            $from = strtolower((string) $party->getOriginal('status'));
            $to = strtolower((string) $party->status);

            if ($from === $to) {
                return;
            }

            if ($to === 'inactive') {
                if (! $party->isDirty('inactive_reason') || empty($party->inactive_reason)) {
                    $party->inactive_reason = 'other';
                    $party->inactive_note = $party->inactive_note ?: 'Made inactive without a recorded reason.';
                }
                if (! $party->isDirty('inactivated_by')) {
                    $party->inactivated_by = auth()->id();
                }
                if (! $party->isDirty('inactivated_at')) {
                    $party->inactivated_at = now();
                }

                return;
            }

            if ($to === 'active' && $from === 'inactive') {
                if (self::isRiskReason($party->getOriginal('inactive_reason')) && ! static::$riskReactivationApproved) {
                    $party->status = $party->getOriginal('status');

                    Log::warning('Blocked reactivation of a party stopped for a risk reason; use the reactivate action.', [
                        'party' => get_class($party),
                        'id' => $party->getKey(),
                        'user_id' => auth()->id(),
                    ]);

                    return;
                }

                $party->inactive_reason = null;
                $party->inactive_note = null;
                $party->inactivated_by = null;
                $party->inactivated_at = null;
            }
        });

        static::updated(function ($party): void {
            if (! self::partyStatusControlApplies($party) || ! $party->wasChanged('status')) {
                return;
            }

            $from = strtolower((string) $party->getOriginal('status'));
            $to = strtolower((string) $party->status);

            if ($from === $to || ! in_array($to, ['active', 'inactive'], true)) {
                return;
            }

            $reactivated = $to === 'active';

            PartyVerificationLog::query()->create([
                'tenant_id' => $party->tenant_id,
                'party_type' => $party->verificationPartyType(),
                'party_id' => $party->getKey(),
                'action' => $reactivated ? 'reactivated' : 'deactivated',
                'actor_id' => auth()->id(),
                'from_status' => $from,
                'to_status' => $to,
                'remarks' => static::$statusChangeNote ?? ($reactivated ? null : $party->inactive_note),
                'changes' => [
                    'reason' => $reactivated ? $party->getOriginal('inactive_reason') : $party->inactive_reason,
                ],
            ]);
        });
    }

    /** Only when the control is on and the party is verified before and after this save. */
    private static function partyStatusControlApplies($party): bool
    {
        return config('party_verification.enabled', true)
            && ($party->verification_status ?? 'verified') === 'verified'
            && ($party->getOriginal('verification_status') ?? 'verified') === 'verified';
    }

    public static function isRiskReason(?string $reason): bool
    {
        return $reason !== null && $reason !== ''
            && (bool) (config('party_verification.inactive_reasons')[$reason]['risk'] ?? false);
    }

    public function isInactiveForRisk(): bool
    {
        return strtolower((string) $this->status) !== 'active' && self::isRiskReason($this->inactive_reason);
    }

    public function inactivator()
    {
        return $this->belongsTo(\App\Models\User::class, 'inactivated_by');
    }
}
