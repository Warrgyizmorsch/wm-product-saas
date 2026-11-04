<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingAssignedAndTaggedSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $leadOwner;
    protected User $delegateUser;
    protected User $attendeeUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Meeting Split Tenant',
            'slug' => 'meeting-split-test',
        ]);

        $this->leadOwner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Demo Admin (Lead Owner)',
            'email' => 'admin@meetingtest.com',
        ]);

        $this->delegateUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rahul Sharma (Delegate Host)',
            'email' => 'rahul@meetingtest.com',
        ]);

        $this->attendeeUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Aarti Mehta (Attendee)',
            'email' => 'aarti@meetingtest.com',
        ]);
    }

    public function test_followup_creation_defaults_assigned_to_id_to_lead_owner(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Acme Corporation',
            'contact_person' => 'John Doe',
            'lead_owner_id' => $this->leadOwner->id,
            'status' => 'Qualified',
        ]);

        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'type' => 'Meeting',
            'title' => 'Product Demo with Acme',
            'followup_date' => Carbon::now()->addHours(2),
            'status' => 'Pending',
            'tagged_user_ids' => [$this->attendeeUser->id],
        ]);

        $this->assertEquals($this->leadOwner->id, $followup->assigned_to_id, 'assigned_to_id should default to lead_owner_id');
        $this->assertEquals([$this->attendeeUser->id], $followup->tagged_user_ids, 'tagged_user_ids should remain intact as attendees');
        $this->assertEquals($this->leadOwner->id, $followup->assignedTo?->id);
    }

    public function test_assign_meeting_updates_assigned_to_id_and_preserves_tagged_attendees(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Global Logistics Ltd',
            'contact_person' => 'Jane Smith',
            'lead_owner_id' => $this->leadOwner->id,
            'status' => 'Qualified',
        ]);

        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'type' => 'Meeting',
            'title' => 'Client Site Inspection',
            'followup_date' => Carbon::now()->addDay(),
            'status' => 'Pending',
            'tagged_user_ids' => [$this->attendeeUser->id],
        ]);

        Sanctum::actingAs($this->leadOwner);

        // Call the API endpoint to delegate the meeting to Rahul
        $response = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->withSession(['tenant_slug' => $this->tenant->slug])
            ->postJson(route('api.crm.meetings.assign', $followup->id), [
                'assigned_to_id' => $this->delegateUser->id,
                'notes' => 'Please visit client site today and conduct demo.',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'assigned_to_id' => $this->delegateUser->id,
                'assigned_to' => [
                    'id' => $this->delegateUser->id,
                    'name' => $this->delegateUser->name,
                ],
            ]
        ]);

        $followup->refresh();

        // 1. Primary assigned host is now Rahul
        $this->assertEquals($this->delegateUser->id, $followup->assigned_to_id);

        // 2. Aarti Mehta is still in tagged_user_ids (Attendees NOT wiped out!)
        $this->assertEquals([$this->attendeeUser->id], $followup->tagged_user_ids);

        // 3. Handover notes prepended
        $this->assertStringContainsString('Assigned to Rahul Sharma', $followup->notes);
    }

    public function test_my_meetings_api_returns_meeting_for_both_host_and_tagged_attendees(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Apex Solutions',
            'contact_person' => 'David Miller',
            'lead_owner_id' => $this->leadOwner->id,
            'status' => 'Qualified',
        ]);

        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'assigned_to_id' => $this->delegateUser->id,
            'type' => 'Meeting',
            'title' => 'Commercial Negotiation',
            'followup_date' => Carbon::now()->addDays(2),
            'status' => 'Pending',
            'tagged_user_ids' => [$this->attendeeUser->id],
        ]);

        // 1. Delegate User (Host) views My Meetings
        Sanctum::actingAs($this->delegateUser);
        $hostResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->withSession(['tenant_slug' => $this->tenant->slug])
            ->getJson('/api/crm/my-meetings?user_id=' . $this->delegateUser->id);
        $hostResponse->assertStatus(200);
        $hostData = $hostResponse->json();
        $this->assertNotEmpty($hostData['planned']);
        $this->assertEquals($followup->id, $hostData['planned'][0]['id']);
        $this->assertTrue($hostData['planned'][0]['is_host']);
        $this->assertFalse($hostData['planned'][0]['is_tagged_attendee']);

        // 2. Attendee User (Aarti) views My Meetings
        Sanctum::actingAs($this->attendeeUser);
        $attendeeResponse = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->withSession(['tenant_slug' => $this->tenant->slug])
            ->getJson('/api/crm/my-meetings?user_id=' . $this->attendeeUser->id);
        $attendeeResponse->assertStatus(200);
        $attendeeData = $attendeeResponse->json();
        $this->assertNotEmpty($attendeeData['planned']);
        $this->assertEquals($followup->id, $attendeeData['planned'][0]['id']);
        $this->assertFalse($attendeeData['planned'][0]['is_host']);
        $this->assertTrue($attendeeData['planned'][0]['is_tagged_attendee']);
    }

    public function test_web_meetings_index_renders_properly_for_host_and_attendee(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Web Render Corp',
            'contact_person' => 'Sam Wilson',
            'lead_owner_id' => $this->leadOwner->id,
            'status' => 'Qualified',
        ]);

        $followup = LeadFollowup::create([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'assigned_to_id' => $this->leadOwner->id,
            'type' => 'Meeting',
            'title' => 'Web View Test Meeting',
            'followup_date' => Carbon::now()->addDays(1),
            'status' => 'Pending',
            'tagged_user_ids' => [$this->attendeeUser->id],
        ]);

        // 1. Lead Owner views meetings page
        $this->actingAs($this->leadOwner);
        $responseOwner = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/meetings?tab=planned');
        $responseOwner->assertStatus(200);
        $responseOwner->assertSee('Host');

        // 2. Aarti Mehta (Attendee) views meetings page
        $this->actingAs($this->attendeeUser);
        $responseAttendee = $this->withSession(['tenant_slug' => $this->tenant->slug])
            ->get('/crm/meetings?tab=planned');
        $responseAttendee->assertStatus(200);
        $responseAttendee->assertSee('Attendee');
        $responseAttendee->assertSee('View Only');
    }
}
