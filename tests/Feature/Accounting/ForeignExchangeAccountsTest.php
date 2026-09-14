<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Support\AccountCode;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForeignExchangeAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug,
            'status' => 'active', 'plan' => 'enterprise',
        ]);

        app(TenantContext::class)->set($tenant);

        return $tenant;
    }

    private function account(int $tenantId, string $code): ?ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $tenantId)->where('code', $code)->first();
    }

    private function runBackfill(): void
    {
        (require database_path('migrations/2026_09_14_000000_backfill_foreign_exchange_accounts.php'))->up();
    }

    /** @test */
    public function new_tenants_are_provisioned_with_fx_gain_and_loss_accounts(): void
    {
        $tenant = $this->makeTenant('fx-new');
        app(ChartOfAccountsService::class)->provisionDefaults($tenant->id);

        $gain = $this->account($tenant->id, AccountCode::FX_GAIN);
        $loss = $this->account($tenant->id, AccountCode::FX_LOSS);

        $this->assertSame(ChartOfAccount::TYPE_INCOME, $gain->type);
        $this->assertSame(ChartOfAccount::BALANCE_CREDIT, $gain->normal_balance);
        $this->assertSame('4000', $gain->parent->code);

        $this->assertSame(ChartOfAccount::TYPE_EXPENSE, $loss->type);
        $this->assertSame(ChartOfAccount::BALANCE_DEBIT, $loss->normal_balance);
        $this->assertSame('5000', $loss->parent->code);
    }

    /** @test */
    public function backfill_adds_missing_fx_accounts_without_touching_customised_ones(): void
    {
        $tenant = $this->makeTenant('fx-existing');
        app(ChartOfAccountsService::class)->provisionDefaults($tenant->id);

        // Simulate a tenant provisioned before these accounts existed.
        ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->whereIn('code', [AccountCode::FX_GAIN, AccountCode::FX_LOSS])->forceDelete();

        // A customised system account must survive the backfill untouched.
        $roundOff = $this->account($tenant->id, AccountCode::ROUND_OFF);
        $roundOff->update(['name' => 'Rounding Differences']);

        $this->runBackfill();
        $this->runBackfill(); // idempotent

        $this->assertSame(1, ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('code', AccountCode::FX_GAIN)->count());
        $this->assertSame('4000', $this->account($tenant->id, AccountCode::FX_GAIN)->parent->code);
        $this->assertSame('5000', $this->account($tenant->id, AccountCode::FX_LOSS)->parent->code);
        $this->assertSame('Rounding Differences', $this->account($tenant->id, AccountCode::ROUND_OFF)->name);
    }

    /** @test */
    public function backfill_skips_tenants_without_a_chart_of_accounts(): void
    {
        $tenant = $this->makeTenant('fx-empty');

        $this->runBackfill();

        $this->assertNull($this->account($tenant->id, AccountCode::FX_GAIN));
    }
}
