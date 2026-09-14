<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\ExchangeRateSyncSetting;
use App\Domains\Accounting\Services\ExchangeRates\ExchangeRateSyncService;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.frankfurter.url' => 'https://fx.test']);
    }

    private function tenantWithCompany(string $slug, string $baseCurrency): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active', 'plan' => 'enterprise', 'currency' => $baseCurrency]);

        app(TenantContext::class)->set($tenant);
        Company::create(['company_name' => ucfirst($slug) . ' Ltd', 'currency' => $baseCurrency]);

        return $tenant;
    }

    /** @param array<int, string> $currencies */
    private function configureSync(Tenant $tenant, array $currencies, bool $enabled = true): void
    {
        ExchangeRateSyncSetting::create([
            'tenant_id' => $tenant->id,
            'is_enabled' => $enabled,
            'currencies' => $currencies,
        ]);
    }

    /** @param array<string, mixed> $latest */
    private function fakeFeed(array $latest): void
    {
        Http::fake(array_merge([
            '*fx.test/currencies' => Http::response(['EUR' => 'Euro', 'GBP' => 'British Pound', 'INR' => 'Indian Rupee', 'USD' => 'US Dollar']),
        ], $latest));
    }

    private function rate(int $tenantId, string $from, string $to): ?ExchangeRate
    {
        return ExchangeRate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)->where('from_currency', $from)->where('to_currency', $to)->first();
    }

    private function sync(): ExchangeRateSyncService
    {
        return app(ExchangeRateSyncService::class);
    }

    /** @test */
    public function it_stores_foreign_to_base_rates_at_the_provider_date_and_flags_unsupported_codes(): void
    {
        $tenant = $this->tenantWithCompany('fx-sync', 'INR');
        $this->configureSync($tenant, ['GBP', 'USD', 'KWD']);

        $this->fakeFeed([
            '*fx.test/latest?from=INR*' => Http::response(['amount' => 1, 'base' => 'INR', 'date' => '2026-09-11', 'rates' => ['GBP' => 0.008, 'USD' => 0.0125]]),
        ]);

        $result = $this->sync()->syncTenant($tenant->id);

        $this->assertSame(ExchangeRateSyncSetting::STATUS_SUCCESS, $result['status']);
        $this->assertSame(2, $result['inserted']);

        $gbp = $this->rate($tenant->id, 'GBP', 'INR');
        $this->assertEqualsWithDelta(125.0, $gbp->rate, 1e-9);
        $this->assertSame('2026-09-11', $gbp->effective_date->toDateString());
        $this->assertSame(ExchangeRate::SOURCE_API, $gbp->source);
        $this->assertEqualsWithDelta(80.0, $this->rate($tenant->id, 'USD', 'INR')->rate, 1e-9);

        // KWD is not requested from the provider, only reported back.
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/latest')
            && urldecode(parse_url($request->url(), PHP_URL_QUERY)) === 'from=INR&to=GBP,USD');

        $setting = ExchangeRateSyncSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(['KWD'], $setting->unsupported);
        $this->assertNotNull($setting->last_synced_at);
    }

    /** @test */
    public function manual_rates_are_kept_and_previously_synced_rates_are_refreshed(): void
    {
        $tenant = $this->tenantWithCompany('fx-manual', 'INR');
        $this->configureSync($tenant, ['GBP', 'USD']);

        ExchangeRate::create(['tenant_id' => $tenant->id, 'from_currency' => 'GBP', 'to_currency' => 'INR', 'rate' => 110, 'effective_date' => '2026-09-11', 'source' => ExchangeRate::SOURCE_MANUAL]);
        ExchangeRate::create(['tenant_id' => $tenant->id, 'from_currency' => 'USD', 'to_currency' => 'INR', 'rate' => 70, 'effective_date' => '2026-09-11', 'source' => ExchangeRate::SOURCE_API]);

        $this->fakeFeed([
            '*fx.test/latest?from=INR*' => Http::response(['amount' => 1, 'base' => 'INR', 'date' => '2026-09-11', 'rates' => ['GBP' => 0.008, 'USD' => 0.0125]]),
        ]);

        $result = $this->sync()->syncTenant($tenant->id);

        $this->assertSame(1, $result['kept_manual']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['inserted']);

        $this->assertEqualsWithDelta(110.0, $this->rate($tenant->id, 'GBP', 'INR')->rate, 1e-9);
        $this->assertSame(ExchangeRate::SOURCE_MANUAL, $this->rate($tenant->id, 'GBP', 'INR')->source);
        $this->assertEqualsWithDelta(80.0, $this->rate($tenant->id, 'USD', 'INR')->rate, 1e-9);
    }

    /** @test */
    public function one_tenants_provider_failure_does_not_stop_others_and_disabled_tenants_are_skipped(): void
    {
        $failing = $this->tenantWithCompany('fx-failing', 'EUR');
        $this->configureSync($failing, ['GBP']);

        $healthy = $this->tenantWithCompany('fx-healthy', 'INR');
        $this->configureSync($healthy, ['GBP']);

        $disabled = $this->tenantWithCompany('fx-disabled', 'INR');
        $this->configureSync($disabled, ['GBP'], enabled: false);

        $this->fakeFeed([
            '*fx.test/latest?from=EUR*' => Http::response(['message' => 'boom'], 500),
            '*fx.test/latest?from=INR*' => Http::response(['amount' => 1, 'base' => 'INR', 'date' => '2026-09-11', 'rates' => ['GBP' => 0.008]]),
        ]);

        $results = $this->sync()->syncEnabledTenants();

        $this->assertSame([$failing->id, $healthy->id], array_keys($results));
        $this->assertSame(ExchangeRateSyncSetting::STATUS_FAILED, $results[$failing->id]['status']);
        $this->assertSame(ExchangeRateSyncSetting::STATUS_SUCCESS, $results[$healthy->id]['status']);

        $failedSetting = ExchangeRateSyncSetting::withoutGlobalScopes()->where('tenant_id', $failing->id)->first();
        $this->assertSame(ExchangeRateSyncSetting::STATUS_FAILED, $failedSetting->last_status);
        $this->assertStringContainsString('HTTP 500', $failedSetting->last_error);

        $this->assertNotNull($this->rate($healthy->id, 'GBP', 'INR'));
        $this->assertNull($this->rate($disabled->id, 'GBP', 'INR'));
    }

    /** @test */
    public function a_tenant_with_nothing_selected_is_skipped_and_the_command_runs(): void
    {
        $tenant = $this->tenantWithCompany('fx-empty', 'INR');
        $this->configureSync($tenant, []);
        $this->fakeFeed([]);

        $this->artisan('accounting:sync-exchange-rates', ['--tenant' => $tenant->id])->assertExitCode(0);

        $setting = ExchangeRateSyncSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertSame(ExchangeRateSyncSetting::STATUS_SKIPPED, $setting->last_status);
        Http::assertNothingSent();
    }
}
