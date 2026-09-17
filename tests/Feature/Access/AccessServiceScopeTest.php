<?php

namespace Tests\Feature\Access;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserPermissionOverride;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers AccessService's scope engine directly, without going through a policy
 * or an HTTP route, so each scope's matching rule is pinned on its own.
 *
 * The rule under test throughout: a grant held below tenant scope compares the
 * record's value against the user's own, and fails closed when either side is
 * missing.
 */
class AccessServiceScopeTest extends TestCase
{
    use RefreshDatabase;

    private AccessService $access;
    private Tenant $tenant;
    private Permission $permission;

    /** Two of each, so "mine" and "not mine" are both real rows. */
    private Company $companyA;
    private Company $companyB;
    private Branch $branchA;
    private Branch $branchB;
    private Department $departmentA;
    private Department $departmentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(AccessService::class);

        $this->tenant = Tenant::create([
            'name' => 'Scope Tenant', 'slug' => 'scope-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->permission = Permission::create([
            'name' => 'crm.leads.view',
            'module' => 'crm',
            'entity' => 'leads',
            'action' => 'view',
        ]);

        // users.company_id / branch_id / department_id are real foreign keys, so
        // the scope tests need genuine org rows rather than arbitrary integers.
        app(TenantContext::class)->set($this->tenant);

