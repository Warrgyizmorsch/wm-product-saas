<?php

namespace Tests\Feature\Production;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionOperatorSkill;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityPlanAndSkillCrudTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id' => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role' => 'production_manager',
        ]);

        $this->seed(RbacSeeder::class);

        $productionManagerRole = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $productionManagerRole->id,
            'tenant_id' => $this->tenantId,
        ]);

        $this->actingAs($this->user);
    }

    /** @test */
    public function can_list_quality_plans_and_skills()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.quality-plans.index'));
        $response->assertOk();

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.operator-skills.index'));
        $response->assertOk();
    }

    /** @test */
    public function can_create_quality_plan_with_parameters()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.quality-plans.store'), [
                'name' => 'Final QA Checklist',
                'version' => '1.0',
                'type' => 'process',
                'status' => 'approved',
                'parameters' => [
                    [
                        'name' => 'Dimensions Calibration',
                        'type' => 'numeric',
                        'min_value' => '10.0',
                        'max_value' => '20.0',
                        'unit_of_measure' => 'mm',
                        'is_mandatory' => '1',
                    ]
                ]
            ]);

        $response->assertRedirect(route('production.quality-plans.index'));
        $this->assertDatabaseHas('production_quality_plans', [
            'tenant_id' => $this->tenantId,
            'name' => 'Final QA Checklist',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('production_quality_plan_parameters', [
            'tenant_id' => $this->tenantId,
            'name' => 'Dimensions Calibration',
            'type' => 'numeric',
            'min_value' => 10.0,
            'max_value' => 20.0,
        ]);
    }

    /** @test */
    public function can_create_operator_skill_mapping()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.operator-skills.store'), [
                'user_id' => $this->user->id,
                'skill_code' => 'SKL-CNC-EXPERT',
                'active' => true,
            ]);

        $response->assertRedirect(route('production.operator-skills.index'));
        $this->assertDatabaseHas('production_operator_skills', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->user->id,
            'skill_code' => 'SKL-CNC-EXPERT',
            'active' => true,
        ]);
    }
}
