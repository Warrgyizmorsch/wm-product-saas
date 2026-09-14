<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Services\CurrencyService;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CurrencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->makeTenant('fx-rates');
    }

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug,
            'status' => 'active', 'plan' => 'enterprise',
        ]);

        app(TenantContext::class)->set($tenant);

        return $tenant;
    }

    private function service(): CurrencyService
    {
        return app(CurrencyService::class);
    }

    private function addRate(string $from, string $to, float $rate, string $date, ?int $tenantId = null): void
    {
        ExchangeRate::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'effective_date' => $date,
        ]);
    }

    /** @test */
    public function iso_currencies_are_seeded_with_their_minor_units(): void
    {
        $this->assertSame(2, $this->service()->decimalsFor('inr'));
        $this->assertSame(0, $this->service()->decimalsFor('JPY'));
        $this->assertSame(3, $this->service()->decimalsFor('KWD'));

        $this->expectException(InvalidArgumentException::class);
        $this->service()->decimalsFor('XXX');
    }

    /** @test */
    public function base_currency_comes_from_the_tenant(): void
    {
        $gbpTenant = Tenant::create(['name' => 'UK', 'slug' => 'uk', 'status' => 'active', 'plan' => 'enterprise', 'currency' => 'gbp']);
        // A company's own currency column is ignored — one currency per tenant.
        Company::create(['company_name' => 'Acme US', 'currency' => 'USD']);

        $this->assertSame('GBP', $this->service()->baseCurrencyForTenant($gbpTenant->id));
        $this->assertSame('INR', $this->service()->baseCurrencyForTenant($this->tenant->id), 'column default');
        $this->assertSame('INR', $this->service()->baseCurrencyForTenant(999999), 'unknown tenant falls back');
    }

    /** @test */
    public function the_latest_rate_on_or_before_the_date_is_used(): void
    {
        $this->addRate('USD', 'INR', 83.00, '2026-09-01');
        $this->addRate('USD', 'INR', 84.00, '2026-09-10');
        $this->addRate('USD', 'INR', 99.00, '2026-09-20'); // future, must be ignored

        $this->assertSame(1.0, $this->service()->rate('INR', 'INR', '2026-09-12'));
        $this->assertSame(83.00, $this->service()->rate('USD', 'INR', '2026-09-09'));
        $this->assertSame(84.00, $this->service()->rate('USD', 'INR', '2026-09-10'));
        $this->assertSame(84.00, $this->service()->rate('usd', 'inr', '2026-09-15'));
    }

    /** @test */
    public function the_inverse_pair_is_used_when_no_direct_rate_exists(): void
    {
        $this->addRate('USD', 'INR', 80.00, '2026-09-01');

        $this->assertEqualsWithDelta(0.0125, $this->service()->rate('INR', 'USD', '2026-09-05'), 1e-12);
        $this->assertSame(125.0, $this->service()->convert(10000, 'INR', 'USD', '2026-09-05'));
    }

    /** @test */
    public function conversion_rounds_to_the_target_currencys_minor_unit(): void
    {
        $this->addRate('USD', 'JPY', 147.357, '2026-09-01');
        $this->addRate('USD', 'KWD', 0.30567, '2026-09-01');

        $this->assertSame(1474.0, $this->service()->convert(10.00, 'USD', 'JPY', '2026-09-02'));
        $this->assertSame(3.057, $this->service()->convert(10.00, 'USD', 'KWD', '2026-09-02'));
    }

    /** @test */
    public function a_missing_rate_throws_instead_of_guessing(): void
    {
        $this->addRate('USD', 'INR', 83.00, '2026-09-10');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No exchange rate from USD to INR effective on or before 2026-09-01');

        $this->service()->rate('USD', 'INR', '2026-09-01');
    }

    /** @test */
    public function another_tenants_rates_are_never_used(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);
        $this->addRate('EUR', 'INR', 90.00, '2026-09-01', $other->id);

        $this->assertSame(0, ExchangeRate::count(), 'global tenant scope should hide the other tenant\'s rate');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->rate('EUR', 'INR', '2026-09-05', $this->tenant->id);
    }
}
