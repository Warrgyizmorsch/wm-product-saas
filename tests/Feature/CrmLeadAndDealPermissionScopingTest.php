<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Repositories\LeadRepository;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmLeadAndDealPermissionScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_executive_with_own_scope_can_access_leads_and_sees_only_own_leads(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        // 1. Create permissions and Role
        $viewPerm = Permission::firstOrCreate(
            ['name' => 'crm.leads.view'],
            ['module' => 'crm', 'entity' => 'leads', 'action' => 'view', 'is_system' => true]
        );

        $execRole = Role::create([
            'tenant_id' => $tenant->id,
            'slug' => 'sales_executive',
            'name' => 'Sales Executive',
            'level' => 50,
            'is_system' => true,
        ]);

        // Grant SCOPE_OWN
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $viewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);

        $execUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $execRole->id,
            'role'      => 'sales_executive',
            'name'      => 'Devendra Verma',
            'email'     => 'devendra@example.com',
        ]);
        $execUser->roles()->attach($execRole->id);

        // 2. Create another user (Lead Owner)
        $otherUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name'      => 'Demo Admin',
            'email'     => 'admin@example.com',
        ]);

        // 3. Create 2 Leads: Lead A owned by Other, Lead B owned by Exec
        $leadA = Lead::create([
            'tenant_id'     => $tenant->id,
            'company_name'  => 'Client A',
            'lead_owner_id' => $otherUser->id,
            'status'        => 'New',
        ]);

        $leadB = Lead::create([
            'tenant_id'     => $tenant->id,
            'company_name'  => 'Client B',
            'lead_owner_id' => $execUser->id,
            'status'        => 'Qualified',
        ]);

        // 4. Test Web Route /crm/leads as Executive
        $this->actingAs($execUser);
        $response = $this->withSession(['tenant_slug' => $tenant->slug])->get('/crm/leads');
        $response->assertStatus(200);

        // 5. Test Repository Scoping
        $leadRepo = app(LeadRepository::class);
        $paginated = $leadRepo->getPaginatedLeads([], 20);
        $visibleLeadIds = collect($paginated->items())->pluck('id')->all();

        $this->assertContains($leadB->id, $visibleLeadIds, 'Executive should see their own lead');
        $this->assertNotContains($leadA->id, $visibleLeadIds, 'Executive should NOT see other user lead');

        // 6. Test Single Lead Detail Route
        // Should be able to view their own lead
        $showOwnResponse = $this->withSession(['tenant_slug' => $tenant->slug])->get("/crm/leads/{$leadB->id}");
        $showOwnResponse->assertStatus(200);

        // Should be forbidden (403) from viewing other user's lead
        $showOtherResponse = $this->withSession(['tenant_slug' => $tenant->slug])->get("/crm/leads/{$leadA->id}");
        $showOtherResponse->assertStatus(403);
    }

    public function test_account_manager_can_view_deals_and_leads_for_their_managed_account(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant 2',
            'slug' => 'test-tenant-2',
        ]);

        $leadsViewPerm = Permission::firstOrCreate(
            ['name' => 'crm.leads.view'],
            ['module' => 'crm', 'entity' => 'leads', 'action' => 'view', 'is_system' => true]
        );
        $dealsViewPerm = Permission::firstOrCreate(
            ['name' => 'crm.deals.view'],
            ['module' => 'crm', 'entity' => 'deals', 'action' => 'view', 'is_system' => true]
        );

        $execRole = Role::create([
            'tenant_id' => $tenant->id,
            'slug' => 'sales_executive',
            'name' => 'Sales Executive',
            'level' => 50,
            'is_system' => true,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $leadsViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);
        RolePermission::create([
            'role_id' => $execRole->id,
            'permission_id' => $dealsViewPerm->id,
            'scope' => RolePermission::SCOPE_OWN,
        ]);

        $execUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role_id'   => $execRole->id,
            'role'      => 'sales_executive',
            'name'      => 'Devendra Verma',
            'email'     => 'devendra2@example.com',
        ]);
        $execUser->roles()->attach($execRole->id);

        $otherUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name'      => 'Other User',
            'email'     => 'other@example.com',
        ]);

        // Account managed by Devendra
        $myAccount = \App\Domains\CRM\Models\CrmAccount::create([
            'tenant_id' => $tenant->id,
            'name'      => 'WarrgyizMorsch Corp',
            'owner_id'  => $execUser->id,
            'status'    => 'active',
        ]);

        // Account managed by Other User
        $otherAccount = \App\Domains\CRM\Models\CrmAccount::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Other Corp',
            'owner_id'  => $otherUser->id,
            'status'    => 'active',
        ]);

        // Deal on myAccount created/owned by otherUser
        $dealOnMyAccount = \App\Domains\CRM\Models\CrmDeal::create([
            'tenant_id'      => $tenant->id,
            'crm_account_id' => $myAccount->id,
            'owner_id'       => $otherUser->id,
            'title'          => 'Deal for My Account',
            'deal_number'    => 'DL-TEST-001',
            'stage'          => 'Qualified',
            'estimated_value'=> 50000,
        ]);

        // Deal on otherAccount
        $dealOnOtherAccount = \App\Domains\CRM\Models\CrmDeal::create([
            'tenant_id'      => $tenant->id,
            'crm_account_id' => $otherAccount->id,
            'owner_id'       => $otherUser->id,
            'title'          => 'Deal for Other Account',
            'deal_number'    => 'DL-TEST-002',
            'stage'          => 'Qualified',
            'estimated_value'=> 20000,
        ]);

        // Lead linked to myAccount owned by otherUser
        $leadOnMyAccount = Lead::create([
            'tenant_id'      => $tenant->id,
            'crm_account_id' => $myAccount->id,
            'lead_owner_id'  => $otherUser->id,
            'company_name'   => 'WarrgyizMorsch Corp',
            'status'         => 'New',
        ]);

        // Lead on otherAccount
        $leadOnOtherAccount = Lead::create([
            'tenant_id'      => $tenant->id,
            'crm_account_id' => $otherAccount->id,
            'lead_owner_id'  => $otherUser->id,
            'company_name'   => 'Other Corp',
            'status'         => 'New',
        ]);

        $this->actingAs($execUser);

        // Test Deals Index
        $dealsResponse = $this->withSession(['tenant_slug' => $tenant->slug])->get('/crm/deals');
        $dealsResponse->assertStatus(200);

        // Test Deals API
        $dealApiResponse = $this->withSession(['tenant_slug' => $tenant->slug])->getJson('/api/crm/deals');
        $dealApiResponse->assertStatus(200);
        $apiDealIds = collect($dealApiResponse->json('data'))->pluck('id')->all();
        $this->assertContains($dealOnMyAccount->id, $apiDealIds, 'Account Manager sees deal of managed account');
        $this->assertNotContains($dealOnOtherAccount->id, $apiDealIds, 'Account Manager does NOT see deal of other account');

        // Test Single Deal Policy
        $this->get("/crm/deals/{$dealOnMyAccount->id}")->assertStatus(200);
        $this->get("/crm/deals/{$dealOnOtherAccount->id}")->assertStatus(403);

        // Test Leads List via Repo
        $leadRepo = app(LeadRepository::class);
        $paginatedLeads = $leadRepo->getPaginatedLeads([], 20);
        $visibleLeadIds = collect($paginatedLeads->items())->pluck('id')->all();
        $this->assertContains($leadOnMyAccount->id, $visibleLeadIds, 'Account Manager sees lead of managed account');
        $this->assertNotContains($leadOnOtherAccount->id, $visibleLeadIds, 'Account Manager does NOT see lead of other account');

        // Test Single Lead Policy
        $this->get("/crm/leads/{$leadOnMyAccount->id}")->assertStatus(200);
        $this->get("/crm/leads/{$leadOnOtherAccount->id}")->assertStatus(403);
    }

    public function test_direct_deal_does_not_inherit_activities_from_other_account_leads(): void
    {
        $tenant = Tenant::create([
            'name' => 'Activity Test Tenant',
            'slug' => 'activity-tenant',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name'      => 'Admin User',
            'email'     => 'admin@activity.com',
            'role'      => 'super_admin',
        ]);

        $account = \App\Domains\CRM\Models\CrmAccount::create([
            'tenant_id' => $tenant->id,
            'name'      => 'WarrgyizMorsch Corporate',
            'owner_id'  => $admin->id,
        ]);

        // A lead for this account that has an activity
        $lead = Lead::create([
            'tenant_id'      => $tenant->id,
            'crm_account_id' => $account->id,
            'company_name'   => 'WarrgyizMorsch Corporate',
            'status'         => 'Dealing',
        ]);

        \App\Domains\CRM\Models\LeadFollowup::create([
            'tenant_id'     => $tenant->id,
            'lead_id'       => $lead->id,
            'followup_date' => now()->subDay(),
            'type'          => 'Meeting',
            'title'         => 'Old Lead Meeting',
            'status'        => 'Done',
        ]);

        // A direct deal created on the same account (NOT converted from $lead)
        $directDeal = \App\Domains\CRM\Models\CrmDeal::create([
            'tenant_id'       => $tenant->id,
            'crm_account_id'  => $account->id,
            'title'           => 'Direct Fresh Deal',
            'owner_id'        => $admin->id,
            'stage'           => 'Qualification',
            'estimated_value' => 50000,
        ]);

        $this->actingAs($admin);

        $response = $this->withSession(['tenant_slug' => $tenant->slug])
            ->get("/crm/deals/{$directDeal->id}");

        $response->assertStatus(200);
        $response->assertViewHas('linkedLead', null);

        $followups = $response->viewData('followups');
        $this->assertCount(0, $followups, 'Direct deal must NOT inherit followups from unrelated account leads');
    }
}
