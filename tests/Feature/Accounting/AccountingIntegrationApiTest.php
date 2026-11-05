<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalApprovalSettings;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Product-neutral integration API: tenant isolation, permissions, idempotency,
 * and that nothing unbalanced or duplicated reaches the ledger.
 */
class AccountingIntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/accounting/integrations';

    private Tenant $tenant;
    private User $owner;
    private IntegrationConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = $this->makeTenant('acme');
        $this->owner = $this->makeUser($this->tenant, 'owner@acme.test', 'tenant_owner');
        $this->useTenant($this->tenant);

        $this->connection = IntegrationConnection::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Head office',
            'product' => IntegrationConnection::PRODUCT_CUSTOM,
            'status' => IntegrationConnection::STATUS_ACTIVE,
        ]);
    }

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise']);
        $this->useTenant($tenant);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $email, string $role): User
    {
        $user = User::create(['tenant_id' => $tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail()->id,
            'tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    private function useTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);
    }

    private function api(?User $user = null, ?Tenant $tenant = null): static
    {
        $user ??= $this->owner;
        Sanctum::actingAs($user, ['*']);

        return $this->withHeader('X-Tenant', ($tenant ?? $this->tenant)->slug);
    }

    private function url(string $path = ''): string
    {
        return self::BASE."/connections/{$this->connection->id}{$path}";
    }

    /** Two mapped ledgers, so voucher tests have something to post to. */
    private function seedLedgers(): void
    {
        $response = $this->api()->withHeader('Idempotency-Key', 'seed-ledgers')->postJson($this->url('/ledgers/batch'), ['items' => [
            ['external_id' => 'L-BANK', 'name' => 'HDFC Bank', 'type' => 'asset', 'subtype' => 'current_asset', 'is_cash_or_bank' => true],
            ['external_id' => 'L-RENT', 'name' => 'Office Rent', 'type' => 'expense', 'subtype' => 'indirect_expense'],
        ]]);

        $response->assertCreated();
        $this->assertSame(2, $response->json('data.created_count'));
    }

    private function voucher(array $overrides = []): array
    {
        return array_replace([
            'external_id' => 'V-1',
            'voucher_type' => 'payment',
            'date' => now()->toDateString(),
            'number' => 'PAY-77',
            'narration' => 'Rent',
            'lines' => [
                ['ledger_external_id' => 'L-RENT', 'debit' => 1000, 'credit' => 0],
                ['ledger_external_id' => 'L-BANK', 'debit' => 0, 'credit' => 1000],
            ],
        ], $overrides);
    }

    private function sendVoucher(array $voucher, string $key)
    {
        return $this->api()->withHeader('Idempotency-Key', $key)->postJson($this->url('/vouchers/batch'), ['items' => [$voucher]]);
    }

    private function integrationJournals()
    {
        return Journal::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('source', Journal::SOURCE_INTEGRATION);
    }

    // ── Auth and tenant isolation ─────────────────────────────────────────

    public function test_guest_is_refused(): void
    {
        $this->withHeader('X-Tenant', 'acme')->getJson(self::BASE.'/connections')->assertUnauthorized();
    }

    public function test_another_tenants_connection_is_not_found(): void
    {
        $other = $this->makeTenant('globex');
        $otherOwner = $this->makeUser($other, 'owner@globex.test', 'tenant_owner');

        $this->api($otherOwner, $other)->getJson($this->url())->assertNotFound();
        $this->api($otherOwner, $other)
            ->withHeader('Idempotency-Key', 'x')
            ->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher()]])
            ->assertNotFound();
    }

    public function test_user_cannot_use_another_tenants_header(): void
    {
        $other = $this->makeTenant('globex');
        $otherOwner = $this->makeUser($other, 'owner@globex.test', 'tenant_owner');

        $this->api($otherOwner, $this->tenant)->getJson(self::BASE.'/connections')->assertForbidden();
    }

    public function test_voucher_cannot_reach_another_tenants_ledger(): void
    {
        $this->seedLedgers();

        $other = $this->makeTenant('globex');
        $otherOwner = $this->makeUser($other, 'owner@globex.test', 'tenant_owner');
        $otherConnection = IntegrationConnection::create([
            'tenant_id' => $other->id, 'name' => 'Globex books', 'product' => 'custom', 'status' => 'active',
        ]);

        $response = $this->api($otherOwner, $other)
            ->withHeader('Idempotency-Key', 'g-1')
            ->postJson(self::BASE."/connections/{$otherConnection->id}/vouchers/batch", ['items' => [$this->voucher()]]);

        $response->assertCreated();
        $this->assertSame('unmapped_ledger', $response->json('data.records.0.error_code'));
        $this->assertSame(0, Journal::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->count());
    }

    public function test_account_code_lookup_stays_inside_the_tenant(): void
    {
        $this->useTenant($this->tenant);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => 'SECRET', 'name' => 'Acme only', 'type' => 'asset', 'normal_balance' => 'debit',
        ]);

        $other = $this->makeTenant('globex');
        $otherOwner = $this->makeUser($other, 'owner@globex.test', 'tenant_owner');
        $otherConnection = IntegrationConnection::create([
            'tenant_id' => $other->id, 'name' => 'Globex books', 'product' => 'custom', 'status' => 'active',
        ]);

        $response = $this->api($otherOwner, $other)
            ->withHeader('Idempotency-Key', 'g-2')
            ->postJson(self::BASE."/connections/{$otherConnection->id}/vouchers/batch", ['items' => [$this->voucher([
                'lines' => [
                    ['account_code' => 'SECRET', 'debit' => 10, 'credit' => 0],
                    ['account_code' => 'SECRET', 'debit' => 0, 'credit' => 10],
                ],
            ])]]);

        $this->assertSame('unmapped_ledger', $response->json('data.records.0.error_code'));
    }

    public function test_plan_without_accounting_is_refused(): void
    {
        $plan = \App\Domains\Platform\Models\Plan::create(['name' => 'CRM Only', 'slug' => 'crm-only', 'features' => ['crm'], 'is_active' => true]);
        $this->tenant->update(['plan_id' => $plan->id]);

        $this->api()->getJson(self::BASE.'/connections')
            ->assertForbidden()
            ->assertJsonPath('message', 'Accounting is not included in your plan.');
    }

    // ── Permissions ───────────────────────────────────────────────────────

    public function test_permissions_are_seeded_for_admins_only(): void
    {
        $accountant = $this->makeUser($this->tenant, 'accountant@acme.test', 'accountant');

        $this->api($accountant)->getJson(self::BASE.'/connections')->assertForbidden();
        $this->api($accountant)->postJson(self::BASE.'/connections', ['name' => 'X', 'product' => 'tally'])->assertForbidden();
        $this->api($accountant)->withHeader('Idempotency-Key', 'a')
            ->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher()]])
            ->assertForbidden();

        $this->api()->getJson(self::BASE.'/connections')->assertOk();
    }

    public function test_connector_token_is_tied_to_its_connection_and_this_api(): void
    {
        $second = IntegrationConnection::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Branch', 'product' => 'custom', 'status' => 'active',
        ]);

        $token = $this->api()->postJson($this->url('/tokens'), ['name' => 'relay'])->assertCreated()->json('data.token');

        // Sanctum::actingAs above would otherwise stand in for the real token.
        $this->app['auth']->forgetGuards();

        $bearer = ['Authorization' => "Bearer {$token}", 'X-Tenant' => 'acme', 'Accept' => 'application/json'];

        $this->getJson($this->url(), $bearer)->assertOk();
        $this->app['auth']->forgetGuards();

        $listed = $this->getJson(self::BASE.'/connections', $bearer)->assertOk();
        $this->assertSame([$this->connection->id], array_column($listed->json('data'), 'id'));
        $this->assertSame(1, $listed->json('meta.total'));
        $this->app['auth']->forgetGuards();

        $this->getJson(self::BASE."/connections/{$second->id}", $bearer)->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/me', $bearer)->assertUnauthorized();
    }

    public function test_full_access_token_lists_every_connection_in_the_tenant(): void
    {
        IntegrationConnection::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Branch', 'product' => 'custom', 'status' => 'active',
        ]);

        $token = $this->owner->createToken('login')->plainTextToken;
        $bearer = ['Authorization' => "Bearer {$token}", 'X-Tenant' => 'acme', 'Accept' => 'application/json'];

        $this->assertSame(2, $this->getJson(self::BASE.'/connections', $bearer)->assertOk()->json('meta.total'));
    }

    // ── Idempotency ───────────────────────────────────────────────────────

    public function test_idempotency_key_is_required(): void
    {
        $this->api()->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher()]])->assertStatus(400);
    }

    public function test_replaying_a_request_returns_the_original_and_posts_nothing(): void
    {
        $this->seedLedgers();

        $first = $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();
        $replay = $this->sendVoucher($this->voucher(), 'k-1')->assertOk();

        $replay->assertHeader('X-Idempotent-Replay', 'true');
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    public function test_reusing_a_key_with_a_different_body_is_refused(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $this->sendVoucher($this->voucher(['number' => 'OTHER']), 'k-1')->assertStatus(409);
    }

    public function test_resending_a_voucher_under_a_new_key_does_not_duplicate_it(): void
    {
        $this->seedLedgers();

        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();
        $response = $this->sendVoucher($this->voucher(), 'k-2')->assertCreated();

        $this->assertSame('unchanged', $response->json('data.records.0.outcome'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    public function test_same_external_id_on_two_connections_are_separate_vouchers(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $second = IntegrationConnection::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Branch', 'product' => 'custom', 'status' => 'active',
        ]);
        $response = $this->api()->withHeader('Idempotency-Key', 'k-1')
            ->postJson(self::BASE."/connections/{$second->id}/vouchers/batch", ['items' => [$this->voucher()]]);

        // Ledgers aren't mapped on the second connection: isolated, not shared.
        $this->assertSame('unmapped_ledger', $response->json('data.records.0.error_code'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    // ── Journal integrity ─────────────────────────────────────────────────

    public function test_unbalanced_voucher_never_reaches_the_ledger(): void
    {
        $this->seedLedgers();

        $response = $this->sendVoucher($this->voucher(['lines' => [
            ['ledger_external_id' => 'L-RENT', 'debit' => 1000, 'credit' => 0],
            ['ledger_external_id' => 'L-BANK', 'debit' => 0, 'credit' => 999.99],
        ]]), 'k-1')->assertCreated();

        $this->assertSame('failed', $response->json('data.records.0.outcome'));
        $this->assertSame('unbalanced', $response->json('data.records.0.error_code'));
        $this->assertSame(0, $this->integrationJournals()->count());
    }

    public function test_posted_journal_balances_and_is_linked_to_its_ref(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $journal = $this->integrationJournals()->with('entries', 'voucherDetail')->firstOrFail();

        $this->assertSame(Journal::STATUS_POSTED, $journal->status);
        $this->assertSame('payment', $journal->voucher_type);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(1000.0, $journal->entries->sum('debit'));
        $this->assertSame(1000.0, $journal->entries->sum('credit'));
        $this->assertSame('PAY-77', $journal->voucherDetail->reference_no);
        $this->assertSame(IntegrationRef::JOURNAL_REFERENCE_TYPE, $journal->reference_type);
    }

    public function test_one_bad_record_does_not_stop_the_rest(): void
    {
        $this->seedLedgers();

        $response = $this->api()->withHeader('Idempotency-Key', 'k-1')->postJson($this->url('/vouchers/batch'), ['items' => [
            $this->voucher(['external_id' => 'V-OK']),
            $this->voucher(['external_id' => '']),
            $this->voucher(['external_id' => 'V-CLOSED', 'date' => now()->subYears(5)->toDateString()]),
        ]])->assertCreated();

        $this->assertSame(1, $response->json('data.created_count'));
        $this->assertSame(2, $response->json('data.failed_count'));
        $this->assertSame('completed_with_issues', $response->json('data.status'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    public function test_failed_record_can_be_retried_once_the_ledger_exists(): void
    {
        $response = $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();
        $this->assertSame('unmapped_ledger', $response->json('data.records.0.error_code'));

        $this->seedLedgers();

        $retry = $this->api()->postJson($this->url('/batches/'.$response->json('data.id').'/retry'))->assertOk();

        $this->assertSame('created', $retry->json('data.records.0.outcome'));
        $this->assertSame(2, $retry->json('data.records.0.attempts'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    public function test_changed_voucher_reverses_the_old_entry_and_posts_the_new(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $response = $this->sendVoucher($this->voucher(['lines' => [
            ['ledger_external_id' => 'L-RENT', 'debit' => 1500, 'credit' => 0],
            ['ledger_external_id' => 'L-BANK', 'debit' => 0, 'credit' => 1500],
        ]]), 'k-2')->assertCreated();

        $this->assertSame('updated', $response->json('data.records.0.outcome'));

        $rent = ChartOfAccount::query()->where('name', 'Office Rent')->firstOrFail();
        $net = \App\Domains\Accounting\Models\JournalEntry::query()
            ->where('chart_of_account_id', $rent->id)
            ->selectRaw('SUM(debit) - SUM(credit) as net')
            ->value('net');

        $this->assertEquals(1500, $net, 'The ledger must show only the new amount.');
        $this->assertSame(1, $this->integrationJournals()->where('status', Journal::STATUS_REVERSED)->count());
    }

    public function test_renaming_a_ledger_does_not_look_like_a_changed_voucher(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(['lines' => [
            ['ledger_name' => 'Office Rent', 'debit' => 1000, 'credit' => 0],
            ['ledger_name' => 'HDFC Bank', 'debit' => 0, 'credit' => 1000],
        ]]), 'k-1')->assertCreated();

        $this->api()->withHeader('Idempotency-Key', 'ren')->postJson($this->url('/ledgers/batch'), ['items' => [
            ['external_id' => 'L-RENT', 'name' => 'Rent Paid', 'type' => 'expense'],
        ]])->assertCreated();

        $response = $this->sendVoucher($this->voucher(['lines' => [
            ['ledger_name' => 'Rent Paid', 'debit' => 1000, 'credit' => 0],
            ['ledger_name' => 'HDFC Bank', 'debit' => 0, 'credit' => 1000],
        ]]), 'k-2')->assertCreated();

        $this->assertSame('unchanged', $response->json('data.records.0.outcome'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    public function test_cancelled_voucher_is_reversed_once(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $this->sendVoucher(['external_id' => 'V-1', 'is_cancelled' => true], 'k-2')->assertCreated();
        $again = $this->sendVoucher(['external_id' => 'V-1', 'is_cancelled' => true], 'k-3')->assertCreated();

        $this->assertSame('unchanged', $again->json('data.records.0.outcome'));
        $this->assertSame(1, $this->integrationJournals()->where('status', Journal::STATUS_REVERSED)->count());
        $this->assertSame(2, $this->integrationJournals()->count());
    }

    // ── Maker-checker ─────────────────────────────────────────────────────

    public function test_tenant_approval_rules_apply_to_imports(): void
    {
        $this->seedLedgers();
        $sync = $this->makeServiceUser();

        app(JournalApprovalSettings::class)->update($this->tenant->id, [
            'enabled' => true, 'threshold' => 0, 'approvers_post_directly' => true,
        ]);

        $response = $this->api($sync)->withHeader('Idempotency-Key', 'k-1')
            ->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher()]])
            ->assertCreated();

        $this->assertSame('pending_approval', $response->json('data.records.0.outcome'));
        $journal = $this->integrationJournals()->firstOrFail();
        $this->assertSame(Journal::STATUS_PENDING_APPROVAL, $journal->status);

        // Re-sync while it waits: no second copy.
        $again = $this->api($sync)->withHeader('Idempotency-Key', 'k-2')
            ->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher()]]);
        $this->assertSame('pending_approval', $again->json('data.records.0.outcome'));
        $this->assertSame(1, $this->integrationJournals()->count());

        // A person approves it, and only then does it count.
        app(JournalService::class)->approve($journal->id, $this->owner->id);
        $this->assertSame(Journal::STATUS_POSTED, $journal->fresh()->status);
    }

    public function test_source_change_while_awaiting_approval_is_a_conflict(): void
    {
        $this->seedLedgers();
        $sync = $this->makeServiceUser();

        app(JournalApprovalSettings::class)->update($this->tenant->id, [
            'enabled' => true, 'threshold' => 0, 'approvers_post_directly' => true,
        ]);

        $this->api($sync)->withHeader('Idempotency-Key', 'k-1')->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher()]]);
        $response = $this->api($sync)->withHeader('Idempotency-Key', 'k-2')->postJson($this->url('/vouchers/batch'), ['items' => [$this->voucher(['lines' => [
            ['ledger_external_id' => 'L-RENT', 'debit' => 2000, 'credit' => 0],
            ['ledger_external_id' => 'L-BANK', 'debit' => 0, 'credit' => 2000],
        ]])]]);

        $this->assertSame('conflict', $response->json('data.records.0.outcome'));
        $this->assertSame(IntegrationConflict::REASON_AWAITING_APPROVAL, $response->json('data.records.0.error_code'));
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    /** Can sync but can't approve, which is how the docs say to set this up. */
    private function makeServiceUser(): User
    {
        $user = $this->makeUser($this->tenant, 'sync@acme.test', 'accountant');
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();

        foreach (['accounting.integrations.view', 'accounting.integrations.sync'] as $name) {
            \App\Models\Access\RolePermission::create([
                'role_id' => $role->id,
                'permission_id' => \App\Models\Access\Permission::query()->where('name', $name)->value('id'),
                'scope' => 'tenant',
            ]);
        }

        return $user;
    }

    // ── Conflicts ─────────────────────────────────────────────────────────

    public function test_locally_reversed_voucher_is_a_conflict_not_a_repost(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $journal = $this->integrationJournals()->firstOrFail();
        app(JournalService::class)->reverse($journal->id, 'Mistake', $this->owner->id);

        $response = $this->sendVoucher($this->voucher(), 'k-2');

        $this->assertSame('conflict', $response->json('data.records.0.outcome'));
        $this->assertSame(IntegrationConflict::REASON_REVERSED_LOCALLY, $response->json('data.records.0.error_code'));
        $this->assertSame(2, $this->integrationJournals()->count());

        // Syncing again doesn't pile up copies of the conflict.
        $this->sendVoucher($this->voucher(), 'k-3');
        $open = $this->api()->getJson($this->url('/conflicts'))->assertOk();
        $this->assertSame(1, $open->json('meta.total'));

        // Keep local: the same version stops being raised.
        $id = $open->json('data.0.id');
        $this->api()->postJson($this->url("/conflicts/{$id}/resolve"), ['resolution' => 'keep_local'])->assertOk();

        $quiet = $this->sendVoucher($this->voucher(), 'k-4');
        $this->assertSame('unchanged', $quiet->json('data.records.0.outcome'));
        $this->assertSame(0, $this->api()->getJson($this->url('/conflicts'))->json('meta.total'));
    }

    public function test_apply_incoming_reposts_a_locally_reversed_voucher(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        app(JournalService::class)->reverse($this->integrationJournals()->firstOrFail()->id, 'Mistake', $this->owner->id);
        $this->sendVoucher($this->voucher(), 'k-2');

        $id = $this->api()->getJson($this->url('/conflicts'))->json('data.0.id');
        $this->api()->postJson($this->url("/conflicts/{$id}/resolve"), ['resolution' => 'apply_incoming'])->assertOk();

        $this->assertSame(1, $this->integrationJournals()->where('status', Journal::STATUS_POSTED)->whereNull('reversed_journal_id')
            ->where('memo', 'Rent')->count());
    }

    public function test_change_that_cannot_be_reversed_is_a_conflict_and_changes_nothing(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $journal = $this->integrationJournals()->with('entries')->firstOrFail();
        \App\Domains\Accounting\Models\JournalEntry::query()->whereKey($journal->entries->first()->id)->update(['is_reconciled' => true]);

        $response = $this->sendVoucher($this->voucher(['lines' => [
            ['ledger_external_id' => 'L-RENT', 'debit' => 1500, 'credit' => 0],
            ['ledger_external_id' => 'L-BANK', 'debit' => 0, 'credit' => 1500],
        ]]), 'k-2');

        $this->assertSame(IntegrationConflict::REASON_CANNOT_REVERSE, $response->json('data.records.0.error_code'));
        $this->assertSame(Journal::STATUS_POSTED, $journal->fresh()->status);
        $this->assertSame(1, $this->integrationJournals()->count());
    }

    public function test_resolve_needs_its_own_permission(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1');
        app(JournalService::class)->reverse($this->integrationJournals()->firstOrFail()->id, 'Mistake', $this->owner->id);
        $this->sendVoucher($this->voucher(), 'k-2');
        $id = $this->api()->getJson($this->url('/conflicts'))->json('data.0.id');

        $this->api($this->makeServiceUser())
            ->postJson($this->url("/conflicts/{$id}/resolve"), ['resolution' => 'keep_local'])
            ->assertForbidden();
    }

    // ── Masters ───────────────────────────────────────────────────────────

    public function test_ledger_with_a_matching_name_links_instead_of_duplicating(): void
    {
        $existing = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit',
        ]);

        $response = $this->api()->withHeader('Idempotency-Key', 'k')->postJson($this->url('/ledgers/batch'), ['items' => [
            ['external_id' => 'L-1', 'name' => 'BANK', 'type' => 'asset'],
        ]])->assertCreated();

        $this->assertSame($existing->id, $response->json('data.records.0.local_id'));
        $this->assertSame(1, ChartOfAccount::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_ledger_type_mismatch_is_a_conflict(): void
    {
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5000', 'name' => 'Commission', 'type' => 'expense', 'normal_balance' => 'debit',
        ]);

        $response = $this->api()->withHeader('Idempotency-Key', 'k')->postJson($this->url('/ledgers/batch'), ['items' => [
            ['external_id' => 'L-1', 'name' => 'Commission', 'type' => 'income'],
        ]]);

        $this->assertSame(IntegrationConflict::REASON_TYPE_CHANGE, $response->json('data.records.0.error_code'));
    }

    public function test_system_account_is_never_renamed(): void
    {
        $account = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset',
            'normal_balance' => 'debit', 'is_system' => true,
        ]);

        $this->api()->withHeader('Idempotency-Key', 'a')->postJson($this->url('/ledgers/batch'), ['items' => [
            ['external_id' => 'L-1', 'name' => 'Accounts Receivable', 'type' => 'asset'],
        ]])->assertCreated();

        $response = $this->api()->withHeader('Idempotency-Key', 'b')->postJson($this->url('/ledgers/batch'), ['items' => [
            ['external_id' => 'L-1', 'name' => 'Debtors', 'type' => 'asset'],
        ]]);

        $this->assertSame(IntegrationConflict::REASON_SYSTEM_ACCOUNT, $response->json('data.records.0.error_code'));
        $this->assertSame('Accounts Receivable', $account->fresh()->name);
    }

    public function test_manual_mapping_lets_a_different_name_post(): void
    {
        $account = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5900', 'name' => 'Other Expense', 'type' => 'expense', 'normal_balance' => 'debit',
        ]);
        $this->seedLedgers();

        $this->api()->postJson($this->url('/mappings'), [
            'entity_type' => 'ledger', 'external_id' => 'L-MISC', 'external_name' => 'Misc', 'local_id' => $account->id,
        ])->assertOk();

        $this->sendVoucher($this->voucher(['lines' => [
            ['ledger_name' => 'Misc', 'debit' => 50, 'credit' => 0],
            ['ledger_external_id' => 'L-BANK', 'debit' => 0, 'credit' => 50],
        ]]), 'k')->assertCreated();

        $this->assertSame(1, $this->integrationJournals()->count());
        $this->assertSame('Other Expense', $account->fresh()->name);
    }

    public function test_mapping_refuses_another_tenants_account(): void
    {
        $other = $this->makeTenant('globex');
        $foreign = ChartOfAccount::withoutGlobalScopes()->create([
            'tenant_id' => $other->id, 'code' => '9', 'name' => 'Theirs', 'type' => 'asset', 'normal_balance' => 'debit',
        ]);
        $this->useTenant($this->tenant);

        $this->api()->postJson($this->url('/mappings'), [
            'entity_type' => 'ledger', 'external_id' => 'L-X', 'local_id' => $foreign->id,
        ])->assertStatus(422);
    }

    // ── Outbound feed ─────────────────────────────────────────────────────

    public function test_feed_excludes_what_came_in_and_lists_local_entries_until_acked(): void
    {
        $this->seedLedgers();
        $this->sendVoucher($this->voucher(), 'k-1')->assertCreated();

        $this->assertSame([], $this->api()->getJson($this->url('/feed/vouchers'))->assertOk()->json('data'));

        $rent = ChartOfAccount::query()->where('name', 'Office Rent')->firstOrFail();
        $bank = ChartOfAccount::query()->where('name', 'HDFC Bank')->firstOrFail();
        $local = app(JournalService::class)->post([
            ['chart_of_account_id' => $rent->id, 'debit' => 70, 'credit' => 0],
            ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 70],
        ], ['tenant_id' => $this->tenant->id]);

        $feed = $this->api()->getJson($this->url('/feed/vouchers'))->json('data');
        $this->assertCount(1, $feed);
        $this->assertSame($local->id, $feed[0]['local_id']);
        $this->assertSame('journal', $feed[0]['voucher_type']);
        $this->assertEquals(70, array_sum(array_column($feed[0]['lines'], 'debit')));
        $this->assertEquals(70, array_sum(array_column($feed[0]['lines'], 'credit')));

        // Still there until acknowledged, so a client crash loses nothing.
        $this->assertCount(1, $this->api()->getJson($this->url('/feed/vouchers'))->json('data'));

        $this->api()->postJson($this->url('/feed/vouchers/ack'), ['items' => [['local_id' => $local->id]]])->assertOk();
        $this->assertSame([], $this->api()->getJson($this->url('/feed/vouchers'))->json('data'));
    }

    public function test_feed_sends_both_a_reversed_journal_and_its_reversal(): void
    {
        $this->seedLedgers();
        $rent = ChartOfAccount::query()->where('name', 'Office Rent')->firstOrFail();
        $bank = ChartOfAccount::query()->where('name', 'HDFC Bank')->firstOrFail();

        $local = app(JournalService::class)->post([
            ['chart_of_account_id' => $rent->id, 'debit' => 70, 'credit' => 0],
            ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 70],
        ], ['tenant_id' => $this->tenant->id]);
        app(JournalService::class)->reverse($local->id, 'Oops', $this->owner->id);

        $this->assertCount(2, $this->api()->getJson($this->url('/feed/vouchers'))->json('data'));
    }

    public function test_feed_leaves_out_pending_opening_and_closing_journals(): void
    {
        $this->seedLedgers();
        $rent = ChartOfAccount::query()->where('name', 'Office Rent')->firstOrFail();
        $bank = ChartOfAccount::query()->where('name', 'HDFC Bank')->firstOrFail();
        $lines = [
            ['chart_of_account_id' => $rent->id, 'debit' => 70, 'credit' => 0],
            ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => 70],
        ];

        $service = app(JournalService::class);
        $service->post($lines, ['tenant_id' => $this->tenant->id, 'reference_type' => 'chart_of_account_opening_balance', 'reference_id' => 1]);
        $service->post($lines, ['tenant_id' => $this->tenant->id, 'source' => Journal::SOURCE_YEAR_END_CLOSE]);

        app(JournalApprovalSettings::class)->update($this->tenant->id, ['enabled' => true, 'threshold' => 0, 'approvers_post_directly' => false]);
        $service->submit($lines, ['tenant_id' => $this->tenant->id]);

        $this->assertSame([], $this->api()->getJson($this->url('/feed/vouchers'))->json('data'));
    }
}
