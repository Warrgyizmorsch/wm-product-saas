<?php

namespace App\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Models\PartyBranch;
use App\Models\PartyVerificationLog;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Second-person verification of customers and vendors (FRS 2.3, task C-15).
 *
 * Flow: a party is created as "pending_verification" and inactive. A different
 * authorised person checks the GSTIN, PAN and bank details against evidence and
 * either verifies it (it becomes active) or returns it with a reason. Editing a
 * sensitive field on a verified party sends it back to pending, so a single
 * person can never both change and approve the details.
 */
class PartyVerificationService
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('party_verification.enabled', true);
    }

    /** @return list<string> */
    public function sensitiveFields(): array
    {
        return config('party_verification.sensitive_fields', ['gstin', 'pan', 'phone', 'bank_name', 'account_number', 'ifsc_code']);
    }

    /**
     * Attributes to merge into the data of a brand new party so it starts as
     * pending and inactive. Empty when the control is switched off, which keeps
     * the old behaviour.
     *
     * @return array<string, mixed>
     */
    public function pendingAttributes(?User $actor = null): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $actor ??= auth()->user();

        return [
            'status' => 'inactive',
            'verification_status' => Customer::VERIFICATION_PENDING,
            'created_by' => $actor?->id,
            'submitted_by' => $actor?->id,
            'submitted_at' => now(),
        ];
    }

    /**
     * Record the "submitted" audit row for a party that was just created as
     * pending. Safe to call for parties that are not pending (no-op).
     */
    public function recordSubmission(Model $party, ?User $actor = null): void
    {
        if (! $this->enabled() || $party->verification_status !== Customer::VERIFICATION_PENDING) {
            return;
        }

        $this->log($party, 'submitted', $actor ?? auth()->user(), null, Customer::VERIFICATION_PENDING, null, [
            'new' => $this->sensitiveSnapshot($party),
        ]);
    }

    /**
     * Called by the update paths before saving edited data. Returns the extra
     * attributes to merge into the update.
     *
     * - A verified party whose GSTIN/PAN/bank details change goes back to pending
     *   (and inactive) with the editor recorded as the new submitter.
     * - A pending or returned party edited again keeps/refreshes its submitter,
     *   and a returned one goes back to pending.
     *
     * @param  array<string, mixed>  $incoming  validated request data
     * @return array<string, mixed>
     */
    public function attributesForUpdate(Model $party, array $incoming, ?User $actor = null): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $actor ??= auth()->user();
        $changes = $this->sensitiveChanges($party, $incoming);

        if ($party->isVerified()) {
            if ($changes === []) {
                return [];
            }

            $this->log($party, 'reverification_required', $actor, $party->verification_status, Customer::VERIFICATION_PENDING,
                'Sensitive details changed on a verified party.', ['changes' => $changes]);

            return [
                'status' => 'inactive',
                'verification_status' => Customer::VERIFICATION_PENDING,
                'submitted_by' => $actor?->id,
                'submitted_at' => now(),
                'verified_by' => null,
                'verified_at' => null,
                'verification_checks' => null,
            ];
        }

        if ($changes === []) {
            return [];
        }

        $extra = [
            'submitted_by' => $actor?->id,
            'submitted_at' => now(),
        ];

        if ($party->isReturnedForCorrection()) {
            $extra['verification_status'] = Customer::VERIFICATION_PENDING;
            $this->log($party, 'resubmitted', $actor, Customer::VERIFICATION_RETURNED, Customer::VERIFICATION_PENDING,
                'Details corrected and resubmitted.', ['changes' => $changes]);
        }

        return $extra;
    }

    /**
     * Fields in $incoming (from the sensitive list) whose normalised value
     * differs from what is stored.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function sensitiveChanges(Model $party, array $incoming): array
    {
        $changes = [];

        foreach ($this->sensitiveFields() as $field) {
            if (! array_key_exists($field, $incoming)) {
                continue;
            }

            $old = $this->normalise($party->getAttribute($field));
            $new = $this->normalise($incoming[$field]);

            if ($old !== $new) {
                $changes[$field] = ['old' => $party->getAttribute($field), 'new' => $incoming[$field]];
            }
        }

        return $changes;
    }

    public function canVerify(?User $user, Model $party): bool
    {
        if (! $user) {
            return false;
        }

        return $this->access->allows($user, $this->permissionFor($party), [
            'tenant_id' => $party->tenant_id,
            'company_id' => $party->company_id ?? null,
            'branch_id' => $party->branch_id ?? null,
        ]);
    }

    /** True when this user took part in creating or editing the details, so may not approve them. */
    public function isMaker(?User $user, Model $party): bool
    {
        if (! $user) {
            return false;
        }

        $id = (int) $user->id;

        return ($party->created_by !== null && (int) $party->created_by === $id)
            || ($party->submitted_by !== null && (int) $party->submitted_by === $id);
    }

    /**
     * Verify a pending party. The caller must already have checked
     * canVerify(); the no-self-approval rule is enforced here.
     *
     * @param  array<string, mixed>  $checks  keys gstin, pan, bank => truthy when the verifier confirmed it
     */
    public function verify(Model $party, User $verifier, array $checks = [], ?string $remarks = null): Model
    {
        if (! $party->isPendingVerification()) {
            throw new PartyVerificationException('Only a party that is pending verification can be verified.');
        }

        if ($this->isMaker($verifier, $party)) {
            throw new PartyVerificationException('You created or last edited these details, so a different person must verify them.');
        }

        $required = $this->requiredChecks($party);
        $missing = array_values(array_filter($required, fn (string $key) => empty($checks[$key])));

        if ($missing !== []) {
            throw new PartyVerificationException('Confirm each detail you checked: ' . implode(', ', $missing) . '.');
        }

        DB::transaction(function () use ($party, $verifier, $checks, $remarks, $required): void {
            $from = $party->verification_status;

            $party->forceFill([
                'verification_status' => Customer::VERIFICATION_VERIFIED,
                'verified_by' => $verifier->id,
                'verified_at' => now(),
                'verification_remarks' => $remarks,
                'verification_checks' => collect($required)->mapWithKeys(fn ($key) => [$key => true])->all(),
                'status' => 'active',
            ]);

            // A vendor's verified bank details are protected from now on: later changes
            // need a callback-verified bank change request (PartyBankChangeService).
            if ($party instanceof Vendor) {
                $party->forceFill(['bank_details_locked' => true]);
            }

            $party->save();

            $this->log($party, 'verified', $verifier, $from, Customer::VERIFICATION_VERIFIED, $remarks, [
                'checked' => $required,
                'values' => $this->sensitiveSnapshot($party),
            ]);
        });

        return $party->refresh();
    }

    public function returnForCorrection(Model $party, User $verifier, string $reason): Model
    {
        if (! $party->isPendingVerification()) {
            throw new PartyVerificationException('Only a party that is pending verification can be returned.');
        }

        if (trim($reason) === '') {
            throw new PartyVerificationException('A reason is required when returning a party.');
        }

        DB::transaction(function () use ($party, $verifier, $reason): void {
            $from = $party->verification_status;

            $party->forceFill([
                'verification_status' => Customer::VERIFICATION_RETURNED,
                'verification_remarks' => $reason,
                'status' => 'inactive',
            ])->save();

            $this->log($party, 'returned', $verifier, $from, Customer::VERIFICATION_RETURNED, $reason);
        });

        return $party->refresh();
    }

    /** Send a returned party back to the verifier without changing any field. */
    public function resubmit(Model $party, User $actor): Model
    {
        if (! $party->isReturnedForCorrection()) {
            throw new PartyVerificationException('Only a returned party can be resubmitted.');
        }

        DB::transaction(function () use ($party, $actor): void {
            $party->forceFill([
                'verification_status' => Customer::VERIFICATION_PENDING,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
            ])->save();

            $this->log($party, 'resubmitted', $actor, Customer::VERIFICATION_RETURNED, Customer::VERIFICATION_PENDING);
        });

        return $party->refresh();
    }

    /** Message to show when someone tries to activate a party that is not verified yet. */
    public function activationBlockedMessage(Model $party): ?string
    {
        if (! $this->enabled() || $party->isVerified()) {
            return null;
        }

        return "'{$party->name}' cannot be activated until a second person verifies its GSTIN, PAN and bank details.";
    }

    /**
     * Which details this party has that the verifier must confirm. A party with
     * no GSTIN, PAN or bank details has nothing to confirm.
     *
     * @return list<string>
     */
    public function requiredChecks(Model $party): array
    {
        $required = [];

        if ($party instanceof PartyBranch) {
            if ($this->normalise($party->gstin) !== '') {
                $required[] = 'gstin';
            }

            return $required;
        }

        if ($this->normalise($party->gstin) !== '') {
            $required[] = 'gstin';
        }
        if ($this->normalise($party->pan) !== '') {
            $required[] = 'pan';
        }
        if ($this->normalise($party->bank_name) !== '' || $this->normalise($party->account_number) !== '' || $this->normalise($party->ifsc_code) !== '') {
            $required[] = 'bank';
        }

        return $required;
    }

    private function permissionFor(Model $party): string
    {
        if ($party instanceof PartyBranch) {
            return $party->party_type === PartyBranch::PARTY_VENDOR
                ? 'purchase.vendors.verify'
                : 'crm.customers.verify';
        }

        return $party instanceof Customer ? 'crm.customers.verify' : 'purchase.vendors.verify';
    }

    /** @return array<string, mixed> */
    private function sensitiveSnapshot(Model $party): array
    {
        $snapshot = [];
        foreach ($this->sensitiveFields() as $field) {
            $snapshot[$field] = $party->getAttribute($field);
        }

        return $snapshot;
    }

    private function normalise(mixed $value): string
    {
        return strtoupper(trim((string) ($value ?? '')));
    }

    private function log(Model $party, string $action, ?User $actor, ?string $from, ?string $to, ?string $remarks = null, ?array $changes = null): void
    {
        PartyVerificationLog::query()->create([
            'tenant_id' => $party->tenant_id,
            'party_type' => $party->verificationPartyType(),
            'party_id' => $party->getKey(),
            'action' => $action,
            'actor_id' => $actor?->id,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'changes' => $changes,
        ]);
    }
}
