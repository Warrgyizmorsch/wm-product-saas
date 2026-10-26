<?php

namespace Tests\Feature\Platform\Admin;

use App\Domains\Platform\Models\PlatformAuditLog;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformConsoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'starter']);
    }

    private function userWithRole(string $email, string $roleSlug, ?int $tenantId): User
    {
        $user = User::create(['tenant_id' => $tenantId, 'name' => $roleSlug, 'email' => $email, 'password' => bcrypt('secret-pass')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenantId]);

        return $user;
    }

    public function test_a_guest_is_sent_to_the_console_sign_in(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('Platform Console');
    }

    public function test_a_tenant_owner_is_kept_out_of_the_console(): void
    {
        $owner = $this->userWithRole('owner@acme.test', 'tenant_owner', $this->tenant->id);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($owner)->get(route('platform.tenants.index'))->assertForbidden();
    }

    public function test_a_tenant_owner_cannot_sign_in_to_the_console(): void
    {
        $this->userWithRole('owner@acme.test', 'tenant_owner', $this->tenant->id);

        $this->post(route('admin.login.store'), ['email' => 'owner@acme.test', 'password' => 'secret-pass'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_tenantless_super_admin_signs_in_and_sees_every_section(): void
    {
        $this->userWithRole('root@platform.test', 'super_admin', null);

        $this->post(route('admin.login.store'), ['email' => 'root@platform.test', 'password' => 'secret-pass'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tenants')
            ->assertSee('Plans')
            ->assertSee('Audit Log');
        $this->get(route('platform.tenants.index'))->assertOk()->assertSee('Acme');
    }

    public function test_a_super_admin_whose_account_belongs_to_a_tenant_gets_in(): void
    {
        // Today's real owners are tenant-bound super admins.
        $admin = $this->userWithRole('super@acme.test', 'super_admin', $this->tenant->id);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_platform_sales_staff_only_see_what_their_role_allows(): void
    {
        $sales = $this->userWithRole('sales@platform.test', 'platform_sales', null);

        $this->actingAs($sales)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Demo Requests')
            ->assertDontSee(route('platform.tenants.index'))
            ->assertDontSee('Audit Log');
        $this->actingAs($sales)->get(route('platform.demo-requests.index'))->assertOk();
        $this->actingAs($sales)->get(route('platform.tenants.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_every_console_change_is_audited_with_secrets_masked(): void
    {
        $admin = $this->userWithRole('root@platform.test', 'super_admin', null);
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'starter']);

        $this->actingAs($admin)
            ->patch(route('platform.tenants.status', $other), ['status' => Tenant::STATUS_SUSPENDED, 'password' => 'should-not-be-stored'])
            ->assertRedirect(route('platform.tenants.index'));

        $log = PlatformAuditLog::query()->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($other->id, $log->tenant_id);
        $this->assertSame('platform.tenants.status', $log->action);
        $this->assertSame('PATCH', $log->method);
        $this->assertSame(Tenant::STATUS_SUSPENDED, $log->payload['status']);
        $this->assertNotSame('should-not-be-stored', $log->payload['password']);

        $this->actingAs($admin)->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('platform.tenants.status');
    }

    public function test_reads_are_not_audited(): void
    {
        $admin = $this->userWithRole('root@platform.test', 'super_admin', null);

        $this->actingAs($admin)->get(route('platform.tenants.index'))->assertOk();

        $this->assertSame(0, PlatformAuditLog::query()->count());
    }

    public function test_platform_staff_roles_cannot_be_given_to_a_workspace_user(): void
    {
        $owner = $this->userWithRole('owner@acme.test', 'tenant_owner', $this->tenant->id);

        $slugs = app(AccessService::class)->assignableRoles($owner, $this->tenant->id)->pluck('slug');

        foreach (Role::PLATFORM_STAFF_SLUGS as $slug) {
            $this->assertNotContains($slug, $slugs);
        }
        $this->assertContains('tenant_owner', $slugs);
    }

    public function test_old_tenant_app_addresses_redirect_to_the_console(): void
    {
        $admin = $this->userWithRole('super@acme.test', 'super_admin', $this->tenant->id);

        $this->actingAs($admin)
            ->withHeader('X-Tenant', 'acme')
            ->get('/platform/tenants')
            ->assertRedirect('/admin/tenants');
    }
}
