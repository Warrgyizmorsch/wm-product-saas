<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Support\VoucherType;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every accounting filter dropdown used to render a second, non-functional
 * "Apply Filters" button underneath its own working "Apply" (the
 * x-ui.filter component's own button sat outside the page's inner <form>).
 * Reset is now a plain link inside the same form as Apply, and the standalone
 * search box gets a clear (x) link once a search term is applied.
 */
class FilterResetButtonsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);

        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function visit(string $routeName, array $query = [])
    {
        return $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')->get(route($routeName, $query));
    }

    /** @return list<array{0: string}> */
    public static function screens(): array
    {
        return [
            ['accounting.journals.index'],
            ['accounting.bank-reconciliation.index'],
            ['accounting.chart-of-accounts.index'],
            ['accounting.fixed-assets.index'],
            ['accounting.posting-failures.index'],
            ['accounting.reports.audit-trail'],
        ];
    }

    /** @dataProvider screens */
    public function test_the_filter_dropdown_has_exactly_one_apply_and_one_reset(string $routeName): void
    {
        $html = $this->visit($routeName)->assertOk()->getContent();

        // Exactly one Apply button belonging to the filter dropdown's own form:
        // the page's own submit button, and none of the component's leftover markup.
        $this->assertSame(1, preg_match_all('/>\s*Apply\s*</', $html), "{$routeName}: expected exactly one Apply button");
        $this->assertSame(1, preg_match_all('/>\s*Reset\s*</', $html), "{$routeName}: expected exactly one Reset button");
    }

    public function test_journals_search_box_shows_a_clear_link_only_once_a_term_is_applied(): void
    {
        $this->visit('accounting.journals.index')->assertOk()->assertDontSee('Clear search');

        $this->visit('accounting.journals.index', ['search' => 'JNL-1'])
            ->assertOk()
            ->assertSee('title="Clear search"', false);
    }

    public function test_clearing_the_search_keeps_the_other_active_filters(): void
    {
        $response = $this->visit('accounting.journals.index', ['search' => 'JNL-1', 'status' => 'posted']);

        $response->assertSee(route('accounting.journals.index', ['status' => 'posted']), false);
    }

    public function test_vouchers_screen_also_has_one_apply_and_one_reset(): void
    {
        $html = $this->visit('accounting.vouchers.'.VoucherType::PAYMENT.'.index')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/>\s*Apply\s*</', $html));
        $this->assertSame(1, preg_match_all('/>\s*Reset\s*</', $html));
    }
}
