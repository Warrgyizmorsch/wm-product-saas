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

class ProjectsLocaleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
    }

    /** @test */
    public function projects_index_renders_in_bulgarian(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'bg'])
            ->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('Проекти');
        $response->assertSee('Нов проект');
        $response->assertSee('Директория с проекти');
    }

    /** @test */
    public function projects_index_renders_in_hindi(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'hi'])
            ->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('प्रोजेक्ट्स');
        $response->assertSee('नया प्रोजेक्ट');
    }

    /** @test */
    public function project_quick_create_modal_renders_translated_name_label(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'bg'])
            ->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('Име на проекта');
    }

    /** @test */
    public function project_directory_row_renders_translated_priority_label(): void
    {
        Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->user->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'bg'])
            ->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('Приоритет');
        $response->assertSee('Висок');
    }

    /** @test */
    public function project_show_page_renders_translated_status_and_priority_labels(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->user->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->withSession(['locale' => 'hi'])
            ->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('उच्च'); // High (translated priority)
        $response->assertSee('सक्रिय'); // Active (translated status)

        // Stored DB value must remain the untranslated English constant
        $this->assertSame('Active', $project->fresh()->status);
        $this->assertSame('High', $project->fresh()->priority);
    }
}
