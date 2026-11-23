<?php

namespace App\Services;

use App\Domains\Inventory\Models\Vendor;
use App\Models\PartyBankChangeRequest;
use App\Models\PartyVerificationLog;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Facades\DB;

/**
 * Callback-verified bank changes for vendors (FRS 2.3, task C-16 bank part).
 *
 * Flow: someone with vendor edit rights raises a request with the new details
 * and a reason. The live bank details do not change. A DIFFERENT person who
 * holds purchase.vendors.bank_change.approve phones the vendor on the number
 * that was on file when the request was raised, records the call, and approves
 * (the new details are then applied) or rejects. Nothing else can change a
 * locked vendor's bank details (see ProtectsBankDetails).
 */
class PartyBankChangeService
{
    public const PERMISSION_APPROVE = 'purchase.vendors.bank_change.approve';

    public function __construct(private readonly AccessService $access)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('party_verification.enabled', true)
            && (bool) config('party_verification.bank_change_enabled', true);
    }

    /** @return list<string> */
    public function bankFields(): array
    {
        return config('party_verification.bank_fields', ['bank_name', 'account_number', 'ifsc_code']);
    }

    /** True when this vendor's bank details can only change through a request. */
    public function isProtected(Vendor $vendor): bool
    {
        return $this->enabled() && (bool) ($vendor->getAttribute('bank_details_locked') ?? true);
    }

    public function openRequest(Vendor $vendor): ?PartyBankChangeRequest
    {
        return PartyBankChangeRequest::query()
            ->where('party_type', 'vendor')
            ->where('party_id', $vendor->id)
            ->where('status', PartyBankChangeRequest::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * Remove the bank fields from data about to be saved to a protected vendor,
     * so a normal edit can never change them.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<string>}  [clean data, bank fields the user tried to change]
     */
    public function stripProtectedFields(Vendor $vendor, array $data): array
    {
        if (! $this->isProtected($vendor)) {
            return [$data, []];
        }

        $attempted = [];
        foreach ($this->bankFields() as $field) {
            if (array_key_exists($field, $data)) {
                if ($this->normalise($data[$field]) !== $this->normalise($vendor->getAttribute($field))) {
                    $attempted[] = $field;
                }
                unset($data[$field]);
            }
        }

        return [$data, $attempted];
    }

    public function canApprove(?User $user, Vendor $vendor): bool
    {
        if (! $user) {
            return false;
        }

        return $this->access->allows($user, self::PERMISSION_APPROVE, [
            'tenant_id' => $vendor->tenant_id,
            'company_id' => $vendor->company_id ?? null,
            'branch_id' => $vendor->branch_id ?? null,
        ]);
    }

    /** @param array{bank_name?: ?string, account_number?: ?string, ifsc_code?: ?string} $new */
    public function request(Vendor $vendor, User $requester, array $new, string $reason): PartyBankChangeRequest
    {
        if (! $vendor->isVerified()) {
            throw new PartyBankChangeException('This vendor is pending verification. Only an active, verified vendor can request a bank change.');
        }

        if (! $this->isProtected($vendor)) {
            throw new PartyBankChangeException('This vendor\'s bank details can be edited directly; no request is needed.');
        }

        if ($this->openRequest($vendor)) {
            throw new PartyBankChangeException('A bank change request is already open for this vendor. Finish or cancel it first.');
        }

        if (trim($reason) === '') {
            throw new PartyBankChangeException('A reason is required.');
        }

        if ($this->digits($vendor->phone) === '') {
            throw new PartyBankChangeException('This vendor has no phone number on record, so the callback cannot be done. Add a verified contact number first.');
        }

        $changed = false;
        foreach (['bank_name', 'account_number', 'ifsc_code'] as $field) {
            if ($this->normalise($new[$field] ?? null) !== $this->normalise($vendor->getAttribute($field))) {
                $changed = true;
            }
        }
        if (! $changed) {
            throw new PartyBankChangeException('The requested details are the same as the current bank details.');
        }

        return DB::transaction(function () use ($vendor, $requester, $new, $reason) {
            $request = PartyBankChangeRequest::create([
                'tenant_id' => $vendor->tenant_id,
                'party_type' => 'vendor',
                'party_id' => $vendor->id,
                'status' => PartyBankChangeRequest::STATUS_PENDING,
                'old_bank_name' => $vendor->bank_name,
                'old_account_number' => $vendor->account_number,
                'old_ifsc_code' => $vendor->ifsc_code,
                'new_bank_name' => $new['bank_name'] ?? null,
                'new_account_number' => $new['account_number'] ?? null,
                'new_ifsc_code' => $new['ifsc_code'] ?? null,
                'reason' => $reason,
                'phone_on_file' => $vendor->phone,
                'requested_by' => $requester->id,
                'requested_at' => now(),
            ]);

            $this->log($vendor, 'bank_change_requested', $requester, $reason, [
                'request_id' => $request->id,
                'old' => $this->snapshot($request, 'old'),
                'new' => $this->snapshot($request, 'new'),
            ]);

            return $request;
        });
    }

    /**
     * Approve after the callback. The requester can never approve. The call must
     * be made to the number that was on file when the request was raised.
     *
     * @param  array{callback_number?: ?string, callback_contact?: ?string, callback_notes?: ?string, number_from_master?: mixed, confirmed?: mixed}  $callback
     */
    public function approve(PartyBankChangeRequest $request, User $approver, array $callback): PartyBankChangeRequest
    {
        $vendor = $this->vendorFor($request);

        if (! $request->isPending()) {
            throw new PartyBankChangeException('This request has already been decided.');
        }

        if ((int) $request->requested_by === (int) $approver->id) {
            throw new PartyBankChangeException('You raised this request, so a different person must do the callback and approve it.');
        }

        if (empty($callback['number_from_master'])) {
            throw new PartyBankChangeException('Confirm that you called the number on the vendor record, not a number from the request or an email.');
        }

        if (trim((string) ($callback['callback_contact'] ?? '')) === '') {
            throw new PartyBankChangeException('Record who you spoke to at the vendor.');
        }

        if (empty($callback['confirmed'])) {
            throw new PartyBankChangeException('The vendor must have confirmed the new bank details on the call. If they did not, reject the request instead.');
        }

        if (! $this->sameNumber($callback['callback_number'] ?? null, $request->phone_on_file)) {
            throw new PartyBankChangeException('The number you called does not match the vendor\'s phone on file when the request was raised.');
        }

        if (! $this->sameNumber($vendor->phone, $request->phone_on_file)) {
            throw new PartyBankChangeException('The vendor\'s phone number was changed after this request was raised. Reject this request and raise a new one.');
        }

        if ($this->normalise($vendor->account_number) !== $this->normalise($request->old_account_number)
            || $this->normalise($vendor->ifsc_code) !== $this->normalise($request->old_ifsc_code)
            || $this->normalise($vendor->bank_name) !== $this->normalise($request->old_bank_name)) {
            throw new PartyBankChangeException('The vendor\'s bank details changed after this request was raised. Reject this request and raise a new one.');
        }

        DB::transaction(function () use ($request, $vendor, $approver, $callback) {
            Vendor::$bankChangeApproved = true;
            try {
                $vendor->forceFill([
                    'bank_name' => $request->new_bank_name,
                    'account_number' => $request->new_account_number,
                    'ifsc_code' => $request->new_ifsc_code,
                ])->save();
            } finally {
                Vendor::$bankChangeApproved = false;
            }

            $request->forceFill([
                'status' => PartyBankChangeRequest::STATUS_APPROVED,
                'callback_by' => $approver->id,
                'callback_at' => now(),
                'callback_number' => $callback['callback_number'],
                'callback_contact' => $callback['callback_contact'],
                'callback_notes' => $callback['callback_notes'] ?? null,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'applied_at' => now(),
            ])->save();

            $this->log($vendor, 'bank_change_approved', $approver, $callback['callback_notes'] ?? null, [
                'request_id' => $request->id,
                'requested_by' => $request->requested_by,
                'old' => $this->snapshot($request, 'old'),
                'new' => $this->snapshot($request, 'new'),
                'callback' => [
                    'number' => $callback['callback_number'],
                    'contact' => $callback['callback_contact'],
                ],
            ]);
        });

        return $request->refresh();
    }

    public function reject(PartyBankChangeRequest $request, User $approver, string $reason): PartyBankChangeRequest
    {
        $vendor = $this->vendorFor($request);

        if (! $request->isPending()) {
            throw new PartyBankChangeException('This request has already been decided.');
        }

        if ((int) $request->requested_by === (int) $approver->id) {
            throw new PartyBankChangeException('You raised this request. Cancel it instead of rejecting it.');
        }

        if (trim($reason) === '') {
            throw new PartyBankChangeException('A reason is required when rejecting a request.');
        }

        DB::transaction(function () use ($request, $vendor, $approver, $reason) {
            $request->forceFill([
                'status' => PartyBankChangeRequest::STATUS_REJECTED,
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'decision_remarks' => $reason,
            ])->save();

            $this->log($vendor, 'bank_change_rejected', $approver, $reason, ['request_id' => $request->id]);
        });

        return $request->refresh();
    }

    /** The person who raised a request can withdraw it while it is pending. */
    public function cancel(PartyBankChangeRequest $request, User $user): PartyBankChangeRequest
    {
        $vendor = $this->vendorFor($request);

        if (! $request->isPending()) {
            throw new PartyBankChangeException('This request has already been decided.');
        }

        if ((int) $request->requested_by !== (int) $user->id) {
            throw new PartyBankChangeException('Only the person who raised a request can cancel it.');
        }

        DB::transaction(function () use ($request, $vendor, $user) {
            $request->forceFill([
                'status' => PartyBankChangeRequest::STATUS_CANCELLED,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ])->save();

            $this->log($vendor, 'bank_change_cancelled', $user, null, ['request_id' => $request->id]);
        });

        return $request->refresh();
    }

    private function vendorFor(PartyBankChangeRequest $request): Vendor
    {
        $vendor = Vendor::query()->find($request->party_id);

        if (! $vendor) {
            throw new PartyBankChangeException('The vendor for this request no longer exists.');
        }

        return $vendor;
    }

    /** @return array{bank_name: ?string, account_number: ?string, ifsc_code: ?string} */
    private function snapshot(PartyBankChangeRequest $request, string $which): array
    {
        return [
            'bank_name' => $request->{$which . '_bank_name'},
            'account_number' => $request->{$which . '_account_number'},
            'ifsc_code' => $request->{$which . '_ifsc_code'},
        ];
    }

    private function sameNumber(?string $a, ?string $b): bool
    {
        $a = $this->digits($a);
        $b = $this->digits($b);

        if ($a === '' || $b === '') {
            return false;
        }

        // Compare the last 10 digits so "+91 98765 43210" and "9876543210" match.
        return substr($a, -10) === substr($b, -10);
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function normalise(mixed $value): string
    {
        return strtoupper(trim((string) ($value ?? '')));
    }

    private function log(Vendor $vendor, string $action, User $actor, ?string $remarks, array $changes): void
    {
        PartyVerificationLog::query()->create([
            'tenant_id' => $vendor->tenant_id,
            'party_type' => 'vendor',
            'party_id' => $vendor->id,
            'action' => $action,
            'actor_id' => $actor->id,
            'from_status' => null,
            'to_status' => null,
            'remarks' => $remarks,
            'changes' => $changes,
        ]);
    }
}
