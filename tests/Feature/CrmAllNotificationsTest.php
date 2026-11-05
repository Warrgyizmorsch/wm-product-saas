<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Repositories\LeadRepository;
use App\Domains\CRM\Services\CrmNotificationService;
use App\Domains\CRM\Services\LeadService;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAllNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $manager;
    protected User $salesExecutive;
    protected User $executiveTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Full CRM Tenant',
            'slug' => 'crm-full-test',
        ]);

        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Manager',
            'email' => 'manager@crmtest.com',
            'role' => 'Sales Manager',
        ]);

        $this->salesExecutive = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive One',
            'email' => 'exec1@crmtest.com',
            'role' => 'Sales Executive',
        ]);

        $this->executiveTwo = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Executive Two',
            'email' => 'exec2@crmtest.com',
            'role' => 'Sales Executive',
        ]);
    }

    public function test_all_18_crm_events_registered_in_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();
        $this->assertArrayHasKey('crm', $events);
        $crmEvents = $events['crm']['events'];

        $expectedKeys = [
            'crm.lead.created',
            'crm.lead.assigned',
            'crm.lead.followup_upcoming',
            'crm.lead.followup_due',
            'crm.lead.status_updated',
            'crm.lead.lost',
            'crm.lead.converted',
            'crm.deal.created',
            'crm.deal.assigned',
            'crm.deal.stage_updated',
            'crm.deal.won',
            'crm.deal.lost',
            'crm.deal.followup_upcoming',
            'crm.deal.followup_due',
            'crm.quotation.created',
            'crm.quotation.approved',
            'crm.quotation.rejected',
            'crm.quotation.accepted',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $crmEvents, "Catalog missing event: {$key}");
        }
    }

    public function test_lead_created_and_status_updated_triggers_notifications(): void
    {
        $this->actingAs($this->manager);

        // 1. Create Lead
        $lead = app(LeadService::class)->storeLead([
            'company_name' => 'Solaris Global',
            'contact_person' => 'Vikram Seth',
            'email' => 'vikram@solaris.com',
            'lead_owner_id' => $this->salesExecutive->id,
            'source' => 'Website',
        ], [], []);

        // Assert Notification created for lead assignment
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module'  => 'crm',
            'type'    => 'lead_assigned',
        ]);

        // 2. Update Lead Status
        app(LeadService::class)->updateLeadStatus($lead, 'In Progress');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module'  => 'crm',
            'type'    => 'lead_status_updated',
        ]);

        // 3. Mark Lead as Lost
        app(LeadService::class)->updateLeadStatus($lead, 'Lost');
        $this->assertEquals('Lost', $lead->fresh()->status);
    }

    public function test_lead_qualify_and_conversion_to_deal_triggers_notifications(): void
    {
        $this->actingAs($this->manager);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Titan Industries',
            'contact_person' => 'Amitabh Roy',
            'lead_owner_id' => $this->salesExecutive->id,
            'status' => 'New',
        ]);

        $repo = app(LeadRepository::class);
        $repo->qualifyLead($lead);

        $lead->refresh();
        $this->assertEquals('Dealing', $lead->status);
        $this->assertNotNull($lead->crm_deal_id);
    }

    public function test_deal_assigned_and_stage_won_lost_triggers_notifications(): void
    {
        $this->actingAs($this->manager);

        $account = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Orbit Corp',
        ]);

        $deal = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $account->id,
            'title' => 'Orbit Cloud Expansion',
            'owner_id' => $this->salesExecutive->id,
            'stage' => 'Qualification',
            'estimated_value' => 500000.00,
        ]);

        // 1. Notify Deal Assigned
        CrmNotificationService::notifyDealAssigned($deal, $this->executiveTwo->id, $this->manager->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->executiveTwo->id,
            'module'  => 'crm',
            'type'    => 'deal_assigned',
        ]);

        // 2. Notify Deal Won
        CrmNotificationService::notifyDealWon($deal);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module'  => 'crm',
            'type'    => 'deal_won',
        ]);

        // 3. Notify Deal Lost
        CrmNotificationService::notifyDealLost($deal, 'Competitor offer lower price');
    }

    public function test_quotation_lifecycle_notifications(): void
    {
        $this->actingAs($this->manager);

        $account = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Falcon Dynamics',
        ]);

        $quotation = Quotation::create([
            'tenant_id'        => $this->tenant->id,
            'crm_account_id'   => $account->id,
            'sales_person_id'  => $this->salesExecutive->id,
            'quotation_number' => '2026-0099',
            'quotation_date'   => now()->toDateString(),
            'total_amount'     => 125000.00,
            'status'           => 'Draft',
        ]);

        // 1. Quotation Created
        CrmNotificationService::notifyQuotationCreated($quotation);

        // 2. Quotation Approved
        CrmNotificationService::notifyQuotationApproved($quotation);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module'  => 'crm',
            'type'    => 'quotation_approved',
        ]);

        // 3. Quotation Accepted by Customer
        CrmNotificationService::notifyQuotationAccepted($quotation);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module'  => 'crm',
            'type'    => 'quotation_accepted',
        ]);

        // 4. Quotation Rejected
        CrmNotificationService::notifyQuotationRejected($quotation, 'Price too high');
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module'  => 'crm',
            'type'    => 'quotation_rejected',
        ]);
    }
}
