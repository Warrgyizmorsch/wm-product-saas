<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Core\Tenant\TenantContext;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The journal balance check accumulates in integer minor units rather than
 * comparing rounded floats. These are characterisation tests: they also pass
 * against the old float implementation, and exist to pin that the switch did
 * not change outcomes — fractional lines still balance, and a one-paisa
 * difference still throws.
 */
class JournalBalancePrecisionTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $journals;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Precision Tenant', 'slug' => 'precision-tenant',
            'status' => 'active', 'plan' => 'enterprise',
        ]);

        app(TenantContext::class)->set($this->tenant);

        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->journals = app(JournalService::class);
    }

    private function accountId(string $code): int
    {
        return ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', $code)
            ->firstOrFail()
            ->id;
    }

    /**
     * 0.1 + 0.2 is 0.30000000000000004 in IEEE doubles. Two debit lines
     * summing to one 0.3 credit line must post.
     *
     * @test
     */
    public function lines_that_sum_through_a_float_representation_gap_still_balance(): void
    {
        $journal = $this->journals->post([
            ['chart_of_account_id' => $this->accountId('1020'), 'debit' => 0.1, 'credit' => 0],
            ['chart_of_account_id' => $this->accountId('1020'), 'debit' => 0.2, 'credit' => 0],
            ['chart_of_account_id' => $this->accountId('4010'), 'debit' => 0, 'credit' => 0.3],
        ], [
            'tenant_id' => $this->tenant->id,
            'journal_date' => now(),
            'memo' => 'float precision probe',
        ]);

        $this->assertNotNull($journal->id);
        $this->assertEqualsWithDelta(0.3, (float) $journal->total_debit, 0.001);
        $this->assertEqualsWithDelta(0.3, (float) $journal->total_credit, 0.001);
    }

    /** @test */
    public function a_longer_chain_of_fractional_lines_balances(): void
    {
        $journal = $this->journals->post([
            ['chart_of_account_id' => $this->accountId('1020'), 'debit' => 33.33, 'credit' => 0],
            ['chart_of_account_id' => $this->accountId('1020'), 'debit' => 33.33, 'credit' => 0],
            ['chart_of_account_id' => $this->accountId('1020'), 'debit' => 33.34, 'credit' => 0],
            ['chart_of_account_id' => $this->accountId('4010'), 'debit' => 0, 'credit' => 100.00],
        ], [
            'tenant_id' => $this->tenant->id,
            'journal_date' => now(),
        ]);

        $this->assertNotNull($journal->id);
    }

    /**
     * The tightening must not make the check permissive — a genuine one-paisa
     * difference is still an unbalanced journal.
     *
     * @test
     */
    public function a_genuinely_unbalanced_journal_is_still_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not balanced');

        $this->journals->post([
            ['chart_of_account_id' => $this->accountId('1020'), 'debit' => 100.00, 'credit' => 0],
            ['chart_of_account_id' => $this->accountId('4010'), 'debit' => 0, 'credit' => 100.01],
        ], [
            'tenant_id' => $this->tenant->id,
            'journal_date' => now(),
        ]);
    }
}
