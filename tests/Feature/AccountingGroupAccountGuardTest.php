<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * JournalService::post() must refuse to post directly to a "group" account —
 * one that has child accounts under it (e.g. a header like "1000 - Assets")
 * — since nothing in the Chart of Accounts UI or voucher/journal forms
 * otherwise stops a user from picking one, and a posting there would corrupt
 * the Balance Sheet by mixing a group-level row into the leaf-account list.
 */
class AccountingGroupAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** @test */
    public function posting_to_a_group_account_with_children_is_rejected(): void
    {
        $assetsHeader = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Assets',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1010',
            'name' => 'Cash on Hand',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'parent_id' => $assetsHeader->id,
        ]);

        $revenue = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4010',
            'name' => 'Sales Revenue',
            'type' => ChartOfAccount::TYPE_INCOME,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('group account');

        app(JournalService::class)->post([
            ['chart_of_account_id' => $assetsHeader->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 100],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);
    }

    /** @test */
    public function posting_to_a_leaf_account_still_works(): void
    {
        $assetsHeader = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1000',
            'name' => 'Assets',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $cash = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1010',
            'name' => 'Cash on Hand',
            'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'parent_id' => $assetsHeader->id,
        ]);

        $revenue = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4010',
            'name' => 'Sales Revenue',
            'type' => ChartOfAccount::TYPE_INCOME,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $journal = app(JournalService::class)->post([
            ['chart_of_account_id' => $cash->id, 'debit' => 100, 'credit' => 0],
            ['chart_of_account_id' => $revenue->id, 'debit' => 0, 'credit' => 100],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $this->assertSame(100.0, (float) $journal->total_debit);
    }
}
