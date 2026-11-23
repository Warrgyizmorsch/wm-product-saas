<?php

namespace App\Services;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Sales\Models\SalesOrder;
use App\Models\ContactRole;
use App\Models\PartyBranch;
use App\Models\PartyContact;
use App\Models\PartyVerificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manages multi-branch party locations and unified role-based contacts (FRS 2.3 / C-17).
 *
 * Rules:
 * - One legal party (Customer or Vendor) owns one ledger and one credit limit.
 * - Head Office branch is owned by the parent: its address and GSTIN mirror the parent and
 *   its verification status follows the parent's verification status (no double verification).
 * - Additional branches must have the same 10-character PAN as the parent (when parent PAN/GSTIN is set).
 * - New branches or changed branch GSTINs go through branch-level second-person verification
 *   without deactivating the parent party.
 */
class PartyStructureService
{
    /** Prevents recursive saved-hook syncing. */
    private static bool $syncing = false;

    public function __construct(private readonly PartyVerificationService $verification)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('party_verification.branches_enabled', true);
    }

    public function partyTypeFor(Customer|Vendor $party): string
    {
        return $party instanceof Customer ? PartyBranch::PARTY_CUSTOMER : PartyBranch::PARTY_VENDOR;
    }

    /**
     * Create or update the parent's Head Office branch.
     * Mirrors parent address, GSTIN, status, and verification status (never starts a second verification cycle).
     */
    public function syncPrimaryBranch(Customer|Vendor $party): ?PartyBranch
    {
        if (self::$syncing || ! Schema::hasTable('party_branches')) {
            return null;
        }

        self::$syncing = true;
        try {
            $type = $this->partyTypeFor($party);
            $tenantId = (int) ($party->tenant_id ?? tenant_id() ?? 1);

            $ho = PartyBranch::withoutGlobalScopes()
                ->withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $type)
                ->where('party_id', $party->getKey())
                ->where('is_head_office', true)
                ->first();

            if ($ho && $ho->trashed()) {
                $ho->restore();
            }

            $address = $party instanceof Vendor
                ? ($party->billing_address ?: $party->address)
                : $party->billing_address;

            $gstin = TaxIdentityService::normalizeGstin($party->gstin);
            $stateCode = TaxIdentityService::extractStateCode($gstin);
            $parentStatus = strtolower((string) ($party->status ?? 'active')) === 'active' ? 'active' : 'inactive';
            $parentVerification = $party->verification_status ?? PartyBranch::VERIFICATION_VERIFIED;

            $hasOtherShippingDefault = PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $type)
                ->where('party_id', $party->getKey())
                ->where('is_head_office', false)
                ->where('is_default_shipping', true)
                ->exists();

            $hasOtherBillingDefault = PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $type)
                ->where('party_id', $party->getKey())
                ->where('is_head_office', false)
                ->where('is_default_billing', true)
                ->exists();

            if (! $ho) {
                $ho = new PartyBranch([
                    'tenant_id' => $tenantId,
                    'company_id' => $party->company_id ?? null,
                    'branch_id' => $party->branch_id ?? null,
                    'party_type' => $type,
                    'party_id' => $party->getKey(),
                    'name' => 'Head Office',
                    'code' => 'HO',
                    'branch_type' => 'head_office',
                    'is_billing' => true,
                    'is_shipping' => true,
                    'is_default_billing' => ! $hasOtherBillingDefault,
                    'is_default_shipping' => ! $hasOtherShippingDefault,
                    'is_head_office' => true,
                ]);
            }

            $ho->forceFill([
                'company_id' => $party->company_id ?? $ho->company_id,
                'branch_id' => $party->branch_id ?? $ho->branch_id,
                'gstin' => $gstin,
                'gst_state_code' => $stateCode,
                'address_line1' => $address ?: $ho->address_line1,
                'phone' => $party->phone ?: $ho->phone,
                'email' => $party->email ?: $ho->email,
                'status' => $parentStatus,
                'verification_status' => $parentVerification,
                'created_by' => $party->created_by ?? $ho->created_by,
                'submitted_by' => $party->submitted_by ?? $ho->submitted_by,
                'submitted_at' => $party->submitted_at ?? $ho->submitted_at,
                'verified_by' => $party->verified_by ?? $ho->verified_by,
                'verified_at' => $party->verified_at ?? $ho->verified_at,
                'verification_remarks' => $party->verification_remarks ?? $ho->verification_remarks,
            ])->save();

            return $ho;
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Validate branch GSTIN format and confirm its PAN (characters 3-12) matches the parent party's PAN.
     */
    public function validateBranchGstin(Customer|Vendor $party, ?string $gstin): ?string
    {
        $normalized = TaxIdentityService::normalizeGstin($gstin);
        if ($normalized === null) {
            return null;
        }

        if (! TaxIdentityService::isValidGstin($normalized)) {
            throw ValidationException::withMessages([
                'gstin' => 'The GSTIN must be a valid 15-character Indian GSTIN (e.g. 27AAAAA0000A1Z5).',
            ]);
        }

        $parentPan = TaxIdentityService::normalizePan($party->pan ?? null)
            ?: TaxIdentityService::extractPanFromGstin($party->gstin ?? null);
        $branchPan = TaxIdentityService::extractPanFromGstin($normalized);

        if ($parentPan && $branchPan && $parentPan !== $branchPan) {
            throw ValidationException::withMessages([
                'gstin' => "Branch GSTIN PAN ({$branchPan}) does not match parent PAN ({$parentPan}). A different PAN belongs to a different legal company.",
            ]);
        }

        return $normalized;
    }

    /**
     * Create a non-head-office branch for a customer or vendor.
     * When verification is enabled, the new branch starts pending_verification + inactive
     * while the parent party stays untouched and active.
     */
    public function createBranch(Customer|Vendor $party, array $data, ?User $actor = null): PartyBranch
    {
        $type = $this->partyTypeFor($party);
        $tenantId = (int) ($party->tenant_id ?? tenant_id() ?? 1);
        $actor ??= auth()->user();

        $gstin = $this->validateBranchGstin($party, $data['gstin'] ?? null);
        $stateCode = $gstin
            ? TaxIdentityService::extractStateCode($gstin)
            : (! empty($data['gst_state_code']) ? str_pad((string) $data['gst_state_code'], 2, '0', STR_PAD_LEFT) : null);

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = $this->nextBranchCode($type, (int) $party->getKey(), $tenantId);
        }

        $codeExists = PartyBranch::withoutGlobalScopes()
            ->where('party_type', $type)
            ->where('party_id', $party->getKey())
            ->where('code', $code)
            ->exists();

        if ($codeExists) {
            throw ValidationException::withMessages([
                'code' => "Branch code '{$code}' already exists for this {$type}.",
            ]);
        }

        return DB::transaction(function () use ($party, $type, $tenantId, $actor, $data, $gstin, $stateCode, $code): PartyBranch {
            $requiresVerification = $this->verification->enabled();

            $attrs = [
                'tenant_id' => $tenantId,
                'company_id' => $party->company_id ?? current_company_id(),
                'branch_id' => $party->branch_id ?? current_branch_id(),
                'party_type' => $type,
                'party_id' => $party->getKey(),
                'name' => trim((string) $data['name']),
                'code' => $code,
                'branch_type' => $data['branch_type'] ?? 'branch',
                'gstin' => $gstin,
                'gst_state_code' => $stateCode,
                'address_line1' => $data['address_line1'] ?? null,
                'address_line2' => $data['address_line2'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'is_billing' => (bool) ($data['is_billing'] ?? true),
                'is_shipping' => (bool) ($data['is_shipping'] ?? true),
                'is_default_billing' => (bool) ($data['is_default_billing'] ?? false),
                'is_default_shipping' => (bool) ($data['is_default_shipping'] ?? false),
                'is_head_office' => false,
                'status' => $requiresVerification ? 'inactive' : ($data['status'] ?? 'active'),
            ];

            if ($requiresVerification) {
                $attrs = array_merge($attrs, [
                    'verification_status' => PartyBranch::VERIFICATION_PENDING,
                    'created_by' => $actor?->id,
                    'submitted_by' => $actor?->id,
                    'submitted_at' => now(),
                ]);
            } else {
                $attrs['verification_status'] = PartyBranch::VERIFICATION_VERIFIED;
            }

            $branch = PartyBranch::create($attrs);

            if ($requiresVerification) {
                PartyVerificationLog::query()->create([
                    'tenant_id' => $tenantId,
                    'party_type' => $branch->verificationPartyType(),
                    'party_id' => $branch->id,
                    'action' => 'submitted',
                    'actor_id' => $actor?->id,
                    'from_status' => null,
                    'to_status' => PartyBranch::VERIFICATION_PENDING,
                    'remarks' => "New {$type} branch '{$branch->name}' submitted for verification.",
                    'changes' => [
                        'parent_type' => $type,
                        'parent_id' => $party->getKey(),
                        'new' => ['gstin' => $branch->gstin],
                    ],
                ]);
            }

            $this->ensureSingleDefault($type, (int) $party->getKey(), $tenantId, $branch);

            return $branch->refresh();
        });
    }

    /**
     * Update a party branch.
     * - Head Office address & GSTIN are owned by the parent and stay read-only here.
     * - Changing GSTIN on a verified non-HO branch sends ONLY this branch to pending_verification.
     */
    public function updateBranch(PartyBranch $branch, array $data, ?User $actor = null): PartyBranch
    {
        $party = $branch->parentParty();
        if (! $party) {
            throw ValidationException::withMessages(['party' => 'Parent party not found.']);
        }

        $actor ??= auth()->user();
        $tenantId = (int) $branch->tenant_id;

        return DB::transaction(function () use ($branch, $party, $data, $actor, $tenantId): PartyBranch {
            $update = [
                'is_billing' => array_key_exists('is_billing', $data) ? (bool) $data['is_billing'] : $branch->is_billing,
                'is_shipping' => array_key_exists('is_shipping', $data) ? (bool) $data['is_shipping'] : $branch->is_shipping,
                'is_default_billing' => array_key_exists('is_default_billing', $data) ? (bool) $data['is_default_billing'] : $branch->is_default_billing,
                'is_default_shipping' => array_key_exists('is_default_shipping', $data) ? (bool) $data['is_default_shipping'] : $branch->is_default_shipping,
            ];

            if (! $branch->is_head_office) {
                if (array_key_exists('name', $data)) {
                    $update['name'] = trim((string) $data['name']);
                }
                if (array_key_exists('code', $data) && trim((string) $data['code']) !== '') {
                    $newCode = strtoupper(trim((string) $data['code']));
                    $exists = PartyBranch::withoutGlobalScopes()
                        ->where('party_type', $branch->party_type)
                        ->where('party_id', $branch->party_id)
                        ->where('code', $newCode)
                        ->where('id', '!=', $branch->id)
                        ->exists();
                    if ($exists) {
                        throw ValidationException::withMessages(['code' => "Branch code '{$newCode}' is already in use."]);
                    }
                    $update['code'] = $newCode;
                }
                foreach (['branch_type', 'address_line1', 'address_line2', 'city', 'state', 'country', 'postal_code', 'phone', 'email'] as $col) {
                    if (array_key_exists($col, $data)) {
                        $update[$col] = $data[$col];
                    }
                }

                if (array_key_exists('gstin', $data)) {
                    $newGstin = $this->validateBranchGstin($party, $data['gstin']);
                    $oldGstin = TaxIdentityService::normalizeGstin($branch->gstin);
                    $update['gstin'] = $newGstin;
                    $update['gst_state_code'] = $newGstin
                        ? TaxIdentityService::extractStateCode($newGstin)
                        : ($data['gst_state_code'] ?? null);

                    if ($this->verification->enabled() && $newGstin !== $oldGstin) {
                        if ($branch->isVerified()) {
                            $update['status'] = 'inactive';
                            $update['verification_status'] = PartyBranch::VERIFICATION_PENDING;
                            $update['submitted_by'] = $actor?->id;
                            $update['submitted_at'] = now();
                            $update['verified_by'] = null;
                            $update['verified_at'] = null;
                            $update['verification_checks'] = null;

                            PartyVerificationLog::query()->create([
                                'tenant_id' => $tenantId,
                                'party_type' => $branch->verificationPartyType(),
                                'party_id' => $branch->id,
                                'action' => 'reverification_required',
                                'actor_id' => $actor?->id,
                                'from_status' => $branch->verification_status,
                                'to_status' => PartyBranch::VERIFICATION_PENDING,
                                'remarks' => 'Branch GSTIN changed on a verified branch.',
                                'changes' => [
                                    'parent_type' => $branch->party_type,
                                    'parent_id' => $branch->party_id,
                                    'gstin' => ['old' => $oldGstin, 'new' => $newGstin],
                                ],
                            ]);
                        } else {
                            $update['submitted_by'] = $actor?->id;
                            $update['submitted_at'] = now();
                            if ($branch->isReturnedForCorrection()) {
                                $update['verification_status'] = PartyBranch::VERIFICATION_PENDING;
                                PartyVerificationLog::query()->create([
                                    'tenant_id' => $tenantId,
                                    'party_type' => $branch->verificationPartyType(),
                                    'party_id' => $branch->id,
                                    'action' => 'resubmitted',
                                    'actor_id' => $actor?->id,
                                    'from_status' => PartyBranch::VERIFICATION_RETURNED,
                                    'to_status' => PartyBranch::VERIFICATION_PENDING,
                                    'remarks' => 'Branch GSTIN corrected and resubmitted.',
                                    'changes' => ['gstin' => ['old' => $oldGstin, 'new' => $newGstin]],
                                ]);
                            }
                        }
                    }
                }
            }

            $branch->forceFill($update)->save();
            $this->ensureSingleDefault($branch->party_type, (int) $branch->party_id, $tenantId, $branch);

            return $branch->refresh();
        });
    }

    /**
     * Deactivate a branch, blocking if open Sales Orders or Purchase Orders reference it.
     */
    public function deactivateBranch(PartyBranch $branch): PartyBranch
    {
        if ($branch->is_head_office) {
            throw ValidationException::withMessages([
                'branch' => 'The Head Office branch cannot be deactivated independently; deactivate the parent party instead.',
            ]);
        }

        if ($this->hasOpenOrders($branch)) {
            throw ValidationException::withMessages([
                'branch' => "Branch '{$branch->name}' has open orders and cannot be deactivated until those orders are closed or cancelled.",
            ]);
        }

        $branch->forceFill([
            'status' => 'inactive',
            'is_default_billing' => false,
            'is_default_shipping' => false,
        ])->save();

        $this->ensureSingleDefault($branch->party_type, (int) $branch->party_id, (int) $branch->tenant_id);

        return $branch->refresh();
    }

    public function activateBranch(PartyBranch $branch): PartyBranch
    {
        if ($this->verification->enabled() && ! $branch->isVerified()) {
            throw ValidationException::withMessages([
                'branch' => "Branch '{$branch->name}' cannot be activated until a second person verifies it.",
            ]);
        }

        $branch->forceFill(['status' => 'active'])->save();

        return $branch->refresh();
    }

    public function hasOpenOrders(PartyBranch $branch): bool
    {
        if ($branch->party_type === PartyBranch::PARTY_CUSTOMER) {
            if (Schema::hasTable('sales_orders') && Schema::hasColumn('sales_orders', 'billing_branch_id')) {
                $openSo = SalesOrder::withoutGlobalScopes()
                    ->where('tenant_id', $branch->tenant_id)
                    ->where(function ($q) use ($branch) {
                        $q->where('billing_branch_id', $branch->id)
                            ->orWhere('shipping_branch_id', $branch->id);
                    })
                    ->whereNotIn('status', ['Cancelled', 'Closed', 'Completed', 'Delivered'])
                    ->exists();
                if ($openSo) {
                    return true;
                }
            }
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'vendor_branch_id')) {
            $openPo = PurchaseOrder::withoutGlobalScopes()
                ->where('tenant_id', $branch->tenant_id)
                ->where(function ($q) use ($branch) {
                    $q->where('vendor_branch_id', $branch->id)
                        ->orWhere('delivery_party_branch_id', $branch->id);
                })
                ->whereNotIn('status', ['Cancelled', 'Completed'])
                ->exists();
            if ($openPo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guarantee at most one default billing and one default shipping branch per party.
     */
    public function ensureSingleDefault(string $partyType, int $partyId, int $tenantId, ?PartyBranch $preferred = null): void
    {
        if ($preferred && $preferred->is_default_billing) {
            PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $partyId)
                ->where('id', '!=', $preferred->id)
                ->where('is_default_billing', true)
                ->update(['is_default_billing' => false]);
        }

        if ($preferred && $preferred->is_default_shipping) {
            PartyBranch::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('party_type', $partyType)
                ->where('party_id', $partyId)
                ->where('id', '!=', $preferred->id)
                ->where('is_default_shipping', true)
                ->update(['is_default_shipping' => false]);
        }
    }

    /**
     * Active, verified branches with their active contacts & roles (used by SO/PO lookup endpoints).
     */
    public function activeVerifiedBranchesFor(string $partyType, int $partyId, ?int $tenantId = null): Collection
    {
        $query = PartyBranch::query()
            ->forParty($partyType, $partyId)
            ->activeVerified()
            ->with(['contacts' => fn ($q) => $q->where('status', 'active')->with('roles')])
            ->orderByDesc('is_head_office')
            ->orderByDesc('is_default_billing')
            ->orderBy('name');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Create or update a unified contact and attach roles.
     *
     * @param  list<int|string>  $roleIds
     */
    public function createContact(string $partyType, int $partyId, array $data, array $roleIds = [], ?int $tenantId = null): PartyContact
    {
        $tenantId ??= tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return DB::transaction(function () use ($partyType, $partyId, $data, $roleIds, $tenantId): PartyContact {
            $isPrimary = (bool) ($data['is_primary'] ?? false);

            if ($isPrimary) {
                PartyContact::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('party_type', $partyType)
                    ->where('party_id', $partyId)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $contact = PartyContact::create([
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'] ?? current_company_id(),
                'branch_id' => $data['branch_id'] ?? current_branch_id(),
                'party_type' => $partyType,
                'party_id' => $partyId,
                'party_branch_id' => ! empty($data['party_branch_id']) ? (int) $data['party_branch_id'] : null,
                'legacy_crm_contact_id' => $data['legacy_crm_contact_id'] ?? null,
                'name' => trim((string) $data['name']),
                'designation' => $data['designation'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'mobile' => $data['mobile'] ?? ($data['phone'] ?? null),
                'is_primary' => $isPrimary,
                'status' => $data['status'] ?? 'active',
            ]);

            $resolvedRoleIds = $this->resolveRoleIds($tenantId, $roleIds, $data['role'] ?? null);
            if ($resolvedRoleIds !== []) {
                $contact->roles()->sync($resolvedRoleIds);
            }

            return $contact->load('roles', 'partyBranch');
        });
    }

    /**
     * @param  list<int|string>  $roleIds
     */
    public function updateContact(PartyContact $contact, array $data, ?array $roleIds = null): PartyContact
    {
        return DB::transaction(function () use ($contact, $data, $roleIds): PartyContact {
            $isPrimary = array_key_exists('is_primary', $data) ? (bool) $data['is_primary'] : $contact->is_primary;

            if ($isPrimary && ! $contact->is_primary) {
                PartyContact::withoutGlobalScopes()
                    ->where('tenant_id', $contact->tenant_id)
                    ->where('party_type', $contact->party_type)
                    ->where('party_id', $contact->party_id)
                    ->where('id', '!=', $contact->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $contact->update([
                'party_branch_id' => array_key_exists('party_branch_id', $data)
                    ? (! empty($data['party_branch_id']) ? (int) $data['party_branch_id'] : null)
                    : $contact->party_branch_id,
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $contact->name,
                'designation' => $data['designation'] ?? $contact->designation,
                'email' => array_key_exists('email', $data) ? $data['email'] : $contact->email,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $contact->phone,
                'mobile' => array_key_exists('mobile', $data) ? $data['mobile'] : $contact->mobile,
                'is_primary' => $isPrimary,
                'status' => $data['status'] ?? $contact->status,
            ]);

            if ($roleIds !== null || array_key_exists('role', $data)) {
                $resolved = $this->resolveRoleIds((int) $contact->tenant_id, $roleIds ?? [], $data['role'] ?? null);
                $contact->roles()->sync($resolved);
            }

            return $contact->refresh()->load('roles', 'partyBranch');
        });
    }

    /**
     * Re-point any pre-conversion CRM Account contacts to the converted Customer.
     */
    public function migrateAccountContactsToCustomer(CrmAccount $account, Customer $customer): int
    {
        if (! Schema::hasTable('contacts')) {
            return 0;
        }

        return PartyContact::withoutGlobalScopes()
            ->where('tenant_id', $account->tenant_id)
            ->where('party_type', PartyContact::PARTY_CRM_ACCOUNT)
            ->where('party_id', $account->id)
            ->update([
                'party_type' => PartyContact::PARTY_CUSTOMER,
                'party_id' => $customer->id,
            ]);
    }

    /**
     * Resolve explicit role IDs or a legacy free-text role name into ContactRole IDs.
     *
     * @param  list<int|string>  $roleIds
     * @return list<int>
     */
    public function resolveRoleIds(int $tenantId, array $roleIds, ?string $freeTextRole = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds))));

        $roleText = trim((string) ($freeTextRole ?? ''));
        if ($ids === [] && $roleText !== '') {
            $slug = Str::slug($roleText, '_');
            if ($slug !== '') {
                $role = ContactRole::query()
                    ->where(function ($q) use ($tenantId) {
                        $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
                    })
                    ->where('slug', $slug)
                    ->first();

                if (! $role) {
                    $role = ContactRole::create([
                        'tenant_id' => $tenantId,
                        'name' => mb_substr($roleText, 0, 100),
                        'slug' => $slug,
                        'is_system' => false,
                        'is_active' => true,
                    ]);
                }

                $ids[] = (int) $role->id;
            }
        }

        return $ids;
    }

    private function nextBranchCode(string $partyType, int $partyId, int $tenantId): string
    {
        $count = PartyBranch::withoutGlobalScopes()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('party_type', $partyType)
            ->where('party_id', $partyId)
            ->count() + 1;

        do {
            $code = 'BR-' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
            $exists = PartyBranch::withoutGlobalScopes()
                ->withTrashed()
                ->where('party_type', $partyType)
                ->where('party_id', $partyId)
                ->where('code', $code)
                ->exists();
            $count++;
        } while ($exists);

        return $code;
    }
}
