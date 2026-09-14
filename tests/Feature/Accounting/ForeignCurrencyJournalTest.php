<?php

namespace Tests\Feature\Accounting;

use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Support\AccountCode;
use App\Domains\HRMS\Models\Company;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ForeignCurrencyJournalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private ChartOfAccount $receivable;
    private ChartOfAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise', 'currency' => 'GBP']);
        app(TenantContext::class)->set($this->tenant);

        $this->company = Company::create(['company_name' => 'UK Ltd', 'currency' => 'GBP']);
        // Fiscal years/periods are company-scoped; create them under this company, as
        // the HTTP request (which resolves the company) will look them up that way.
        app(CompanyContext::class)->set($this->company);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        $this->receivable = $this->account(AccountCode::AR);
        $this->bank = $this->account(AccountCode::BANK);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        ExchangeRate::create([
            'tenant_id' => $this->tenant->id, 'from_currency' => 'USD', 'to_currency' => 'GBP',
            'rate' => 0.79, 'effective_date' => '2026-09-01', 'source' => ExchangeRate::SOURCE_MANUAL,
        ]);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    /** @param array<string, mixed> $meta */
    private function postJournal(array $lines, array $meta = []): Journal
    {
        return app(JournalService::class)->post($lines, $meta + [
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'journal_date' => '2026-09-10',
        ]);
    }

    /** @test */
    public function a_foreign_journal_is_converted_to_base_at_the_rate_for_its_date(): void
    {
        $journal = $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 1000],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 1000],
        ], ['currency_code' => 'usd']);

        $this->assertSame('USD', $journal->currency_code);
        $this->assertEqualsWithDelta(0.79, $journal->exchange_rate, 1e-9);
        $this->assertEqualsWithDelta(790.0, $journal->total_debit, 1e-9);
        $this->assertEqualsWithDelta(790.0, $journal->total_credit, 1e-9);

        $debitLine = $journal->entries->firstWhere('chart_of_account_id', $this->receivable->id);
        $this->assertEqualsWithDelta(790.0, $debitLine->debit, 1e-9);
        $this->assertEqualsWithDelta(1000.0, $debitLine->foreign_debit, 1e-9);
        $this->assertNull($debitLine->foreign_credit);
    }

    /** @test */
    public function an_explicit_rate_overrides_the_rate_table(): void
    {
        $journal = $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 1000],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 1000],
        ], ['currency_code' => 'USD', 'exchange_rate' => '0.8']);

        $this->assertEqualsWithDelta(0.8, $journal->exchange_rate, 1e-9);
        $this->assertEqualsWithDelta(800.0, $journal->total_debit, 1e-9);
    }

    /** @test */
    public function a_conversion_rounding_residual_is_posted_to_round_off(): void
    {
        // 1.00 = 0.50 + 0.50 in USD, but at 0.333333 the base lines are 0.33 vs 0.17 + 0.17.
        $journal = $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 1.00],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 0.50],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 0.50],
        ], ['currency_code' => 'USD', 'exchange_rate' => 0.333333]);

        $this->assertCount(4, $journal->entries);

        $roundOff = $journal->entries->firstWhere('chart_of_account_id', $this->account(AccountCode::ROUND_OFF)->id);
        $this->assertEqualsWithDelta(0.01, $roundOff->debit, 1e-9);
        $this->assertNull($roundOff->foreign_debit);

        $this->assertEqualsWithDelta(0.34, $journal->total_debit, 1e-9);
        $this->assertEqualsWithDelta(0.34, $journal->total_credit, 1e-9);
    }

    /** @test */
    public function foreign_lines_must_balance_in_the_transaction_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not balanced');

        $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 1000],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 999],
        ], ['currency_code' => 'USD']);
    }

    /** @test */
    public function a_missing_rate_rejects_the_journal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No exchange rate from EUR to GBP');

        $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 100],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 100],
        ], ['currency_code' => 'EUR']);
    }

    /** @test */
    public function the_base_currency_code_posts_a_plain_base_journal(): void
    {
        $journal = $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 50],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 50],
        ], ['currency_code' => 'GBP', 'exchange_rate' => 5]);

        $this->assertNull($journal->currency_code);
        $this->assertEqualsWithDelta(1.0, $journal->exchange_rate, 1e-9);
        $this->assertEqualsWithDelta(50.0, $journal->total_debit, 1e-9);
    }

    /** @test */
    public function reversing_a_foreign_journal_mirrors_both_currencies_at_the_original_rate(): void
    {
        $original = $this->postJournal([
            ['chart_of_account_id' => $this->receivable->id, 'debit' => 1000],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 1000],
        ], ['currency_code' => 'USD']);

        // A later rate change must not affect the reversal.
        ExchangeRate::create([
            'tenant_id' => $this->tenant->id, 'from_currency' => 'USD', 'to_currency' => 'GBP',
            'rate' => 0.9, 'effective_date' => '2026-09-05', 'source' => ExchangeRate::SOURCE_MANUAL,
        ]);

        $this->travelTo('2026-09-20');
        $reversal = app(JournalService::class)->reverse($original->id, 'Posted in error');

        $this->assertSame('USD', $reversal->currency_code);
        $this->assertEqualsWithDelta(0.79, $reversal->exchange_rate, 1e-9);

        $line = $reversal->entries->firstWhere('chart_of_account_id', $this->receivable->id);
        $this->assertEqualsWithDelta(790.0, $line->credit, 1e-9);
        $this->assertEqualsWithDelta(1000.0, $line->foreign_credit, 1e-9);
        $this->assertSame(Journal::STATUS_REVERSED, $original->fresh()->status);
    }

    /** @test */
    public function an_accountant_can_look_up_a_rate_and_post_a_foreign_journal_from_the_form(): void
    {
        $this->seed(RbacSeeder::class);
        $accountant = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'accountant@example.com', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $client = $this->actingAs($accountant)->withHeader('X-Tenant', 'test-tenant');

        $client->getJson(route('accounting.journals.exchange-rate', ['currency' => 'USD', 'date' => '2026-09-10']))
            ->assertOk()
            ->assertJson(['rate' => 0.79, 'base' => 'GBP', 'currency' => 'USD']);

        $client->getJson(route('accounting.journals.exchange-rate', ['currency' => 'EUR', 'date' => '2026-09-10']))
            ->assertNotFound();

        $client->get(route('accounting.journals.create'))->assertOk()->assertSee('GBP — Pound Sterling (base)', false);

        $client->post(route('accounting.journals.store'), [
            'journal_date' => '2026-09-10',
            'currency_code' => 'USD',
            'items' => [
                ['chart_of_account_id' => $this->receivable->id, 'debit' => '250', 'credit' => '0'],
                ['chart_of_account_id' => $this->bank->id, 'debit' => '0', 'credit' => '250'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $journal = Journal::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
        $this->assertSame('USD', $journal->currency_code);
        $this->assertEqualsWithDelta(197.5, $journal->total_debit, 1e-9);

        $client->get(route('accounting.journals.show', $journal))
            ->assertOk()
            ->assertSee('Transaction Currency')
            ->assertSee('Dr 250.00');
    }
}
