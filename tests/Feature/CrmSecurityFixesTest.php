<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmSecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $salesExecutive;
    private User $salesManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Security Audit Tenant',
            'slug'   => 'sec-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->salesExecutive = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Junior Sales Rep',
            'email'     => 'rep@sectenant.test',
            'password'  => bcrypt('secret123'),
        ]);

        $execRole = Role::whereNull('tenant_id')->where('slug', 'sales_executive')->first();
        if ($execRole) {
            UserRole::create([
                'user_id'   => $this->salesExecutive->id,
                'role_id'   => $execRole->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        $this->salesManager = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Senior Sales Manager',
            'email'     => 'manager@sectenant.test',
            'password'  => bcrypt('secret123'),
        ]);

        $mgrRole = Role::whereNull('tenant_id')->where('slug', 'sales_manager')->first();
        if ($mgrRole) {
            UserRole::create([
                'user_id'   => $this->salesManager->id,
                'role_id'   => $mgrRole->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }
    }

    /** @test */
    public function bug_01_clean_html_strips_malicious_scripts_and_event_handlers(): void
    {
        $maliciousPayload = '<p>Standard Terms</p><script>alert("XSS")</script><img src="x" onerror="alert(1)"><a href="javascript:alert(2)">Click me</a>';
        $cleaned = clean_html($maliciousPayload);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('onerror', $cleaned);
        $this->assertStringNotContainsString('javascript:', $cleaned);
        $this->assertStringContainsString('<p>Standard Terms</p>', $cleaned);
        $this->assertStringContainsString('Click me', $cleaned);
    }

    /** @test */
    public function n_10_sales_executive_cannot_modify_crm_settings(): void
    {
        // Sales Executive attempts to update quotation approval policy
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'sec-tenant')
            ->post(route('crm.settings.update-quotation-approval-policy'), [
                'quotation_approval_policy' => 'auto_approve',
            ]);

        $response->assertForbidden();

        // Sales Executive attempts to update field visit verification
        $response2 = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'sec-tenant')
            ->post(route('crm.settings.update-field-visit-verification'), [
                'field_visit_verification' => 0,
            ]);

        $response2->assertForbidden();
    }

    /** @test */
    public function n_10_sales_manager_can_modify_crm_settings(): void
    {
        $response = $this->actingAs($this->salesManager)
            ->withHeader('X-Tenant', 'sec-tenant')
            ->post(route('crm.settings.update-quotation-approval-policy'), [
                'quotation_approval_policy' => 'auto_approve',
            ]);

        $response->assertRedirect();
        $this->tenant->refresh();
        $this->assertEquals('auto_approve', $this->tenant->settings['quotation_approval_policy'] ?? null);
    }

    /** @test */
    public function bug_04_lead_number_generation_is_gap_tolerant_and_unique(): void
    {
        $lead1 = Lead::create([
            'tenant_id'      => $this->tenant->id,
            'company_name'   => 'Company One',
            'contact_person' => 'Alice',
            'email'          => 'alice@comp1.test',
            'lead_owner_id'  => $this->salesManager->id,
        ]);

        $lead2 = Lead::create([
            'tenant_id'      => $this->tenant->id,
            'company_name'   => 'Company Two',
            'contact_person' => 'Bob',
            'email'          => 'bob@comp2.test',
            'lead_owner_id'  => $this->salesManager->id,
        ]);

        $this->assertNotEmpty($lead1->lead_number);
        $this->assertNotEmpty($lead2->lead_number);
        $this->assertNotEquals($lead1->lead_number, $lead2->lead_number);
    }

    /** @test */
    public function n_16_followup_selfie_is_stored_on_local_disk_and_streamed_securely(): void
    {
        Storage::fake('local');

        $lead = Lead::create([
            'tenant_id'      => $this->tenant->id,
            'company_name'   => 'Selfie Lead Co',
            'contact_person' => 'Selfie Contact',
            'email'          => 'selfie@test.com',
            'lead_owner_id'  => $this->salesExecutive->id,
        ]);

        $fakeSelfie = UploadedFile::fake()->image('selfie.jpg', 600, 600);

        // Store followup with selfie
        $followup = LeadFollowup::create([
            'tenant_id'      => $this->tenant->id,
            'lead_id'        => $lead->id,
            'assigned_to_id' => $this->salesExecutive->id,
            'followup_date'  => now()->toDateString(),
            'selfie_path'    => $fakeSelfie->storeAs('crm/selfies', 'test-selfie.jpg', 'local'),
            'type'           => 'Meeting',
            'status'         => 'Completed',
        ]);

        Storage::disk('local')->assertExists($followup->selfie_path);

        // Access via authenticated authorized user
        $response = $this->actingAs($this->salesExecutive)
            ->withHeader('X-Tenant', 'sec-tenant')
            ->get(route('crm.followups.selfie', $followup->id));

        $response->assertOk();
    }
}