        [$this->companyA, $this->branchA, $this->departmentA] = $this->makeOrgChain('A');
        [$this->companyB, $this->branchB, $this->departmentB] = $this->makeOrgChain('B');
    }

    /**
     * @return array{0: Company, 1: Branch, 2: Department}
     */
    private function makeOrgChain(string $suffix): array
    {
        $company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => "Company {$suffix}",
            'status' => true,
        ]);

        $businessUnit = BusinessUnit::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $company->id,
            'name' => "BU {$suffix}",
            'code' => "BU-{$suffix}",
            'status' => true,
        ]);

        $branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'business_unit_id' => $businessUnit->id,
            'name' => "Branch {$suffix}",
            'code' => "BR-{$suffix}",
            'status' => true,
        ]);

        $department = Department::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branch->id,
            'name' => "Department {$suffix}",
            'code' => "DPT-{$suffix}",
            'status' => true,
        ]);

        return [$company, $branch, $department];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => "User {$sequence}",
            'email' => "user{$sequence}@example.com",
            'password' => bcrypt('password'),
        ], $attributes));
    }

    private function grantAt(User $user, string $scope): Role
    {
        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => "Role {$scope}",
            'slug' => "role-{$scope}-{$user->id}",
            'level' => 50,
            'is_system' => false,
        ]);

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $this->permission->id,
            'scope' => $scope,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $role;
    }

    // ── branch scope ──────────────────────────────────────────────────

    /** @test */
    public function a_branch_scoped_grant_allows_a_record_in_the_users_own_branch(): void
    {
        $user = $this->makeUser(['branch_id' => $this->branchA->id]);
        $this->grantAt($user, RolePermission::SCOPE_BRANCH);

        $this->assertTrue($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchA->id,
        ]));
    }

    /** @test */
    public function a_branch_scoped_grant_denies_a_record_in_another_branch(): void
    {
        $user = $this->makeUser(['branch_id' => $this->branchA->id]);
        $this->grantAt($user, RolePermission::SCOPE_BRANCH);

        $this->assertFalse($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchB->id,
        ]));
    }

    /** @test */
    public function a_branch_scoped_grant_denies_when_the_caller_omits_the_branch(): void
    {
        $user = $this->makeUser(['branch_id' => $this->branchA->id]);
        $this->grantAt($user, RolePermission::SCOPE_BRANCH);

        $this->assertFalse($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
        ]));
    }

    /** @test */
    public function a_branch_scoped_grant_denies_a_user_who_has_no_branch(): void
    {
        $user = $this->makeUser(['branch_id' => null]);
        $this->grantAt($user, RolePermission::SCOPE_BRANCH);

        $this->assertFalse($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchA->id,
        ]));
    }

    // ── department scope ──────────────────────────────────────────────

    /** @test */
    public function a_department_scoped_grant_compares_the_users_department(): void
    {
        $user = $this->makeUser(['department_id' => $this->departmentA->id]);
        $this->grantAt($user, RolePermission::SCOPE_DEPARTMENT);
        $user = $user->fresh();

        $this->assertTrue($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->departmentA->id,
        ]));

        $this->assertFalse($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->departmentB->id,
        ]));
    }

    // ── company scope ─────────────────────────────────────────────────

    /** @test */
    public function a_company_scoped_grant_compares_the_users_company(): void
    {
        $user = $this->makeUser(['company_id' => $this->companyA->id]);
        $this->grantAt($user, RolePermission::SCOPE_COMPANY);
        $user = $user->fresh();

        $this->assertTrue($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyA->id,
        ]));

        $this->assertFalse($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->companyB->id,
        ]));
    }

    // ── own scope ─────────────────────────────────────────────────────

    /** @test */
    public function an_own_scoped_grant_allows_only_the_owners_own_records(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->grantAt($user, RolePermission::SCOPE_OWN);
        $user = $user->fresh();

        $this->assertTrue($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'owner_id' => $user->id,
        ]));

        $this->assertFalse($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'owner_id' => $other->id,
        ]));
    }

    // ── tenant scope is unchanged ─────────────────────────────────────

    /** @test */
    public function a_tenant_scoped_grant_still_covers_any_record_in_the_tenant(): void
    {
        $user = $this->makeUser(['branch_id' => $this->branchA->id]);
        $this->grantAt($user, RolePermission::SCOPE_TENANT);

        $this->assertTrue($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchB->id,
        ]));
    }

    // ── overrides honour their own scope ──────────────────────────────

    /** @test */
    public function an_allow_override_scoped_to_own_does_not_grant_another_users_record(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $this->permission->id,
            'tenant_id' => $this->tenant->id,
            'scope' => RolePermission::SCOPE_OWN,
            'allowed' => true,
        ]);

        $this->assertTrue($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'owner_id' => $user->id,
        ]));

        $this->assertFalse($this->access->allows($user, 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'owner_id' => $other->id,
        ]));
    }

    /** @test */
    public function a_deny_override_applies_regardless_of_scope(): void
    {
        $user = $this->makeUser(['branch_id' => $this->branchA->id]);
        $this->grantAt($user, RolePermission::SCOPE_TENANT);

        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $this->permission->id,
            'tenant_id' => $this->tenant->id,
            'scope' => RolePermission::SCOPE_OWN,
            'allowed' => false,
        ]);

        $this->assertFalse($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branchA->id,
        ]));
    }

    /** @test */
    public function an_allow_override_that_misses_its_scope_falls_through_to_role_grants(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $this->grantAt($user, RolePermission::SCOPE_TENANT);

        UserPermissionOverride::create([
            'user_id' => $user->id,
            'permission_id' => $this->permission->id,
            'tenant_id' => $this->tenant->id,
            'scope' => RolePermission::SCOPE_OWN,
            'allowed' => true,
        ]);

        // The override doesn't cover another user's record, but the tenant-scoped
        // role grant still does — a non-matching allow must not act as a denial.
        $this->assertTrue($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
            'owner_id' => $other->id,
        ]));
    }

    // ── legacy role_id is tenant-validated ────────────────────────────

    /** @test */
    public function a_legacy_role_id_pointing_at_another_tenants_role_is_ignored(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Foreign Tenant', 'slug' => 'foreign-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $foreignRole = Role::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Role',
            'slug' => 'foreign-role',
            'level' => 50,
            'is_system' => false,
        ]);

        RolePermission::create([
            'role_id' => $foreignRole->id,
            'permission_id' => $this->permission->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);

        $user = $this->makeUser(['role_id' => $foreignRole->id]);

        $this->assertFalse($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
        ]));
    }

    /** @test */
    public function a_legacy_role_id_pointing_at_a_system_role_still_works(): void
    {
        $systemRole = Role::create([
            'tenant_id' => null,
            'name' => 'System Role',
            'slug' => 'system-role',
            'level' => 50,
            'is_system' => true,
        ]);

        RolePermission::create([
            'role_id' => $systemRole->id,
            'permission_id' => $this->permission->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);

        $user = $this->makeUser(['role_id' => $systemRole->id]);

        $this->assertTrue($this->access->allows($user->fresh(), 'crm.leads.view', [
            'tenant_id' => $this->tenant->id,
        ]));
    }
}
