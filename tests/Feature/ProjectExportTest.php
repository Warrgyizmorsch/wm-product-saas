<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Exports\ProjectsExport;
use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProjectExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $tenantOwner;
    private User $readOnlyUser;
    private User $otherTenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant', 'slug' => 'other-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = $this->createUserWithRole('owner@example.com', 'tenant_owner', $this->tenant);
        $this->readOnlyUser = $this->createUserWithRole('readonly@example.com', 'read_only', $this->tenant);
        $this->otherTenantOwner = $this->createUserWithRole('owner2@example.com', 'tenant_owner', $this->otherTenant);
    }

    private function createUserWithRole(string $email, string $roleSlug, Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);

        return $user;
    }

    private function createProject(Tenant $tenant, User $owner, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'tenant_id' => $tenant->id,
            'project_code' => 'PRJ-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'name' => 'Project ' . uniqid(),
            'owner_id' => $owner->id,
            'start_date' => now(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * Downloads a real (non-faked) .xlsx and reads it back with
     * PhpSpreadsheet so assertions exercise the actual query execution
     * inside the request/tenant-context lifecycle, exactly as production
     * does — Excel::fake() only records the export object without ever
     * running its query, which would make tenant-scoping assertions
     * meaningless (the tenant context is cleared by ResolveTenant's
     * `finally` block the moment the request ends).
     *
     * @return list<array<int, mixed>> including the header row at index 0
     */
    private function downloadAndReadRows(TestResponse $response): array
    {
        $path = $response->getFile()->getPathname();

        return IOFactory::load($path)->getActiveSheet()->toArray();
    }

    /** @test */
    public function authorized_user_downloads_an_xlsx_file(): void
    {
        $this->createProject($this->tenant, $this->tenantOwner, ['project_code' => 'PRJ-0001', 'name' => 'Alpha']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export'));

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $disposition = $response->headers->get('content-disposition');
        $this->assertMatchesRegularExpression(
            '/projects_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}\.xlsx/',
            $disposition,
        );
    }

    /** @test */
    public function guest_is_redirected_to_login(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')->get(route('projects.export'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function read_only_user_is_forbidden_from_exporting(): void
    {
        $response = $this->actingAs($this->readOnlyUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export'));

        $response->assertForbidden();
    }

    /** @test */
    public function export_never_includes_another_tenants_projects(): void
    {
        $this->createProject($this->tenant, $this->tenantOwner, ['name' => 'Mine']);
        $this->createProject($this->otherTenant, $this->otherTenantOwner, ['name' => 'Not Mine']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export'));

        $rows = $this->downloadAndReadRows($response);
        $names = array_column(array_slice($rows, 1), 1);

        $this->assertSame(['Mine'], $names);
    }

    /** @test */
    public function export_preserves_search_status_and_priority_filters(): void
    {
        $this->createProject($this->tenant, $this->tenantOwner, [
            'name' => 'Zulu Build', 'status' => Project::STATUS_ACTIVE, 'priority' => Project::PRIORITY_HIGH,
        ]);
        $this->createProject($this->tenant, $this->tenantOwner, [
            'name' => 'Alpha Build', 'status' => Project::STATUS_ACTIVE, 'priority' => Project::PRIORITY_LOW,
        ]);
        $this->createProject($this->tenant, $this->tenantOwner, [
            'name' => 'Beta Other', 'status' => Project::STATUS_DRAFT, 'priority' => Project::PRIORITY_HIGH,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export', [
                'search' => 'Build',
                'status' => Project::STATUS_ACTIVE,
                'priority' => Project::PRIORITY_HIGH,
            ]));

        $rows = $this->downloadAndReadRows($response);
        $names = array_column(array_slice($rows, 1), 1);

        $this->assertSame(['Zulu Build'], $names);
    }

    /** @test */
    public function export_preserves_sort_and_direction(): void
    {
        $this->createProject($this->tenant, $this->tenantOwner, ['project_code' => 'PRJ-0003', 'name' => 'Charlie']);
        $this->createProject($this->tenant, $this->tenantOwner, ['project_code' => 'PRJ-0001', 'name' => 'Alpha']);
        $this->createProject($this->tenant, $this->tenantOwner, ['project_code' => 'PRJ-0002', 'name' => 'Bravo']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export', ['sort' => 'name', 'direction' => 'desc']));

        $rows = $this->downloadAndReadRows($response);
        $names = array_column(array_slice($rows, 1), 1);

        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $names);
    }

    /** @test */
    public function export_orders_ties_deterministically_by_id(): void
    {
        $first = $this->createProject($this->tenant, $this->tenantOwner, [
            'project_code' => 'PRJ-0001', 'name' => 'Same Priority One', 'priority' => Project::PRIORITY_HIGH,
        ]);
        $second = $this->createProject($this->tenant, $this->tenantOwner, [
            'project_code' => 'PRJ-0002', 'name' => 'Same Priority Two', 'priority' => Project::PRIORITY_HIGH,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export', ['sort' => 'priority', 'direction' => 'asc']));

        $rows = $this->downloadAndReadRows($response);
        $codes = array_column(array_slice($rows, 1), 0);

        $this->assertSame([$first->project_code, $second->project_code], $codes);
    }

    /** @test */
    public function export_with_no_matching_projects_still_produces_zero_rows(): void
    {
        $this->createProject($this->tenant, $this->tenantOwner, ['name' => 'Alpha']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export', ['search' => 'no-such-project']));

        $rows = $this->downloadAndReadRows($response);

        // Header row only — the export is still a valid file, just with no data rows.
        $this->assertCount(1, $rows);
    }

    /** @test */
    public function export_maps_human_readable_labels_and_formatted_dates(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);

        $this->createProject($this->tenant, $this->tenantOwner, [
            'project_code' => 'PRJ-0009',
            'name' => 'Labelled Project',
            'customer_id' => $customer->id,
            'priority' => Project::PRIORITY_CRITICAL,
            'status' => Project::STATUS_ON_HOLD,
            'start_date' => '2026-01-15',
            'end_date' => '2026-03-20',
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.export'));

        $rows = $this->downloadAndReadRows($response);

        $this->assertSame([
            'Code', 'Name', 'Client', 'Owner', 'Priority', 'Status', 'Start Date', 'End Date',
        ], $rows[0]);

        $this->assertSame([
            'PRJ-0009',
            'Labelled Project',
            'Acme Corp',
            $this->tenantOwner->name,
            'Critical',
            'On Hold',
            '15 Jan 2026',
            '20 Mar 2026',
        ], $rows[1]);
    }

    /** @test */
    public function export_headings_match_the_mapped_column_order(): void
    {
        $export = new ProjectsExport(Project::query());

        $this->assertSame(
            ['Code', 'Name', 'Client', 'Owner', 'Priority', 'Status', 'Start Date', 'End Date'],
            $export->headings(),
        );
    }
}
