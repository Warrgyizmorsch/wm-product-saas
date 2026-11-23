<?php

use App\Services\TaxIdentityService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('party_branches') || ! Schema::hasTable('contacts')) {
            return;
        }

        $now = now();
        $countsByTenant = [];

        // 1. Backfill Customer Head Office + optional separate Shipping branch
        if (Schema::hasTable('customers')) {
            DB::table('customers')->orderBy('id')->chunk(200, function ($customers) use ($now, &$countsByTenant): void {
                foreach ($customers as $c) {
                    $tid = (int) ($c->tenant_id ?? 1);
                    $countsByTenant[$tid] ??= ['customers_ho' => 0, 'customers_ship' => 0, 'vendors_ho' => 0, 'vendors_ship' => 0, 'contacts' => 0];

                    $billing = trim((string) ($c->billing_address ?? ''));
                    $shipping = trim((string) ($c->shipping_address ?? ''));
                    $hasSeparateShipping = ($shipping !== '') && (mb_strtolower($shipping) !== mb_strtolower($billing));

                    $gstin = TaxIdentityService::normalizeGstin($c->gstin ?? null);
                    $stateCode = TaxIdentityService::extractStateCode($gstin);
                    $verificationStatus = $c->verification_status ?? 'verified';

                    $hoExists = DB::table('party_branches')
                        ->where('party_type', 'customer')
                        ->where('party_id', $c->id)
                        ->where('code', 'HO')
                        ->exists();

                    if (! $hoExists) {
                        DB::table('party_branches')->insert([
                            'tenant_id' => $tid,
                            'company_id' => $c->company_id ?? null,
                            'branch_id' => $c->branch_id ?? null,
                            'party_type' => 'customer',
                            'party_id' => $c->id,
                            'name' => 'Head Office',
                            'code' => 'HO',
                            'branch_type' => 'head_office',
                            'gstin' => $gstin,
                            'gst_state_code' => $stateCode,
                            'address_line1' => $billing !== '' ? $billing : null,
                            'phone' => $c->phone ?? null,
                            'email' => $c->email ?? null,
                            'is_billing' => true,
                            'is_shipping' => ! $hasSeparateShipping,
                            'is_default_billing' => true,
                            'is_default_shipping' => ! $hasSeparateShipping,
                            'is_head_office' => true,
                            'status' => strtolower((string) ($c->status ?? 'active')) === 'active' ? 'active' : 'inactive',
                            'verification_status' => $verificationStatus,
                            'created_by' => $c->created_by ?? null,
                            'submitted_by' => $c->submitted_by ?? null,
                            'submitted_at' => $c->submitted_at ?? null,
                            'verified_by' => $c->verified_by ?? null,
                            'verified_at' => $c->verified_at ?? null,
                            'verification_remarks' => $c->verification_remarks ?? 'Backfilled from parent customer',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $countsByTenant[$tid]['customers_ho']++;
                    }

                    if ($hasSeparateShipping) {
                        $shipExists = DB::table('party_branches')
                            ->where('party_type', 'customer')
                            ->where('party_id', $c->id)
                            ->where('code', 'SHIP-01')
                            ->exists();

                        if (! $shipExists) {
                            DB::table('party_branches')->insert([
                                'tenant_id' => $tid,
                                'company_id' => $c->company_id ?? null,
                                'branch_id' => $c->branch_id ?? null,
                                'party_type' => 'customer',
                                'party_id' => $c->id,
                                'name' => 'Shipping Address',
                                'code' => 'SHIP-01',
                                'branch_type' => 'delivery',
                                'gstin' => null,
                                'gst_state_code' => null,
                                'address_line1' => $shipping,
                                'phone' => $c->phone ?? null,
                                'email' => $c->email ?? null,
                                'is_billing' => false,
                                'is_shipping' => true,
                                'is_default_billing' => false,
                                'is_default_shipping' => true,
                                'is_head_office' => false,
                                'status' => strtolower((string) ($c->status ?? 'active')) === 'active' ? 'active' : 'inactive',
                                'verification_status' => $verificationStatus,
                                'created_by' => $c->created_by ?? null,
                                'submitted_by' => $c->submitted_by ?? null,
                                'submitted_at' => $c->submitted_at ?? null,
                                'verified_by' => $c->verified_by ?? null,
                                'verified_at' => $c->verified_at ?? null,
                                'verification_remarks' => $c->verification_remarks ?? 'Backfilled shipping address from parent customer',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                            $countsByTenant[$tid]['customers_ship']++;
                        }
                    }
                }
            });
        }

        // 2. Backfill Vendor Head Office + optional separate Shipping branch
        if (Schema::hasTable('vendors')) {
            DB::table('vendors')->orderBy('id')->chunk(200, function ($vendors) use ($now, &$countsByTenant): void {
                foreach ($vendors as $v) {
                    $tid = (int) ($v->tenant_id ?? 1);
                    $countsByTenant[$tid] ??= ['customers_ho' => 0, 'customers_ship' => 0, 'vendors_ho' => 0, 'vendors_ship' => 0, 'contacts' => 0];

                    $billing = trim((string) ($v->billing_address ?: ($v->address ?? '')));
                    $shipping = trim((string) ($v->shipping_address ?? ''));
                    $hasSeparateShipping = ($shipping !== '') && (mb_strtolower($shipping) !== mb_strtolower($billing));

                    $gstin = TaxIdentityService::normalizeGstin($v->gstin ?? null);
                    $stateCode = TaxIdentityService::extractStateCode($gstin);
                    $verificationStatus = $v->verification_status ?? 'verified';

                    $hoExists = DB::table('party_branches')
                        ->where('party_type', 'vendor')
                        ->where('party_id', $v->id)
                        ->where('code', 'HO')
                        ->exists();

                    if (! $hoExists) {
                        DB::table('party_branches')->insert([
                            'tenant_id' => $tid,
                            'company_id' => $v->company_id ?? null,
                            'branch_id' => $v->branch_id ?? null,
                            'party_type' => 'vendor',
                            'party_id' => $v->id,
                            'name' => 'Head Office',
                            'code' => 'HO',
                            'branch_type' => 'head_office',
                            'gstin' => $gstin,
                            'gst_state_code' => $stateCode,
                            'address_line1' => $billing !== '' ? $billing : null,
                            'phone' => $v->phone ?? null,
                            'email' => $v->email ?? null,
                            'is_billing' => true,
                            'is_shipping' => ! $hasSeparateShipping,
                            'is_default_billing' => true,
                            'is_default_shipping' => ! $hasSeparateShipping,
                            'is_head_office' => true,
                            'status' => strtolower((string) ($v->status ?? 'active')) === 'active' ? 'active' : 'inactive',
                            'verification_status' => $verificationStatus,
                            'created_by' => $v->created_by ?? null,
                            'submitted_by' => $v->submitted_by ?? null,
                            'submitted_at' => $v->submitted_at ?? null,
                            'verified_by' => $v->verified_by ?? null,
                            'verified_at' => $v->verified_at ?? null,
                            'verification_remarks' => $v->verification_remarks ?? 'Backfilled from parent vendor',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $countsByTenant[$tid]['vendors_ho']++;
                    }

                    if ($hasSeparateShipping) {
                        $shipExists = DB::table('party_branches')
                            ->where('party_type', 'vendor')
                            ->where('party_id', $v->id)
                            ->where('code', 'SHIP-01')
                            ->exists();

                        if (! $shipExists) {
                            DB::table('party_branches')->insert([
                                'tenant_id' => $tid,
                                'company_id' => $v->company_id ?? null,
                                'branch_id' => $v->branch_id ?? null,
                                'party_type' => 'vendor',
                                'party_id' => $v->id,
                                'name' => 'Dispatch / Shipping Address',
                                'code' => 'SHIP-01',
                                'branch_type' => 'warehouse',
                                'gstin' => null,
                                'gst_state_code' => null,
                                'address_line1' => $shipping,
                                'phone' => $v->phone ?? null,
                                'email' => $v->email ?? null,
                                'is_billing' => false,
                                'is_shipping' => true,
                                'is_default_billing' => false,
                                'is_default_shipping' => true,
                                'is_head_office' => false,
                                'status' => strtolower((string) ($v->status ?? 'active')) === 'active' ? 'active' : 'inactive',
                                'verification_status' => $verificationStatus,
                                'created_by' => $v->created_by ?? null,
                                'submitted_by' => $v->submitted_by ?? null,
                                'submitted_at' => $v->submitted_at ?? null,
                                'verified_by' => $v->verified_by ?? null,
                                'verified_at' => $v->verified_at ?? null,
                                'verification_remarks' => $v->verification_remarks ?? 'Backfilled shipping address from parent vendor',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                            $countsByTenant[$tid]['vendors_ship']++;
                        }
                    }
                }
            });
        }

        // 3. Migrate crm_contacts -> contacts + map free-text role to contact_roles
        if (Schema::hasTable('crm_contacts')) {
            $accountCustomerMap = Schema::hasTable('crm_accounts')
                ? DB::table('crm_accounts')->pluck('customer_id', 'id')->all()
                : [];

            DB::table('crm_contacts')->orderBy('id')->chunk(200, function ($rows) use ($now, $accountCustomerMap, &$countsByTenant): void {
                foreach ($rows as $row) {
                    $tid = (int) ($row->tenant_id ?? 1);
                    $countsByTenant[$tid] ??= ['customers_ho' => 0, 'customers_ship' => 0, 'vendors_ho' => 0, 'vendors_ship' => 0, 'contacts' => 0];

                    $already = DB::table('contacts')->where('legacy_crm_contact_id', $row->id)->exists();
                    if ($already) {
                        continue;
                    }

                    $customerId = $accountCustomerMap[$row->crm_account_id] ?? null;
                    $partyType = $customerId ? 'customer' : 'crm_account';
                    $partyId = $customerId ? (int) $customerId : (int) $row->crm_account_id;

                    $contactId = DB::table('contacts')->insertGetId([
                        'tenant_id' => $tid,
                        'company_id' => $row->company_id ?? null,
                        'branch_id' => $row->branch_id ?? null,
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'party_branch_id' => null,
                        'legacy_crm_contact_id' => $row->id,
                        'name' => $row->name,
                        'designation' => $row->designation ?? null,
                        'email' => $row->email ?? null,
                        'phone' => $row->phone ?? null,
                        'mobile' => $row->mobile ?? null,
                        'is_primary' => (bool) ($row->is_primary ?? false),
                        'status' => $row->status ?? 'active',
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                        'deleted_at' => $row->deleted_at ?? null,
                    ]);

                    $countsByTenant[$tid]['contacts']++;

                    $roleText = trim((string) ($row->role ?? ''));
                    if ($roleText !== '') {
                        $roleId = $this->resolveOrCreateRoleId($tid, $roleText, $now);
                        if ($roleId) {
                            DB::table('contact_role_assignments')->insertOrIgnore([
                                'contact_id' => $contactId,
                                'contact_role_id' => $roleId,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            });
        }

        if ($countsByTenant !== []) {
            Log::info('C-17 party_branches & contacts backfill summary', ['tenants' => $countsByTenant]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            DB::table('contacts')->whereNotNull('legacy_crm_contact_id')->delete();
        }

        if (Schema::hasTable('party_branches')) {
            DB::table('party_branches')
                ->whereIn('code', ['HO', 'SHIP-01'])
                ->where('verification_remarks', 'like', 'Backfilled%')
                ->delete();
        }
    }

    private function resolveOrCreateRoleId(int $tenantId, string $roleName, $now): ?int
    {
        $normalizedMap = [
            'finance' => 'Accounts',
            'purchase decision maker' => 'Decision Maker',
        ];
        $mappedName = $normalizedMap[mb_strtolower($roleName)] ?? $roleName;
        $slug = Str::slug($mappedName, '_');
        if ($slug === '') {
            return null;
        }

        $roleId = DB::table('contact_roles')
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
            })
            ->where('slug', $slug)
            ->value('id');

        if ($roleId) {
            return (int) $roleId;
        }

        return (int) DB::table('contact_roles')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => mb_substr($mappedName, 0, 100),
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
