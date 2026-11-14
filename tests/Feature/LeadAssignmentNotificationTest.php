<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Services\LeadService;
use App\Domains\Platform\Services\NotificationEventCatalog;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;
    protected User $salesExecutive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'CRM Test Tenant',
            'slug' => 'crm-test',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@crmtest.com',
        ]);

        $this->salesExecutive = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sales Rep One',
            'email' => 'salesrep@crmtest.com',
        ]);
    }

    public function test_crm_lead_assigned_event_registered_in_catalog(): void
    {
        $events = NotificationEventCatalog::getEvents();
        $this->assertArrayHasKey('crm', $events);
        $this->assertArrayHasKey('crm.lead.assigned', $events['crm']['events']);

        $event = NotificationEventCatalog::getEventDetails('crm.lead.assigned');
        $this->assertNotNull($event);
        $this->assertEquals('Lead Assigned to Executive', $event['label']);
    }

    public function test_lead_creation_with_owner_creates_notification_for_assigned_user(): void
    {
        $this->actingAs($this->admin);

        $leadService = app(LeadService::class);
        $lead = $leadService->storeLead([
            'company_name' => 'Acme Corporation',
            'contact_person' => 'John Doe',
            'email' => 'john@acme.com',
            'phone' => '9876543210',
            'source' => 'Website',
            'lead_owner_id' => $this->salesExecutive->id,
        ], [], []);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'lead_owner_id' => $this->salesExecutive->id,
        ]);

        // Assert Notification was created for the assigned sales executive
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module' => 'crm',
            'type' => 'lead_assigned',
        ]);

        $notif = Notification::where('user_id', $this->salesExecutive->id)
            ->where('type', 'lead_assigned')
            ->latest('id')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('New Lead Assigned', $notif->title);
        $this->assertStringContainsString('Acme Corporation', $notif->title);
    }

    public function test_lead_owner_update_creates_notification_for_new_owner(): void
    {
        $this->actingAs($this->admin);

        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Beta Industries',
            'contact_person' => 'Jane Smith',
            'lead_owner_id' => $this->admin->id,
            'status' => 'New',
        ]);

        $leadRepo = app(\App\Domains\CRM\Repositories\LeadRepository::class);
        $leadRepo->updateOwner($lead, $this->salesExecutive->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->salesExecutive->id,
            'module' => 'crm',
            'type' => 'lead_assigned',
        ]);

        $notif = Notification::where('user_id', $this->salesExecutive->id)
            ->where('type', 'lead_assigned')
            ->latest('id')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Beta Industries', $notif->title);
    }
}
