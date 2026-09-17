<?php

namespace Tests\Feature\Access;

use App\Domains\HRMS\Models\ExpenseCategory;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\PlatformAdminSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacGapsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);

        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@acme.test', 'password' => bcrypt('password')]);
    }

    private function signedIn(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'acme');
    }

    public function test_expense_categories_need_the_expense_policy_permission(): void
    {
        $this->signedIn($this->staff)->post(route('hrms.expense-categories.store'), ['name' => 'Travel', 'code' => 'TRV'])->assertForbidden();
        $this->assertDatabaseMissing('expense_categories', ['code' => 'TRV']);

        $this->signedIn($this->owner)->post(route('hrms.expense-categories.store'), ['name' => 'Travel', 'code' => 'TRV'])->assertRedirect();
        $category = ExpenseCategory::query()->withoutGlobalScopes()->where('code', 'TRV')->firstOrFail();

        $this->signedIn($this->staff)->put(route('hrms.expense-categories.update', $category), ['name' => 'Hacked', 'code' => 'TRV'])->assertForbidden();
        $this->signedIn($this->staff)->delete(route('hrms.expense-categories.destroy', $category))->assertForbidden();
        $this->assertSame('Travel', $category->fresh()->name);
    }

    public function test_probation_crm_activities_and_scanner_pages_need_permission(): void
    {
        foreach (['hrms.probation.index', 'crm.activities.index', 'production.mes.scanner.index'] as $route) {
            $this->signedIn($this->staff)->get(route($route))->assertForbidden();
        }

        $this->signedIn($this->owner)->get(route('production.mes.scanner.index'))->assertOk();
    }

    public function test_platform_admin_can_be_created_from_the_command(): void
    {
        $this->artisan('rbac:create-user', [
            '--platform' => true, '--name' => 'Root', '--email' => 'root@platform.test', '--password' => 'long-secret-pass',
        ])->assertSuccessful();

        $admin = User::query()->withoutGlobalScopes()->where('email', 'root@platform.test')->firstOrFail();

        $this->assertNull($admin->tenant_id);
        $this->assertTrue(app(AccessService::class)->allows($admin, 'platform.tenants.manage'));
    }

    public function test_platform_admin_seeder_needs_configured_credentials_and_keeps_an_existing_password(): void
    {
        config(['tenancy.platform_admin' => ['name' => null, 'email' => null, 'password' => null]]);
        $this->seed(PlatformAdminSeeder::class);
        $this->assertSame(0, User::query()->withoutGlobalScopes()->whereNull('tenant_id')->count());

        config(['tenancy.platform_admin' => ['name' => 'Root', 'email' => 'root@platform.test', 'password' => 'long-secret-pass']]);
        $this->seed(PlatformAdminSeeder::class);

        $admin = User::query()->withoutGlobalScopes()->where('email', 'root@platform.test')->firstOrFail();
        $this->assertNull($admin->tenant_id);
        $this->assertTrue(Hash::check('long-secret-pass', $admin->password));
        $this->assertTrue(app(AccessService::class)->allows($admin, 'platform.tenants.manage'));

        config(['tenancy.platform_admin.password' => 'a-different-pass']);
        $this->seed(PlatformAdminSeeder::class);
        $this->assertTrue(Hash::check('long-secret-pass', $admin->fresh()->password));
    }

    public function test_role_text_uses_the_matching_system_roles_grants_without_the_legacy_map(): void
    {
        config(['production.permissions' => []]);
        $access = app(AccessService::class);
        $context = ['tenant_id' => $this->tenant->id];

        $engineer = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Eng', 'email' => 'eng@acme.test', 'password' => bcrypt('password'), 'role' => 'production_engineer']);
        $this->assertTrue($access->allows($engineer, 'production.routing.create', $context));
        $this->assertFalse($access->allows($engineer, 'production.routing.approve', $context));

        // Role text never makes a tenant user a platform admin.
        $fakeSuper = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Fake', 'email' => 'fake@acme.test', 'password' => bcrypt('password'), 'role' => 'super_admin']);
        $this->assertFalse($access->allows($fakeSuper, 'platform.tenants.manage'));
    }
}
