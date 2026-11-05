<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Mossie chat widget loads on every ERP page when configured, and not at all when it isn't. */
class MossieWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($tenant);
        $this->owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $tenant->id,
        ]);
        $this->withHeader('X-Tenant', 'acme');
    }

    public function test_widget_script_is_on_erp_pages_when_configured(): void
    {
        config(['services.mossie' => ['url' => 'https://chat.example.test/mossie-widget.js', 'key' => 'test-key', 'title' => 'Mossie Assistant', 'open' => false]]);

        $this->actingAs($this->owner)->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('src="https://chat.example.test/mossie-widget.js"', false)
            ->assertSee('data-key="test-key"', false)
            ->assertSee('data-open="false"', false);
    }

    public function test_widget_is_left_out_when_not_configured(): void
    {
        config(['services.mossie' => ['url' => null, 'key' => null, 'title' => 'Mossie Assistant', 'open' => false]]);

        $this->actingAs($this->owner)->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertDontSee('mossie-widget.js', false);
    }
}
