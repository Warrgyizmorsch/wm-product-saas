<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\ProjectReview;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPhase5LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Localization Workspace',
            'slug'   => 'loc-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Localization User',
            'email'     => 'loc@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-LOC-01',
            'name'         => 'Localization Project',
            'owner_id'     => $this->user->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
            'budget_amount'=> 10000.00,
            'budget_hours' => 100.00,
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->user->id,
            'project_role' => 'Manager',
            'is_active'    => true,
        ]);
    }

    /** @test */
    public function key_parity_exists_between_english_hindi_and_bulgarian(): void
    {
        $en = include resource_path('../lang/en/projects.php');
        $hi = include resource_path('../lang/hi/projects.php');
        $bg = include resource_path('../lang/bg/projects.php');

        $enKeys = array_keys($en);
        $hiKeys = array_keys($hi);
        $bgKeys = array_keys($bg);

        // Every EN key must exist in HI and BG
        $missingInHi = array_diff($enKeys, $hiKeys);
        $missingInBg = array_diff($enKeys, $bgKeys);

        $this->assertEmpty($missingInHi, 'Missing keys in Hindi: ' . implode(', ', $missingInHi));
        $this->assertEmpty($missingInBg, 'Missing keys in Bulgarian: ' . implode(', ', $missingInBg));
    }

    /** @test */
    public function placeholder_parity_exists_across_all_translations(): void
    {
        $en = include resource_path('../lang/en/projects.php');
        $hi = include resource_path('../lang/hi/projects.php');
        $bg = include resource_path('../lang/bg/projects.php');

        $extractPlaceholders = function ($value): array {
            if (! is_string($value)) {
                return [];
            }
            preg_match_all('/:([a-zA-Z0-9_]+)/', $value, $matches);
            sort($matches[1]);
            return $matches[1];
        };

        foreach ($en as $key => $enVal) {
            if (is_array($enVal)) {
                continue;
            }

            $enPlaceholders = $extractPlaceholders($enVal);
            $hiPlaceholders = $extractPlaceholders($hi[$key] ?? '');
            $bgPlaceholders = $extractPlaceholders($bg[$key] ?? '');

            $this->assertEquals(
                $enPlaceholders,
                $hiPlaceholders,
                "Placeholder mismatch in Hindi for key '{$key}': EN has [" . implode(', ', $enPlaceholders) . "] vs HI has [" . implode(', ', $hiPlaceholders) . "]"
            );

            $this->assertEquals(
                $enPlaceholders,
                $bgPlaceholders,
                "Placeholder mismatch in Bulgarian for key '{$key}': EN has [" . implode(', ', $enPlaceholders) . "] vs BG has [" . implode(', ', $bgPlaceholders) . "]"
            );
        }
    }

    /** @test */
    public function phase_5_keys_load_in_all_three_languages(): void
    {
        $testKeys = [
            'reviews_and_cr'        => ['Reviews & CR', 'समीक्षाएं और सीआर', 'Прегледи и CR'],
            'client_reviews_uat'    => ['Client Reviews & UAT', 'ग्राहक समीक्षाएं और यूएटी', 'Клиентски прегледи и UAT'],
            'initiate_review'       => ['Initiate Review', 'समीक्षा आरंभ करें', 'Стартиране на преглед'],
            'change_requests'       => ['Change Requests', 'परिवर्तन अनुरोध (सीआर)', 'Заявки за промяна (CR)'],
            'new_change_request'    => ['New Change Request', 'नया परिवर्तन अनुरोध', 'Нова заявка за промяна'],
            'standalone_cr'         => ['Standalone Scope Change (No Review)', 'स्वतंत्र दायरा परिवर्तन (कोई समीक्षा नहीं)', 'Самостоятелна промяна на обхвата (без преглед)'],
        ];

        foreach ($testKeys as $key => [$expectedEn, $expectedHi, $expectedBg]) {
            app()->setLocale('en');
            $this->assertEquals($expectedEn, __('projects.' . $key));

            app()->setLocale('hi');
            $this->assertEquals($expectedHi, __('projects.' . $key));

            app()->setLocale('bg');
            $this->assertEquals($expectedBg, __('projects.' . $key));
        }

        app()->setLocale('en');
    }

    /** @test */
    public function rendered_reviews_tab_contains_no_raw_placeholders(): void
    {
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Launch Prep',
            'status'      => Milestone::STATUS_COMPLETED,
            'start_date'  => now()->subDays(10),
            'due_date'    => now()->subDays(2),
        ]);

        $review = ProjectReview::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'reviewer_id'   => $this->user->id,
            'reviewer_name' => 'Client Jane',
            'review_date'   => now()->toDateString(),
            'status'        => ProjectReview::STATUS_PENDING,
            'comments'      => 'Ready for signoff',
            'created_by'    => $this->user->id,
        ]);

        ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-LOC-01-CR-001',
            'title'                => 'Localization Scope Add',
            'description'          => 'Add additional translations',
            'requested_by'         => $this->user->id,
            'impact_schedule_days' => 1,
            'impact_budget_amount' => 500.00,
            'impact_budget_hours'  => 4.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->user->id,
        ]);

        foreach (['en', 'hi', 'bg'] as $locale) {
            $response = $this->actingAs($this->user)
                ->withHeader('X-Tenant', 'loc-workspace')
                ->withSession(['locale' => $locale])
                ->get(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

            $response->assertOk();
            $content = $response->getContent();

            // Extract the reviews tab section
            $this->assertStringContainsString('tab-reviews', $content);

            // Assert no raw unreplaced translation placeholders like :active, :completed, :count, :user
            $this->assertDoesNotMatchRegularExpression(
                '/(?:>|")\s*:(?:user|project|number|title|approver|from|to|active|completed|done|progress|todo|hours|task|budget)\b/',
                $content,
                "Found raw translation placeholder in rendered HTML for locale '{$locale}'"
            );
        }
    }
}
