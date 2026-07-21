<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the rendered <option> set of the status inline-edit dropdown on the
 * project show page, not just the backend transition rules — the dropdown is
 * built from ProjectService::availableStatusTransitions() and must never offer
 * a status the backend would reject. Assertions are scoped to the status
 * field's own <select> block (located via its data-field="status" marker)
 * rather than page-wide assertSee(), since Task::STATUSES shares several
 * literal values with Project statuses (On Hold, Completed, Cancelled) and a
 * page-wide search would false-positive against the task filter dropdown.
 */
class ProjectStatusDropdownTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->tenantOwner->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
    }

    private function createProject(array $overrides = []): Project
    {
        return Project::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'Original Name',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => '2026-01-01',
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ], $overrides));
    }

    /**
     * @return list<string> the option values rendered inside the status
     *  field's own <select>...</select> block, in document order.
     */
    private function statusDropdownOptions(string $html): array
    {
        $marker = strpos($html, 'data-field="status"');
        $this->assertNotFalse($marker, 'status inline-edit control was not found in the response.');

        $selectStart = strpos($html, '<select', $marker);
        $this->assertNotFalse($selectStart, 'status field is not rendered as a <select>.');

        $selectEnd = strpos($html, '</select>', $selectStart);
        $this->assertNotFalse($selectEnd, 'status <select> was not closed.');

        $selectHtml = substr($html, $selectStart, $selectEnd - $selectStart);

        preg_match_all('/<option value="([^"]*)"/', $selectHtml, $matches);

        return $matches[1];
    }

    /** @test */
    public function active_project_dropdown_offers_only_active_transitions(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_ACTIVE]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.show', $project));

        $response->assertOk();

        $options = $this->statusDropdownOptions($response->getContent());

        $this->assertEqualsCanonicalizing(
            [Project::STATUS_ACTIVE, Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED, Project::STATUS_CANCELLED],
            $options,
        );
        $this->assertNotContains(Project::STATUS_DRAFT, $options);
        $this->assertNotContains(Project::STATUS_CLOSED, $options);
    }

    /** @test */
    public function completed_project_dropdown_offers_only_completed_and_closed(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_COMPLETED]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.show', $project));

        $response->assertOk();

        $options = $this->statusDropdownOptions($response->getContent());

        $this->assertEqualsCanonicalizing(
            [Project::STATUS_COMPLETED, Project::STATUS_CLOSED],
            $options,
        );
    }

    /** @test */
    public function closed_project_dropdown_offers_only_closed(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_CLOSED]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.show', $project));

        $response->assertOk();

        $options = $this->statusDropdownOptions($response->getContent());

        $this->assertSame([Project::STATUS_CLOSED], $options);
    }

    /** @test */
    public function cancelled_project_dropdown_offers_only_cancelled(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_CANCELLED]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.show', $project));

        $response->assertOk();

        $options = $this->statusDropdownOptions($response->getContent());

        $this->assertSame([Project::STATUS_CANCELLED], $options);
    }
}
