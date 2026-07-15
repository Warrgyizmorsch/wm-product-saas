<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AssetImportExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $hrManager;
    private User $salesExecutive;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->hrManager = $this->createUserWithRole('hr@example.com', 'hr_manager');
        $this->salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Acme Corporation',
            'legal_name' => 'Acme Corp Ltd',
            'email' => 'acme@example.com',
            'status' => true,
        ]);
    }

    private function createUserWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    /** @test */
    public function export_requires_hr_permissions(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.assets.export'));

        $response->assertForbidden();
    }

    /** @test */
    public function download_template_returns_xlsx_file(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.assets.import.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function download_categories_template_returns_xlsx_file(): void
    {
        $response = $this->actingAs($this->hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.assets.categories.import.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function export_assets_returns_xlsx_file(): void
    {
        $category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Laptops',
            'description' => 'Test Laptops',
        ]);

        Asset::create([
            'company_id' => $this->company->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'AST-999',
            'name' => 'Test Dell Laptop',
            'condition' => 'good',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.assets.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** @test */
    public function export_categories_returns_xlsx_file(): void
    {
        AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Laptops',
            'description' => 'Test Laptops',
        ]);

        $response = $this->actingAs($this->hrManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('hrms.assets.categories.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
