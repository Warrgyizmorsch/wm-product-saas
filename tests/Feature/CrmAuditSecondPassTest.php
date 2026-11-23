<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Services\CrmFollowupReminderService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for the CRM QA / security / load audit (second pass, 2026-11-17).
 */
class CrmAuditSecondPassTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $owner;
    private User $salesExecutive;
    private User $otherTenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active', 'plan' => 'enterprise']);
        $this->otherTenant = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'plan' => 'enterprise']);

        $this->seed(RbacSeeder::class);

        $this->owner = $this->userWithRole($this->tenant, 'owner@a.test', 'tenant_owner');
        $this->salesExecutive = $this->userWithRole($this->tenant, 'exec@a.test', 'sales_executive');
        $this->otherTenantUser = $this->userWithRole($this->otherTenant, 'exec@b.test', 'sales_executive');
    }

    private function userWithRole(Tenant $tenant, string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
            'tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withHeader('X-Tenant', $user->tenant_id === $this->tenant->id ? 'tenant-a' : 'tenant-b');
    }

    // N-01 ---------------------------------------------------------------

    public function test_pdf_rendering_never_enables_php(): void
    {
        $this->assertFalse(config('dompdf.options.enable_php'));

        $templates = array_merge(
            glob(resource_path('views/modules/*/*/pdf.blade.php')) ?: [],
            glob(resource_path('views/modules/*/*/*/pdf.blade.php')) ?: []
        );
        $this->assertNotEmpty($templates);
        foreach ($templates as $file) {
            $this->assertStringNotContainsString('text/php', file_get_contents($file), $file);
        }

        foreach (glob(app_path('Domains/*/Controllers/{,Api/}*.php'), GLOB_BRACE) as $file) {
            $this->assertStringNotContainsString('isPhpEnabled', file_get_contents($file), $file);
        }
    }

    // BUG-01 -------------------------------------------------------------

    public function test_rich_text_sanitizer_keeps_formatting_and_drops_scripts(): void
    {
        $clean = HtmlSanitizer::clean('<b>ok</b><img src=x onerror=alert(1)><script>alert(2)</script>'
            . '<script type="text/php">touch("pwn");</script><a href="javascript:alert(3)">x</a><p>₹ text</p>');

        $this->assertStringContainsString('<b>ok</b>', $clean);
        $this->assertStringContainsString('<p>₹ text</p>', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('touch(', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_quotation_terms_are_sanitized_on_write_and_on_read(): void
    {
        $quotation = new Quotation();
        $quotation->terms_conditions = '<p>Net 30</p><script>alert(1)</script>';
        $this->assertSame('<p>Net 30</p>', $quotation->getAttributes()['terms_conditions']);

        // A row stored before sanitizing existed is cleaned when read.
        $legacy = (new Quotation())->setRawAttributes(['terms_conditions' => '<i>x</i><img src=x onerror=alert(1)>']);
        $this->assertSame('<i>x</i>', $legacy->terms_conditions);
    }

    // N-10 / N-11 --------------------------------------------------------

    public function test_sales_executive_cannot_change_crm_settings_but_owner_can(): void
    {
        $this->as($this->salesExecutive)
            ->post(route('crm.settings.update-quotation-approval-policy'), ['quotation_approval_policy' => 'auto_approve'])
            ->assertForbidden();
        $this->assertArrayNotHasKey('quotation_approval_policy', (array) $this->tenant->fresh()->settings);

        $this->as($this->owner)
            ->post(route('crm.settings.update-quotation-approval-policy'), ['quotation_approval_policy' => 'auto_approve'])
            ->assertRedirect();
        $this->assertSame('auto_approve', $this->tenant->fresh()->settings['quotation_approval_policy'] ?? null);
    }

    public function test_sales_executive_cannot_create_lead_statuses(): void
    {
        $this->as($this->salesExecutive)
            ->post(route('crm.masters.lead-statuses.store'), ['name' => 'Audit Status'])
            ->assertForbidden();

        $this->assertFalse(DB::table('lead_statuses')->where('name', 'Audit Status')->exists());
    }

    // N-14 ---------------------------------------------------------------

    public function test_follow_up_cannot_tag_a_user_from_another_tenant(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'lead_owner_id' => $this->owner->id,
            'company_name' => 'Tagged Co',
            'call_date' => now(),
        ]);

        $this->as($this->owner)
            ->postJson(route('crm.leads.followups.store', $lead), [
                'type' => 'Call',
                'followup_date' => now()->addHour()->toDateTimeString(),
                'tagged_user_ids' => [$this->otherTenantUser->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tagged_user_ids.0']);
    }

    public function test_reminders_never_notify_users_of_another_tenant(): void
    {
        $lead = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'lead_owner_id' => $this->owner->id,
            'company_name' => 'Reminder Co',
            'call_date' => now(),
        ]);

        $followup = LeadFollowup::withoutGlobalScopes()->forceCreate([
            'tenant_id' => $this->tenant->id,
            'lead_id' => $lead->id,
            'type' => 'Call',
            'status' => 'Pending',
            'followup_date' => now()->addMinutes(5),
            'assigned_to_id' => $this->otherTenantUser->id,
            'tagged_user_ids' => [$this->otherTenantUser->id],
        ]);

        $method = new \ReflectionMethod(CrmFollowupReminderService::class, 'resolveTargetUserIds');
        $ids = $method->invoke(app(CrmFollowupReminderService::class), $this->owner->id, $followup);

        $this->assertSame([$this->owner->id], $ids);
    }

    // BUG-04 -------------------------------------------------------------

    public function test_lead_numbers_are_not_reused_after_a_soft_delete(): void
    {
        $this->actingAs($this->owner);
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        $first = Lead::create(['tenant_id' => $this->tenant->id, 'company_name' => 'One', 'call_date' => now()]);
        $second = Lead::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Two', 'call_date' => now()]);
        $first->delete();
        $third = Lead::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Three', 'call_date' => now()]);

        $numbers = [$first->lead_number, $second->lead_number, $third->lead_number];
        $this->assertCount(3, array_unique($numbers));
    }

    // N-25 ---------------------------------------------------------------

    public function test_deals_kanban_caps_cards_per_column_but_reports_full_count(): void
    {
        foreach (range(1, 55) as $i) {
            CrmDeal::withoutGlobalScopes()->forceCreate([
                'tenant_id' => $this->tenant->id,
                'deal_number' => 'DL-T-' . $i,
                'title' => "Deal {$i}",
                'stage' => 'Qualification',
                'estimated_value' => 100,
                'owner_id' => $this->owner->id,
            ]);
        }

        $response = $this->as($this->owner)->get(route('crm.deals.kanban'))->assertOk();
        $column = $response->viewData('kanbanData')['Qualification'];

        $this->assertCount(50, $column['deals']);
        $this->assertSame(55, $column['count']);
        $this->assertEquals(5500, $column['total']);
    }

    // N-15 ---------------------------------------------------------------

    public function test_public_form_ignores_numeric_ids_and_foreign_company(): void
    {
        // A company ID the client picks (here one no tenant owns) must never be stored.
        $foreignCompanyId = 987654;

        $this->postJson('/forms/lead-capture', ['tenant' => (string) $this->tenant->id, 'name' => 'X', 'email' => 'x@x.test'])
            ->assertNotFound();

        $this->postJson('/forms/lead-capture', [
            'tenant' => 'tenant-a',
            'name' => 'Public Lead',
            'email' => 'public@x.test',
            'company_id' => $foreignCompanyId,
        ])->assertSuccessful();

        $lead = Lead::withoutGlobalScopes()->where('email', 'public@x.test')->firstOrFail();
        $this->assertSame($this->tenant->id, (int) $lead->tenant_id);
        $this->assertNotSame($foreignCompanyId, (int) $lead->company_id);
    }

    // N-17 / BUG-03 ------------------------------------------------------

    public function test_unsigned_webhooks_are_rejected(): void
    {
        $this->postJson('/webhooks/meta-leadgen', ['entry' => [['id' => '1', 'changes' => [['field' => 'leadgen', 'value' => ['leadgen_id' => '9']]]]]])
            ->assertForbidden();
        $this->assertSame(0, DB::table('meta_lead_logs')->count());

        $this->post('/api/crm/webhooks/twilio/voice', ['AccountSid' => 'AC123', 'To' => '+910000000000'])
            ->assertForbidden();
    }

    // N-19 ---------------------------------------------------------------

    public function test_permission_lookups_are_memoised_within_a_request(): void
    {
        $access = app(\App\Services\Access\AccessService::class);
        $context = ['tenant_id' => $this->tenant->id];

        $access->allows($this->salesExecutive, 'crm.leads.create', $context); // warm up

        DB::enableQueryLog();
        foreach (range(1, 25) as $_) {
            $this->assertTrue($access->allows($this->salesExecutive, 'crm.leads.create', $context));
            $this->assertFalse($access->allows($this->salesExecutive, 'crm.settings.manage', $context));
        }
        $rbacQueries = collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/role_permissions|user_roles|permissions|user_permission_overrides/', $q['query']));
        DB::disableQueryLog();

        $this->assertSame(0, $rbacQueries->count());
        // Another user in the same request still gets their own answer.
        $this->assertTrue($access->allows($this->owner, 'crm.settings.manage', $context));
    }
}
