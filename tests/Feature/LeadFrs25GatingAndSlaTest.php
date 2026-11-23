<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmLossReason;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\CrmFollowupReminderService;
use App\Domains\CRM\Services\LeadAssignmentService;
use App\Domains\CRM\Services\LeadFollowupService;
use App\Domains\CRM\Services\LeadService;
use App\Domains\CRM\Services\LeadStageGateService;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadFrs25GatingAndSlaTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Company $company;
    protected Branch $branch;
    protected User $adminUser;
    protected User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Encore Prime Furniture',
            'slug' => 'encore-prime',
            'status' => 'active',
            'plan' => 'enterprise',
            'settings' => [
                'crm_lead_policy' => [
                    'response_sla_minutes' => 60,
                    'sla_working_hours_only' => false,
                    'stale_lead_days' => 3,
                    'stale_alert_cadence_days' => 2,
                    'auto_ack_inbound' => true,
                    'default_nurture_days' => 90,
                ],
            ],
        ]);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Encore Prime HQ',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Main Showroom',
            'code' => 'MS01',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sales Director',
            'email' => 'director@encoreprime.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->salesUser = User::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Sales Executive',
            'email' => 'sales@encoreprime.com',
            'password' => bcrypt('password'),
            'role' => 'sales_executive',
        ]);

        // Seed default loss reasons
        CrmLossReason::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Price Higher than Budget',
            'code' => 'PRICE_TOO_HIGH',
            'requires_competitor' => false,
            'is_active' => true,
        ]);

        CrmLossReason::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lost to Competitor',
            'code' => 'COMPETITOR_WON',
            'requires_competitor' => true,
            'is_active' => true,
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);
    }

    /**
     * C-01: Response SLA clock starts on lead assignment and stops at first customer outreach touch.
     */
    public function test_response_sla_clock_starts_on_assignment_and_stops_on_first_touch(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Apex Architects',
            'contact_person' => 'Rajesh Sharma',
            'phone' => '9876543210',
            'email' => 'rajesh@apex.com',
            'status' => 'New',
        ]);

        $this->assertNull($lead->assigned_at);
        $this->assertNull($lead->first_responded_at);

        // Assign lead to sales user (owner chosen at creation, clock starts on assignment)
        $lead->update(['lead_owner_id' => $this->salesUser->id]);
        app(LeadAssignmentService::class)->assignLeadOwner($lead, 'web_form');
        $lead->refresh();

        $this->assertNotNull($lead->assigned_at);
        $this->assertEquals(60, $lead->response_sla_minutes);
        $this->assertFalse((bool)$lead->response_sla_breached);

        // Now sales executive logs first call outreach
        app(LeadFollowupService::class)->storeFollowup($lead, [
            'type' => 'Call',
            'status' => 'Connected',
            'followup_date' => now()->format('Y-m-d H:i'),
            'notes' => 'Connected with Rajesh. Discussed office workstation requirements.',
        ]);
        $lead->refresh();

        $this->assertNotNull($lead->first_responded_at);
        $this->assertNotNull($lead->last_activity_at);
        $this->assertFalse((bool)$lead->response_sla_breached);
    }

    /**
     * C-01: Overdue Response SLA breaches escalate to Sales Manager.
     */
    public function test_overdue_response_sla_escalates_to_manager(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Zenith Tech Parks',
            'contact_person' => 'Anita Desai',
            'phone' => '9812345678',
            'email' => 'anita@zenith.com',
            'status' => 'New',
            'lead_owner_id' => $this->salesUser->id,
            'assigned_at' => Carbon::now()->subMinutes(90),
            'response_sla_minutes' => 60,
            'first_responded_at' => null,
            'response_sla_breached' => false,
        ]);

        $service = app(CrmFollowupReminderService::class);
        $dispatched = $service->scanOverdueResponseSlas($this->tenant->id);

        $lead->refresh();
        $this->assertTrue((bool)$lead->response_sla_breached);
        $this->assertNotNull($lead->sla_escalated_at);
        $this->assertGreaterThanOrEqual(1, $dispatched);
    }

    /**
     * C-02: Next-Action Mandate prevents leaving an open lead without an accountable next step.
     */
    public function test_next_action_mandate_enforces_scheduled_next_step_on_interaction_log(): void
    {
        $this->actingAs($this->adminUser);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'BlueStar Enterprises',
            'contact_person' => 'Karan Patel',
            'phone' => '9988776655',
            'email' => 'karan@bluestar.com',
            'status' => 'New',
            'lead_owner_id' => $this->salesUser->id,
        ]);

        // Attempting to log a note/call without next_followup_date when no pending future activity exists
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)->post(route('crm.leads.followups.store', $lead->id), [
            'action_mode' => 'log_note',
            'type' => 'Call',
            'status' => 'Connected',
            'notes' => 'Call completed with Karan.',
            // next_followup_date omitted
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['next_followup_date']);

        // Now provide next_followup_date
        $validResponse = $this->withHeader('X-Tenant', $this->tenant->slug)->post(route('crm.leads.followups.store', $lead->id), [
            'action_mode' => 'log_note',
            'type' => 'Call',
            'status' => 'Connected',
            'notes' => 'Call completed with Karan. Scheduled quotation presentation.',
            'next_activity_type' => 'Meeting',
            'next_followup_date' => now()->addDays(2)->format('Y-m-d H:i'),
        ]);

        $validResponse->assertSessionHas('success');
        $lead->refresh();
        $this->assertTrue((bool)$lead->has_active_next_action);
        $this->assertNotNull($lead->next_followup_date);
    }

    /**
     * C-02: Stale lead scanner detects inactivity and alerts owner/manager.
     */
    public function test_stale_lead_scanner_flags_inactive_leads(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Stale Corp',
            'contact_person' => 'Inactivity User',
            'phone' => '9111222333',
            'email' => 'stale@corp.com',
            'status' => 'Qualified',
            'lead_owner_id' => $this->salesUser->id,
            'has_active_next_action' => false,
            'last_activity_at' => Carbon::now()->subDays(5),
            'stale_alert_sent_at' => null,
        ]);

        $service = app(CrmFollowupReminderService::class);
        $dispatched = $service->scanStaleLeads($this->tenant->id);

        $lead->refresh();
        $this->assertNotNull($lead->stale_alert_sent_at);
        $this->assertGreaterThanOrEqual(1, $dispatched);
    }

    /**
     * C-03: Stage gate validator prevents moving to Dealing without verified BANT qualification.
     */
    public function test_stage_gate_blocks_transition_to_dealing_without_bant_fields(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Modern Interiors',
            'contact_person' => 'Sunil Verma',
            'phone' => '9876541230',
            'email' => 'sunil@modern.com',
            'city' => 'Bengaluru',
            'status' => 'Qualified',
            'project_type' => null, // Missing
            'approx_quantity' => null, // Missing
            'expected_amount' => 0, // Missing
        ]);

        $gateService = app(LeadStageGateService::class);
        $result = $gateService->validateStageProgression($lead, 'Dealing');

        $this->assertFalse($result['allowed']);
        $this->assertArrayHasKey('project_type', $result['missing_fields']);
        $this->assertArrayHasKey('approx_quantity', $result['missing_fields']);
        $this->assertArrayHasKey('expected_amount', $result['missing_fields']);
        $this->assertArrayHasKey('contact_role', $result['missing_fields']);
        $this->assertArrayHasKey('decision_process', $result['missing_fields']);

        // Now populate BANT qualification fields and log completed interaction
        $lead->update([
            'project_type' => 'Office Renovation',
            'approx_quantity' => 25,
            'expected_amount' => 150000.00,
            'decision_timeline' => 'Within 30 Days',
            'designation' => 'Facilities Head',
            'decision_maker_identified' => true,
        ]);

        // Still requires at least 1 completed customer touch
        $resultBeforeTouch = $gateService->validateStageProgression($lead, 'Dealing');
        $this->assertFalse($resultBeforeTouch['allowed']);
        $this->assertArrayHasKey('completed_activity', $resultBeforeTouch['missing_fields']);

        // Complete a touch
        LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'assigned_to_id' => $this->salesUser->id,
            'type' => 'Call',
            'status' => 'Completed',
            'followup_date' => now(),
            'notes' => 'Scope verified and budget confirmed.',
        ]);

        $resultAfterTouch = $gateService->validateStageProgression($lead, 'Dealing');
        $this->assertTrue($resultAfterTouch['allowed']);
    }

    /**
     * C-04: Closed Lost requires structured reason and auto-schedules future nurture follow-up.
     */
    public function test_closed_lost_requires_reason_and_schedules_nurture(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Dynamic Studios',
            'contact_person' => 'Neha Kapoor',
            'phone' => '9888777666',
            'email' => 'neha@dynamic.com',
            'status' => 'Qualified',
            'lead_owner_id' => $this->salesUser->id,
        ]);

        $competitorReason = CrmLossReason::where('code', 'COMPETITOR_WON')->first();

        $leadService = app(LeadService::class);
        $result = $leadService->updateLeadStatus($lead, 'Lost', false, [
            'loss_reason_id' => $competitorReason->id,
            'lost_to_competitor' => 'Featherlite',
            'loss_remarks' => 'Competitor offered 15% lower price on ergonomic chairs.',
            'nurture_followup_date' => now()->addDays(90)->toDateString(),
        ]);

        $this->assertTrue($result['success']);
        $lead->refresh();

        $this->assertEquals('Lost', $lead->status);
        $this->assertNotNull($lead->lost_at);
        $this->assertEquals($competitorReason->id, $lead->loss_reason_id);
        $this->assertEquals('Featherlite', $lead->lost_to_competitor);

        // Verify nurture task was auto-created in lead_followups
        $nurtureFollowup = LeadFollowup::where('lead_id', $lead->id)
            ->where('title', 'like', '%Nurture Outreach%')
            ->first();

        $this->assertNotNull($nurtureFollowup);
        $this->assertEquals('Pending', $nurtureFollowup->status);
        $this->assertStringContainsString('Featherlite', $nurtureFollowup->notes);
    }

    /**
     * C-04: Reopening a Lost lead by a regular sales user without manager permission is blocked.
     */
    public function test_reopening_lost_lead_requires_manager_permission(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Lost Company',
            'status' => 'Lost',
            'lost_at' => now(),
            'lead_owner_id' => $this->salesUser->id,
        ]);

        $this->actingAs($this->salesUser);

        $leadService = app(LeadService::class);
        $result = $leadService->updateLeadStatus($lead, 'Qualified', false);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('permission', $result['message']);
    }

    /**
     * C-05: Inbound Web-to-Lead stamps provenance and records auto-acknowledgment.
     */
    public function test_inbound_web_to_lead_stamps_provenance_and_acknowledgement(): void
    {
        $response = $this->post(route('forms.lead-capture.submit'), [
            'tenant_id' => $this->tenant->slug,
            'contact_person' => 'Vikas Malhotra',
            'company_name' => 'Prime Retailers',
            'phone' => '9876501234',
            'email' => 'vikas@primeretail.com',
            'requirement' => 'Need 50 custom cashier counters for retail expansion.',
            'city' => 'Mumbai',
        ]);

        $response->assertSuccessful();

        $lead = Lead::where('email', 'vikas@primeretail.com')->first();
        $this->assertNotNull($lead);
        $this->assertTrue((bool)$lead->is_inbound_enquiry);
        $this->assertEquals('web_form', $lead->provenance);
        $this->assertNotNull($lead->acknowledgement_sent_at);
    }

    /**
     * BASE-05: Strict CRM Isolation — Ensure CRM 'Won' creates zero Job Orders or manufacturing demand.
     */
    public function test_crm_won_does_not_create_manufacturing_job_orders_base_05(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Corporate Towers Ltd',
            'contact_person' => 'Naveen Jindal',
            'phone' => '9811223344',
            'email' => 'naveen@corporatetowers.com',
            'status' => 'Dealing',
            'expected_amount' => 500000.00,
            'lead_owner_id' => $this->adminUser->id,
        ]);

        // Count job orders / production demands before
        $jobOrdersBefore = \Illuminate\Support\Facades\Schema::hasTable('job_orders')
            ? \DB::table('job_orders')->count()
            : 0;

        // Transition Lead to Won via system trigger
        $leadService = app(LeadService::class);
        $result = $leadService->updateLeadStatus($lead, 'Won', true);

        $this->assertTrue($result['success']);
        $lead->refresh();
        $this->assertEquals('Won', $lead->status);
        $this->assertTrue((bool)$lead->is_customer);

        // Count job orders after
        $jobOrdersAfter = \Illuminate\Support\Facades\Schema::hasTable('job_orders')
            ? \DB::table('job_orders')->count()
            : 0;

        // Strict BASE-05 Isolation: Zero manufacturing demand / job orders created from CRM Won
        $this->assertEquals($jobOrdersBefore, $jobOrdersAfter);
    }

    /**
     * C-04: CRM Settings renders Loss Reasons tab and supports Store, Toggle, and Delete.
     */
    public function test_crm_settings_renders_loss_reasons_and_supports_crud(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Visit CRM Settings
        $response = $this->get(route('crm.settings.index', ['tab' => 'loss-reasons']));
        $response->assertStatus(200);
        $response->assertSee('Structured Loss Reasons Master');
        $response->assertSee('PRICE_TOO_HIGH');

        // 2. Store new custom tenant loss reason
        $storeResponse = $this->post(route('crm.settings.loss-reasons.store'), [
            'name' => 'Price Too High',
            'follow_up_days' => 30,
            'requires_competitor' => 0,
            'is_active' => 1,
        ]);
        $storeResponse->assertRedirect(route('crm.settings.index', ['tab' => 'loss-reasons']));

        $createdReason = CrmLossReason::where('name', 'Price Too High')
            ->where('tenant_id', $this->tenant->id)
            ->first();
        $this->assertNotNull($createdReason);
        $this->assertTrue((bool)$createdReason->is_active);

        // 3. Toggle Status
        $toggleResponse = $this->post(route('crm.settings.loss-reasons.toggle', $createdReason->id));
        $toggleResponse->assertRedirect(route('crm.settings.index', ['tab' => 'loss-reasons']));
        $createdReason->refresh();
        $this->assertFalse((bool)$createdReason->is_active);

        // 4. Delete custom reason
        $deleteResponse = $this->delete(route('crm.settings.loss-reasons.destroy', $createdReason->id));
        $deleteResponse->assertRedirect(route('crm.settings.index', ['tab' => 'loss-reasons']));
        $this->assertNull(CrmLossReason::find($createdReason->id));
    }
    private function makeLead(array $over = []): Lead
    {
        return Lead::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'company_name' => 'Extra Co',
            'contact_person' => 'Extra Person',
            'phone' => '9000000001',
            'email' => 'extra' . uniqid() . '@example.com',
            'status' => 'Qualified',
            'lead_owner_id' => $this->salesUser->id,
        ], $over));
    }

    /** C-04: a manual loss without a structured reason is refused on the web screen. */
    public function test_marking_lost_without_reason_is_refused_and_competitor_rule_applies(): void
    {
        $lead = $this->makeLead();
        $this->actingAs($this->adminUser);
        $json = ['Accept' => 'application/json'];
        $url = route('crm.leads.updateStatus', $lead->id);

        $this->patch($url, ['status' => 'Lost'], $json)->assertStatus(422);

        $competitor = CrmLossReason::where('code', 'COMPETITOR_WON')->first();
        $this->patch($url, ['status' => 'Lost', 'loss_reason_id' => $competitor->id], $json)->assertStatus(422);

        $this->patch($url, ['status' => 'Lost', 'loss_reason_id' => $competitor->id, 'lost_to_competitor' => 'Rival'], $json)->assertSuccessful();
        $this->assertEquals('Lost', $lead->fresh()->status);
    }

    /** C-04: an administrator can reopen a lost lead (previously the check crashed for everyone). */
    public function test_admin_can_reopen_lost_lead(): void
    {
        $lead = $this->makeLead(['status' => 'Lost', 'lost_at' => now()]);
        $this->actingAs($this->adminUser);

        $result = app(LeadService::class)->updateLeadStatus($lead, 'New', false);

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertEquals('New', $lead->fresh()->status);
    }

    /** C-01: scheduling a call (Pending) is a plan, not a response; it must not stop the SLA clock. */
    public function test_pending_scheduled_call_does_not_stop_sla_clock(): void
    {
        $lead = $this->makeLead(['status' => 'New', 'assigned_at' => now()->subMinutes(5)]);
        $this->actingAs($this->adminUser);

        app(LeadFollowupService::class)->storeFollowup($lead, [
            'type' => 'Call',
            'status' => 'Pending',
            'followup_date' => now()->addDay()->format('Y-m-d H:i'),
            'notes' => 'Planned call.',
        ]);

        $this->assertNull($lead->fresh()->first_responded_at);
    }

    /** C-05 / F04 LEAD-10: researched and imported prospects are never acknowledged. */
    public function test_acknowledgement_is_blocked_for_research_and_import_leads(): void
    {
        $svc = app(\App\Domains\CRM\Services\LeadAcknowledgementService::class);
        $settings = ['crm_lead_policy' => ['auto_ack_inbound' => true]];

        $inbound = $this->makeLead(['is_inbound_enquiry' => true, 'provenance' => 'web_form']);
        $research = $this->makeLead(['is_inbound_enquiry' => true, 'provenance' => 'researched']);
        $imported = $this->makeLead(['is_inbound_enquiry' => false, 'provenance' => 'import']);

        $this->assertTrue($svc->shouldAcknowledge($inbound, $settings));
        $this->assertFalse($svc->shouldAcknowledge($research, $settings));
        $this->assertFalse($svc->shouldAcknowledge($imported, $settings));
        $this->assertFalse($svc->shouldAcknowledge($inbound, []), 'off unless the tenant enables it');
    }

    /** C-02: bulk reassign needs an owner and restarts the SLA clock for the new owner. */
    public function test_bulk_reassign_requires_owner_and_restarts_sla(): void
    {
        $lead = $this->makeLead(['status' => 'New', 'assigned_at' => now()->subHours(3), 'response_sla_breached' => true]);
        $this->actingAs($this->adminUser);

        $this->post(route('crm.leads.bulkAssign'), ['lead_ids' => [$lead->id], 'lead_owner_id' => null], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->post(route('crm.leads.bulkAssign'), ['lead_ids' => [$lead->id], 'lead_owner_id' => $this->adminUser->id], ['Accept' => 'application/json'])
            ->assertSuccessful();

        $lead->refresh();
        $this->assertEquals($this->adminUser->id, $lead->lead_owner_id);
        $this->assertFalse((bool) $lead->response_sla_breached);
        $this->assertTrue($lead->assigned_at->gt(now()->subMinute()));
    }

    /** C-02: an unassigned open lead is surfaced by the stale scanner. */
    public function test_unassigned_open_lead_is_flagged_stale(): void
    {
        $lead = $this->makeLead(['lead_owner_id' => null, 'status' => 'New', 'last_activity_at' => now()]);

        app(CrmFollowupReminderService::class)->scanStaleLeads($this->tenant->id);

        $this->assertNotNull($lead->fresh()->stale_alert_sent_at);
    }

    /** C-03: editing a lead from a form without the qualification inputs must not wipe them. */
    public function test_edit_without_qualification_inputs_preserves_them(): void
    {
        $lead = $this->makeLead(['project_type' => 'Office', 'approx_quantity' => 10, 'decision_maker_identified' => true]);

        app(LeadService::class)->updateLead($lead, ['contact_person' => 'Changed', 'lead_owner_id' => $this->salesUser->id], [], []);

        $lead->refresh();
        $this->assertEquals('Office', $lead->project_type);
        $this->assertEquals(10, $lead->approx_quantity);
        $this->assertTrue((bool) $lead->decision_maker_identified);
    }
    private function setAssignment(array $assignment): void
    {
        $settings = $this->tenant->settings;
        $settings['lead_assignment'] = array_merge(\App\Domains\CRM\Services\LeadAssignmentService::getDefaultConfig(), $assignment);
        $this->tenant->update(['settings' => $settings]);
    }

    private function secondSalesUser(): User
    {
        return User::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'name' => 'Second Exec',
            'email' => 'second' . uniqid() . '@encoreprime.com',
            'password' => bcrypt('password'),
            'role' => 'sales_executive',
        ]);
    }

    /** Step 3: territory / segment / source / product rules with workload balancing. */
    public function test_assignment_rules_route_by_territory_and_workload(): void
    {
        $second = $this->secondSalesUser();
        $this->setAssignment(['rules' => [[
            'name' => 'Gujarat desks', 'territory' => 'Gujarat', 'segment' => '', 'source' => '', 'product' => 'chair',
            'strategy' => 'least_loaded', 'owner_ids' => [$this->salesUser->id, $second->id], 'active' => true,
        ]]]);

        // salesUser already carries two open leads, so the least-loaded pick is $second
        $this->makeLead(['status' => 'New']);
        $this->makeLead(['status' => 'New']);

        $match = $this->makeLead(['lead_owner_id' => null, 'state' => 'Gujarat', 'requirement' => 'Need 40 ergonomic chairs']);
        app(LeadAssignmentService::class)->assignLeadOwner($match, 'web_form');
        $this->assertEquals($second->id, $match->fresh()->lead_owner_id);

        $noMatch = $this->makeLead(['lead_owner_id' => null, 'state' => 'Kerala', 'requirement' => 'Need 40 ergonomic chairs']);
        $this->setAssignment(['mode' => 'manual_creator', 'default_owner_id' => $this->adminUser->id, 'rules' => [[
            'name' => 'Gujarat desks', 'territory' => 'Gujarat', 'strategy' => 'first', 'owner_ids' => [$second->id], 'active' => true,
        ]]]);
        app(LeadAssignmentService::class)->assignLeadOwner($noMatch, 'web_form');
        $this->assertEquals($this->adminUser->id, $noMatch->fresh()->lead_owner_id);
    }

    /** Step 3: a returning company goes to its account owner. */
    public function test_existing_account_owner_gets_the_lead(): void
    {
        $this->setAssignment(['prefer_account_owner' => true]);
        \App\Domains\CRM\Models\CrmAccount::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Known Buyer Ltd', 'email' => 'buyer@known.com',
            'status' => 'active', 'owner_id' => $this->adminUser->id,
        ]);

        $lead = $this->makeLead(['lead_owner_id' => null, 'company_name' => 'Known Buyer Ltd', 'email' => 'buyer@known.com']);
        app(LeadAssignmentService::class)->assignLeadOwner($lead, 'web_form');

        $this->assertEquals($this->adminUser->id, $lead->fresh()->lead_owner_id);
    }

    /** Checks: every open owned lead has a dated next action; the only one cannot be deleted. */
    public function test_assignment_creates_next_action_and_last_one_cannot_be_deleted(): void
    {
        $lead = $this->makeLead(['status' => 'New', 'lead_owner_id' => null]);
        $this->setAssignment(['mode' => 'manual_creator', 'default_owner_id' => $this->salesUser->id]);
        app(LeadAssignmentService::class)->assignLeadOwner($lead, 'web_form');

        $pending = $lead->fresh()->followups()->where('status', 'Pending')->get();
        $this->assertCount(1, $pending);
        $this->assertTrue((bool) $lead->fresh()->has_active_next_action);

        $this->actingAs($this->adminUser);
        $this->delete(route('crm.followups.destroy', $pending->first()->id), [], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertDatabaseHas('lead_followups', ['id' => $pending->first()->id]);
    }

    /** Hold: reason + review date required, pauses SLA/stale alerts, review task is the next action. */
    public function test_hold_pauses_alerts_and_keeps_a_dated_review(): void
    {
        $lead = $this->makeLead(['status' => 'New', 'assigned_at' => now()->subHours(5), 'last_activity_at' => now()->subDays(10)]);
        $this->actingAs($this->adminUser);

        $this->post(route('crm.leads.hold', $lead->id), ['hold_type' => 'customer_pause', 'hold_reason' => '', 'review_date' => now()->addDays(10)->toDateString()], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->post(route('crm.leads.hold', $lead->id), [
            'hold_type' => 'customer_pause', 'hold_reason' => 'Client asked to call after the audit', 'review_date' => now()->addDays(10)->toDateString(),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $lead->refresh();
        $this->assertNotNull($lead->on_hold_until);
        $this->assertTrue($lead->followups()->where('status', 'Pending')->where('title', 'like', 'Hold review%')->exists());

        $svc = app(CrmFollowupReminderService::class);
        $svc->scanOverdueResponseSlas($this->tenant->id);
        $svc->scanStaleLeads($this->tenant->id);
        $lead->refresh();
        $this->assertFalse((bool) $lead->response_sla_breached, 'SLA clock is suspended while on hold');
        $this->assertNull($lead->stale_alert_sent_at, 'a held lead is not stale');

        $this->post(route('crm.leads.hold.resume', $lead->id), [], ['Accept' => 'application/json'])->assertSuccessful();
        $this->assertNull($lead->fresh()->on_hold_until);
        $this->assertDatabaseHas('lead_histories', ['lead_id' => $lead->id, 'event_type' => 'hold_ended']);
    }

    /** Step 2: merging keeps every interaction. */
    public function test_merge_moves_all_activity_to_the_surviving_lead(): void
    {
        $keep = $this->makeLead(['company_name' => 'Dup Co', 'email' => 'keep@dup.com', 'phone' => null]);
        $dupe = $this->makeLead(['company_name' => 'Dup Co', 'email' => 'dupe@dup.com', 'phone' => '9123456789']);
        LeadFollowup::create([
            'tenant_id' => $this->tenant->id, 'lead_id' => $dupe->id, 'assigned_to_id' => $this->salesUser->id,
            'type' => 'Call', 'status' => 'Completed', 'followup_date' => now()->subDay(), 'notes' => 'Earlier call on the duplicate',
        ]);
        $this->actingAs($this->adminUser);

        $this->post(route('crm.leads.merge', $keep->id), ['source_lead_id' => $dupe->id], ['Accept' => 'application/json'])->assertSuccessful();

        $this->assertSoftDeleted('leads', ['id' => $dupe->id]);
        $this->assertDatabaseHas('leads', ['id' => $dupe->id, 'merged_into_lead_id' => $keep->id]);
        $this->assertDatabaseHas('lead_followups', ['lead_id' => $keep->id, 'notes' => 'Earlier call on the duplicate']);
        $this->assertEquals('9123456789', $keep->fresh()->phone, 'missing data is filled from the merged lead');
        $this->assertDatabaseHas('lead_histories', ['lead_id' => $keep->id, 'event_type' => 'merged']);
    }

    /** SLA in working hours only. */
    public function test_working_hours_sla_ignores_nights_and_sundays(): void
    {
        // Friday 17:00 -> Saturday 10:00 = 60 + 60 working minutes (18:00 close, 09:00 open, Saturday is a working day)
        $from = Carbon::parse('2026-11-20 17:00:00');
        $to = Carbon::parse('2026-11-21 10:00:00');
        $this->assertEquals(120, CrmFollowupReminderService::workingMinutesBetween($from, $to));
        // Saturday 17:00 -> Monday 10:00 skips Sunday and the night
        $this->assertEquals(120, CrmFollowupReminderService::workingMinutesBetween(Carbon::parse('2026-11-21 17:00:00'), Carbon::parse('2026-11-23 10:00:00')));
    }

    /** Acknowledgement permission: opted-out prospects are never messaged; template is configurable. */
    public function test_opt_out_blocks_acknowledgement(): void
    {
        $svc = app(\App\Domains\CRM\Services\LeadAcknowledgementService::class);
        $settings = ['crm_lead_policy' => ['auto_ack_inbound' => true]];
        $ok = $this->makeLead(['is_inbound_enquiry' => true, 'provenance' => 'web_form']);
        $optedOut = $this->makeLead(['is_inbound_enquiry' => true, 'provenance' => 'web_form', 'communication_opt_out' => true]);

        $this->assertTrue($svc->shouldAcknowledge($ok, $settings));
        $this->assertFalse($svc->shouldAcknowledge($optedOut, $settings));
    }

    /** Dealing leads can still be closed as Lost with a reason; the linked deal is closed too. */
    public function test_dealing_lead_can_be_lost_and_closes_its_deal(): void
    {
        $account = \App\Domains\CRM\Models\CrmAccount::create(['tenant_id' => $this->tenant->id, 'name' => 'Deal Co', 'status' => 'active', 'owner_id' => $this->adminUser->id]);
        $deal = \App\Domains\CRM\Models\CrmDeal::create([
            'tenant_id' => $this->tenant->id, 'crm_account_id' => $account->id, 'title' => 'Deal Co deal', 'stage' => 'Qualification',
            'estimated_value' => 100000, 'probability' => 40, 'owner_id' => $this->adminUser->id,
        ]);
        $lead = $this->makeLead(['status' => 'Dealing', 'crm_deal_id' => $deal->id, 'crm_account_id' => $account->id]);
        $this->actingAs($this->adminUser);
        $reason = CrmLossReason::where('code', 'PRICE_TOO_HIGH')->first();

        $this->patch(route('crm.leads.updateStatus', $lead->id), ['status' => 'Lost', 'loss_reason_id' => $reason->id], ['Accept' => 'application/json'])->assertSuccessful();

        $this->assertEquals('Lost', $lead->fresh()->status);
        $this->assertEquals('Closed Lost', $deal->fresh()->stage);
    }

    /** Reports: response time, funnel, ageing, win/loss, forecast. */
    public function test_lead_reports_page_renders_all_sections(): void
    {
        $this->makeLead(['status' => 'New', 'assigned_at' => now()->subHours(2), 'first_responded_at' => now()->subHour(), 'expected_amount' => 50000, 'expected_sale_date' => now()->addMonth()]);
        $this->actingAs($this->adminUser);

        $this->get(route('crm.reports.leads'))
            ->assertOk()
            ->assertSee('Response time')
            ->assertSee('Funnel conversion')
            ->assertSee('Ageing of open leads')
            ->assertSee('Win and loss reasons')
            ->assertSee('Forecast by salesperson');
    }
}
