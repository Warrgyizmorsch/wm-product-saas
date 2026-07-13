<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Lead;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $salesExecutive;
    private User $otherSalesExecutive;
    private User $salesManager;

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

        $this->salesExecutive = $this->createUserWithRole('exec@example.com', 'sales_executive');
        $this->otherSalesExecutive = $this->createUserWithRole('exec2@example.com', 'sales_executive');
        $this->salesManager = $this->createUserWithRole('manager@example.com', 'sales_manager');
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
    public function guest_is_redirected_to_login_instead_of_reaching_crm(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('crm.leads.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function sales_executive_can_create_and_view_their_own_lead(): void
    {
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('crm.leads.store'), [
                'lead_owner_id' => $this->salesExecutive->id,
                'company_name' => 'Own Lead Co',
                'contact_person' => 'Jane Doe',
                'email' => 'jane@ownleadco.test',
                'phone' => '5550000000',
                'requirement' => 'Sample requirement',
                'expected_amount' => '1000',
                'expected_sale_date' => now()->addDays(30)->toDateString(),
                'source' => 'Website',
                'priority' => 'High',
                'segment' => 'Enterprise',
                'call_date' => now()->toDateTimeString(),
            ]);

        $response->assertRedirect(route('crm.leads.index'));

        $lead = Lead::where('company_name', 'Own Lead Co')->firstOrFail();

        $show = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('crm.leads.show', $lead));

        $show->assertOk();
    }

    /** @test */
    public function sales_executive_cannot_view_a_lead_owned_by_someone_else(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'lead_owner_id' => $this->otherSalesExecutive->id,
            'company_name' => 'Other Lead Co',
            'call_date' => now(),
        ]);

        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('crm.leads.show', $lead));

        $response->assertForbidden();
    }

    /** @test */
    public function sales_executive_cannot_delete_their_own_lead(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'lead_owner_id' => $this->salesExecutive->id,
            'company_name' => 'Own Lead Co',
            'call_date' => now(),
        ]);

        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('crm.leads.destroy', $lead));

        $response->assertForbidden();
        $this->assertNull($lead->fresh()->deleted_at);
    }

    /** @test */
    public function sales_manager_can_delete_any_lead_in_the_tenant(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'lead_owner_id' => $this->salesExecutive->id,
            'company_name' => 'Managed Lead Co',
            'call_date' => now(),
        ]);

        $response = $this->actingAs($this->salesManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('crm.leads.destroy', $lead));

        $response->assertRedirect(route('crm.leads.index'));
        $this->assertSoftDeleted($lead);
    }

    /** @test */
    public function authenticated_user_with_lead_view_permission_can_download_sample(): void
    {
        $response = $this->actingAs($this->salesManager)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('crm.leads.downloadSample'));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=lead_sample.xlsx');
    }
}
