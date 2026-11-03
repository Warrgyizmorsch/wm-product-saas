<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\CrmFollowupReminderService;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFollowupReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $leadOwner;
    protected User $dealOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'CRM Reminder Tenant',
            'slug' => 'crm-reminder-test',
        ]);

        $this->leadOwner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lead Executive',
            'email' => 'leadowner@crmtest.com',
        ]);

        $this->dealOwner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deal Executive',
            'email' => 'dealowner@crmtest.com',
        ]);
    }

    public function test_followup_due_and_upcoming_events_registered_in_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();
        $this->assertArrayHasKey('crm', $events);
        $this->assertArrayHasKey('crm.lead.followup_upcoming', $events['crm']['events']);
        $this->assertArrayHasKey('crm.lead.followup_due', $events['crm']['events']);
        $this->assertArrayHasKey('crm.deal.followup_upcoming', $events['crm']['events']);
        $this->assertArrayHasKey('crm.deal.followup_due', $events['crm']['events']);

        $upcomingLead = NotificationEventCatalog::getEventDetails('crm.lead.followup_upcoming');
        $this->assertNotNull($upcomingLead);
        $this->assertStringContainsString('Advance Reminder', $upcomingLead['label']);

        $dueLead = NotificationEventCatalog::getEventDetails('crm.lead.followup_due');
        $this->assertNotNull($dueLead);
        $this->assertStringContainsString('Exact Time', $dueLead['label']);
    }

    public function test_lead_followup_advance_reminder_dispatches_30_mins_before(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Beta Technologies',
            'contact_person' => 'Sunil Verma',
            'email' => 'sunil@beta.com',
            'lead_owner_id' => $this->leadOwner->id,
            'status' => 'New',
        ]);

        // Meeting scheduled 25 minutes from now (inside the 30-min advance window)
        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'type' => 'Demo',
            'title' => 'Software Walkthrough',
            'followup_date' => Carbon::now()->addMinutes(25),
            'status' => 'Pending',
            'notes' => 'Client requested live presentation',
        ]);

        $service = app(CrmFollowupReminderService::class);
        $dispatched = $service->dispatchDueReminders($this->tenant->id, $this->leadOwner->id);

        $this->assertEquals(1, $dispatched);

        // Verify advance_reminder_sent_at is stamped, but reminder_sent_at is still null
        $followup->refresh();
        $this->assertNotNull($followup->advance_reminder_sent_at);
        $this->assertNull($followup->reminder_sent_at);

        // Verify Notification record created in DB with type 'followup_upcoming'
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->leadOwner->id,
            'module' => 'crm',
            'type' => 'followup_upcoming',
        ]);

        $notif = Notification::where('user_id', $this->leadOwner->id)->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Upcoming Follow-up', $notif->title);
        $this->assertStringContainsString('Beta Technologies', $notif->title);
    }

    public function test_lead_followup_due_dispatches_notification_at_exact_time(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Apex Enterprises',
            'contact_person' => 'Rajesh Sharma',
            'email' => 'rajesh@apex.com',
            'lead_owner_id' => $this->leadOwner->id,
            'status' => 'New',
        ]);

        // Meeting scheduled 5 minutes ago (exact time has arrived)
        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'type' => 'Call',
            'title' => 'Product Demo Call',
            'followup_date' => Carbon::now()->subMinutes(5),
            'status' => 'Pending',
            'notes' => 'Discuss enterprise pricing',
        ]);

        $service = app(CrmFollowupReminderService::class);
        $dispatched = $service->dispatchDueReminders($this->tenant->id, $this->leadOwner->id);

        $this->assertEquals(1, $dispatched);

        // Verify reminder_sent_at is stamped
        $followup->refresh();
        $this->assertNotNull($followup->reminder_sent_at);

        // Verify Notification record created in DB for lead owner
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->leadOwner->id,
            'module' => 'crm',
            'type' => 'followup_due',
        ]);

        $notif = Notification::where('user_id', $this->leadOwner->id)->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Apex Enterprises', $notif->title);
        $this->assertStringContainsString('Call', $notif->message);

        // Calling again does not duplicate
        $secondRun = $service->dispatchDueReminders($this->tenant->id, $this->leadOwner->id);
        $this->assertEquals(0, $secondRun);
    }

    public function test_deal_meeting_due_dispatches_notification_and_pusher_to_deal_owner(): void
    {
        $account = CrmAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zenith Global Ltd',
        ]);

        $deal = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'crm_account_id' => $account->id,
            'title' => 'ERP Rollout Deal 2026',
            'owner_id' => $this->dealOwner->id,
            'stage' => 'Proposal',
            'estimated_value' => 750000.00,
        ]);

        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'crm_deal_id' => $deal->id,
            'lead_id' => null,
            'type' => 'Meeting',
            'title' => 'Final Commercial Negotiation',
            'followup_date' => Carbon::now()->subMinutes(10),
            'status' => 'Pending',
            'notes' => 'Meeting with CFO at HQ',
        ]);

        $service = app(CrmFollowupReminderService::class);
        $dispatched = $service->dispatchDueReminders($this->tenant->id, $this->dealOwner->id);

        $this->assertEquals(1, $dispatched);

        // Verify reminder_sent_at is stamped
        $followup->refresh();
        $this->assertNotNull($followup->reminder_sent_at);

        // Verify Notification record created in DB for deal owner
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->dealOwner->id,
            'module' => 'crm',
            'type' => 'followup_due',
        ]);

        $notif = Notification::where('user_id', $this->dealOwner->id)->latest('id')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('ERP Rollout Deal 2026', $notif->title);
        $this->assertStringContainsString('Zenith Global Ltd', $notif->message);

        // Calling again does not duplicate
        $secondRun = $service->dispatchDueReminders($this->tenant->id, $this->dealOwner->id);
        $this->assertEquals(0, $secondRun);
    }

    public function test_artisan_send_crm_followup_reminders_command_runs_successfully(): void
    {
        $this->artisan('crm:send-followup-reminders', ['--tenant' => $this->tenant->id])
            ->assertSuccessful();
    }
}
