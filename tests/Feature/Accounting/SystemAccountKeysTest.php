<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\AccountResolverService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SystemAccountKeysTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Keys', 'slug' => 'keys',
            'status' => 'active', 'plan' => 'enterprise',
        ]);
        app(TenantContext::class)->set($this->tenant);
    }

    private function provision(): void
    {
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
    }

    private function account(string $code): ?ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', $code)->first();
    }

    /** Renumber bypassing the service lock, as a tenant could before it existed. */
    private function renumber(string $from, string $to): ChartOfAccount
    {
        $account = $this->account($from);
        $account->forceFill(['code' => $to])->save();

        return $account->fresh();
    }

    public function test_every_template_key_points_at_a_distinct_code(): void
    {
        $this->assertSame(count(SystemAccount::TEMPLATE), count(array_unique(SystemAccount::TEMPLATE)));
        $this->assertSame('1100', SystemAccount::templateCode(SystemAccount::AR));
        $this->assertSame(SystemAccount::AR, SystemAccount::keyForTemplateCode('1100'));
        $this->assertNull(SystemAccount::keyForTemplateCode('9999'));
    }

    /**
     * The template rows in ChartOfAccountsService::provisionDefaults() each
     * carry an explicit 'key'; SystemAccount::TEMPLATE is what the migration
     * backfills existing tenants from. The two must never disagree.
     */
    public function test_template_row_keys_match_the_system_account_map(): void
    {
        $this->provision();

        $provisioned = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->pluck('system_key', 'code')
            ->all();

        $expected = array_flip(SystemAccount::TEMPLATE); // code => key
        ksort($provisioned);
        ksort($expected);

        $this->assertSame(
            $expected,
            $provisioned,
            'A template row in provisionDefaults() and SystemAccount::TEMPLATE disagree on code/key, '
            . 'or one has a row the other lacks. Add or fix the entry in both places.'
        );
    }

    public function test_system_accounts_are_found_after_the_tenant_renumbers_them(): void
    {
        $this->provision();
        $receivable = $this->renumber('1100', '1105');

        $this->assertTrue($receivable->is(app(SystemAccountService::class)->get(SystemAccount::AR, $this->tenant->id)));

        // The shared resolver's code fallbacks go through the key too.
        $resolved = app(AccountResolverService::class)->resolveAccount(null, $this->tenant->id, fallbackCode: '1100');
        $this->assertTrue($receivable->is($resolved));
    }

    public function test_a_new_account_reusing_an_old_system_code_is_not_mistaken_for_the_system_account(): void
    {
        $this->provision();
        $receivable = $this->renumber('1100', '1105');

        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1100', 'name' => 'Sundry Debtors - Old',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $this->assertTrue($receivable->is(app(SystemAccountService::class)->get(SystemAccount::AR, $this->tenant->id)));
    }

    public function test_unkeyed_accounts_from_before_system_keys_are_still_found_by_their_code(): void
    {
        $legacy = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '2010', 'name' => 'Accounts Payable',
            'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->assertTrue($legacy->is(app(SystemAccountService::class)->get(SystemAccount::AP, $this->tenant->id)));
        $this->assertNull(app(SystemAccountService::class)->get(SystemAccount::AR, $this->tenant->id));
    }

    public function test_reprovisioning_updates_a_renumbered_account_in_place_instead_of_duplicating_it(): void
    {
        $this->provision();
        $receivable = $this->renumber('1100', '1105');

        $this->provision();

        $this->assertSame('1105', $receivable->fresh()->code);
        $this->assertNull($this->account('1100'));
        $this->assertSame(1, ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('system_key', SystemAccount::AR)->count());
    }

    public function test_reprovisioning_keys_rows_created_before_system_keys_existed(): void
    {
        $this->provision();
        ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['system_key' => null]);

        $this->provision();

        $this->assertSame(SystemAccount::AR, $this->account('1100')->system_key);
    }

    public function test_the_code_of_a_system_account_cannot_be_changed(): void
    {
        $this->provision();
        $receivable = $this->account('1100');

        $this->expectException(InvalidArgumentException::class);
        app(ChartOfAccountsService::class)->update($receivable->id, ['code' => '1105']);
    }

    public function test_a_system_account_can_still_be_renamed_with_its_code_unchanged(): void
    {
        $this->provision();
        $receivable = $this->account('1100');

        app(ChartOfAccountsService::class)->update($receivable->id, ['code' => '1100', 'name' => 'Sundry Debtors']);

        $this->assertSame('Sundry Debtors', $receivable->fresh()->name);
    }

    public function test_the_code_of_a_tenant_created_account_can_be_changed(): void
    {
        $this->provision();
        $own = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1024', 'name' => 'Axis Bank',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        app(ChartOfAccountsService::class)->update($own->id, ['code' => '1025']);

        $this->assertSame('1025', $own->fresh()->code);
    }
}
