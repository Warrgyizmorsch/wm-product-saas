<?php

namespace Tests\Feature\Platform;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSuspendTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $current;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->current = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'starter']);
        $this->withHeader('X-Tenant', 'acme');

        $this->admin = User::create(['tenant_id' => null, 'name' => 'Root', 'email' => 'root@platform.test', 'password' => bcrypt('password')]);
        UserRole::create(['user_id' => $this->admin->id, 'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail()->id, 'tenant_id' => null]);
    }

    public function test_the_workspace_you_are_in_cannot_be_suspended(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('platform.tenants.status', $this->current), ['status' => Tenant::STATUS_SUSPENDED])
            ->assertRedirect(route('platform.tenants.index'))
            ->assertSessionHas('error');

        $this->assertSame(Tenant::STATUS_ACTIVE, $this->current->fresh()->status);
    }

    public function test_another_tenant_can_still_be_suspended_and_reactivated(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'starter']);

        $this->actingAs($this->admin)->patch(route('platform.tenants.status', $other), ['status' => Tenant::STATUS_SUSPENDED]);
        $this->assertSame(Tenant::STATUS_SUSPENDED, $other->fresh()->status);

        $this->actingAs($this->admin)->patch(route('platform.tenants.status', $other), ['status' => Tenant::STATUS_ACTIVE]);
        $this->assertSame(Tenant::STATUS_ACTIVE, $other->fresh()->status);
    }

    public function test_the_suspend_button_asks_for_confirmation(): void
    {
        $this->actingAs($this->admin)->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertSee('data-confirm-suspend="Acme"', false);
    }
}
