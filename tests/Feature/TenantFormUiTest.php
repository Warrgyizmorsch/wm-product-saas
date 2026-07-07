<?php

namespace Tests\Feature;

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFormUiTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => 'Home Tenant', 'slug' => 'home-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $permission = Permission::create([
            'name' => 'platform.tenants.manage', 'module' => 'platform', 'entity' => 'tenants', 'action' => 'manage',
        ]);

        $role = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin', 'level' => 1]);

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => RolePermission::SCOPE_PLATFORM,
        ]);

        $this->superAdmin = User::create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'name' => 'Super Admin',
            'email' => 'super@platform.test',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function the_create_tenant_page_renders_with_the_new_form_sections()
    {
        $response = $this->actingAs($this->superAdmin)
            ->withHeader('X-Tenant', 'home-tenant')
            ->get(route('platform.tenants.create'));

        $response->assertOk();
        $response->assertSee('Company Details');
        $response->assertSee('Plan &amp; Billing', false);
        $response->assertSee('Localization &amp; Currency', false);
        $response->assertSee('Branding');
        $response->assertSee('Advanced options', false);
        $response->assertSee('Tenant Owner');
        $response->assertSee('slug_preview_new', false);
        $response->assertSee('logo_preview_full_new', false);
        $response->assertSee('advancedOptions-new', false);
        $response->assertSee('owner_password_new', false);
        $response->assertSee('password-toggle-btn', false);
        $response->assertSee('Minimum 8 characters.');
    }

    /** @test */
    public function the_tenants_index_page_renders_edit_modals_with_unique_suffixed_ids()
    {
        $tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->withHeader('X-Tenant', 'home-tenant')
            ->get(route('platform.tenants.index'));

        $response->assertOk();
        $response->assertSee('slug_preview_'.$tenantB->id, false);
        $response->assertDontSee('owner_password_'.$tenantB->id, false);
    }
}
