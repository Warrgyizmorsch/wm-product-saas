<?php

namespace Tests\Feature\Notification;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Employee;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait NotificationFixtures
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $otherUserA;
    protected User $userB;

    protected function prepareNotificationFixtures(): void
    {
        $this->tenantA = $this->makeTenant('tenant-a');
        $this->tenantB = $this->makeTenant('tenant-b');

        $this->userA = $this->makeUser($this->tenantA, 'user.a@example.com', 'employee');
        $this->otherUserA = $this->makeUser($this->tenantA, 'other.a@example.com', 'employee');
        $this->userB = $this->makeUser($this->tenantB, 'user.b@example.com', 'employee');
    }

    protected function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => strtoupper($slug),
            'slug' => $slug,
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
    }

    protected function makeUser(?Tenant $tenant, string $email, string $role = 'employee'): User
    {
        return User::create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    protected array $orgIds = [];

    /** Real company/department/designation rows so employee foreign keys hold. */
    protected function orgIds(): array
    {
        if ($this->orgIds) {
            return $this->orgIds;
        }

        $now = now();
        $company = DB::table('companies')->insertGetId(['company_name' => 'Acme', 'created_at' => $now, 'updated_at' => $now]);
        $department = DB::table('departments')->insertGetId(['company_id' => $company, 'name' => 'Ops', 'code' => 'OPS', 'created_at' => $now, 'updated_at' => $now]);
        $designation = DB::table('designations')->insertGetId(['department_id' => $department, 'name' => 'Staff', 'created_at' => $now, 'updated_at' => $now]);

        return $this->orgIds = compact('company', 'department', 'designation');
    }

    protected function makeEmployee(User $user, array $overrides = []): Employee
    {
        $org = $this->orgIds();

        return Employee::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'company_id' => $org['company'],
            'business_unit_id' => null,
            'branch_id' => null,
            'department_id' => $org['department'],
            'designation_id' => $org['designation'],
            'employee_id' => 'EMP-'.$user->id.'-'.uniqid(),
            'full_name' => $user->name,
            'date_of_joining' => now()->toDateString(),
            'gender' => 'Other',
            'office_email' => $user->email,
            'status' => true,
        ], $overrides));
    }

    /** Insert a row directly so tests never depend on the service under test. */
    protected function makeNotification(User $user, array $overrides = []): Notification
    {
        return Notification::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'module' => 'hrms',
            'type' => 'general',
            'title' => 'Title',
            'message' => 'Message',
            'icon_class' => 'feather-bell',
        ], $overrides));
    }

    protected function actAsTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);
    }

    protected function clearTenant(): void
    {
        app(TenantContext::class)->clear();
    }

    protected function asUser(User $user, Tenant $tenant)
    {
        return $this->actingAs($user)
            ->withHeaders(['X-Tenant' => $tenant->slug])
            ->withSession(['tenant_slug' => $tenant->slug]);
    }
}
