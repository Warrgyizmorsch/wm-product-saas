<?php

namespace Tests\Feature\Production;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Models\SalaryStructureItem;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class MaintenanceHourlyRateAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch;
    private User $adminUser;
    private Machine $machine;
    private MaintenanceWorkOrderService $woService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();

        // Standardize currency configuration for deterministic testing
        Config::set('currency.base', 'USD');
        Config::set('currency.active', 'INR');
        Config::set('currency.currencies.USD.rate', 1.0);
        Config::set('currency.currencies.INR.rate', 80.0);
        Config::set('currency.currencies.INR.symbol', '₹');
        Config::set('production.maintenance_working_hours_per_month', 208.0);

        $this->tenant = Tenant::create([
            'name'   => 'Currency Test Tenant',
            'slug'   => 'test-currency-tenant-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->company = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Test Company',
            'currency'     => 'INR',
            'status'       => true,
        ]);

        $this->branch = Branch::create([
            'tenant_id'   => $this->tenant->id,
            'company_id'  => $this->company->id,
            'name'        => 'Main Branch',
            'code'        => 'BR01',
            'status'      => true,
        ]);

        $this->adminUser = User::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'name'       => 'Admin User',
            'email'      => 'admin_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'role'       => 'admin',
        ]);

        $this->actingAs($this->adminUser);
        $this->withHeaders(['X-Tenant' => $this->tenant->slug]);
        $this->withSession([
            'tenant_slug'     => $this->tenant->slug,
            'company_id'      => $this->company->id,
            'branch_id'       => $this->branch->id,
            'active_currency' => 'INR',
        ]);

        app(TenantContext::class)->setTenant($this->tenant);
        app(Tenancy::class)->setTenant($this->tenant);
        app()->instance('tenant', $this->tenant);
        app(\App\Core\Company\CompanyContext::class)->set($this->company);
        app(\App\Core\Branch\BranchContext::class)->set($this->branch);

        Gate::before(fn () => true);

        $workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Test Work Center',
            'code'                  => 'WC-01',
            'cost_per_hour'         => 50.00,
            'capacity_per_hour'     => 10.00,
            'efficiency_percentage' => 100.00,
            'status'                => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'          => $this->tenant->id,
            'company_id'         => $this->company->id,
            'branch_id'          => $this->branch->id,
            'work_center_id'     => $workCenter->id,
            'name'               => 'Test Milling Machine',
            'code'               => 'MCH-01',
            'status'             => Machine::STATUS_ACTIVE,
            'current_state'      => 'Idle',
            'maintenance_status' => 'none',
        ]);

        $this->woService = app(MaintenanceWorkOrderService::class);
    }

    /**
     * Helper to set up a technician user with employee record and salary structure.
     */
    private function createTechnicianWithSalary(
        float $annualCtc,
        string $calcType,
        float $componentValue,
        string $componentCode = 'BASIC'
    ): User {
        $user = User::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'name'       => 'Vikram Technician',
            'email'      => 'tech_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'role'       => 'technician',
        ]);

        $payGroup = PayGroup::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company->id,
            'name'       => 'Standard Tech Group',
        ]);

        $structure = SalaryStructure::create([
            'tenant_id'    => $this->tenant->id,
            'company_id'   => $this->company->id,
            'pay_group_id' => $payGroup->id,
            'name'         => 'Tech Salary Structure',
            'status'       => true,
            'min_ctc'      => 100000,
            'max_ctc'      => 5000000,
        ]);

        $component = SalaryComponent::create([
            'tenant_id'        => $this->tenant->id,
            'company_id'       => $this->company->id,
            'name'             => 'Basic Salary',
            'code'             => $componentCode,
            'type'             => 'earning',
            'calculation_type' => $calcType,
            'status'           => true,
        ]);

        SalaryStructureItem::create([
            'tenant_id'           => $this->tenant->id,
            'salary_structure_id' => $structure->id,
            'salary_component_id' => $component->id,
            'calculation_type'    => $calcType,
            'value'               => $componentValue,
            'status'              => true,
        ]);

        Employee::create([
            'tenant_id'           => $this->tenant->id,
            'user_id'             => $user->id,
            'company_id'          => $this->company->id,
            'branch_id'           => $this->branch->id,
            'employee_id'         => 'EMP-' . uniqid(),
            'full_name'           => $user->name,
            'gender'              => 'male',
            'date_of_joining'     => '2025-01-01',
            'pay_group_id'        => $payGroup->id,
            'salary_structure_id' => $structure->id,
            'current_salary'      => $annualCtc,
        ]);

        return $user->fresh();
    }

    public function test_percentage_of_ctc_basic_component_derives_correct_monetary_hourly_rate(): void
    {
        // Vikram: Annual CTC = 1,800,000 INR. Basic = 50% of CTC.
        // Annual Basic = 900,000 INR. Monthly Basic = 75,000 INR.
        // Working hours = 208. Hourly rate in INR = 75,000 / 208 = 360.5769... => 360.58 INR.
        $tech = $this->createTechnicianWithSalary(1800000.00, 'percentage_of_ctc', 50.00);

        // When requesting rate in INR:
        $rateInr = $this->woService->calculateTechnicianHourlyRate($tech, 'INR');
        $this->assertEquals(360.58, $rateInr);

        // When requesting rate in base USD (rate 80.0):
        // 360.5769 / 80 = 4.5072 => 4.51 USD
        $rateUsd = $this->woService->calculateTechnicianHourlyRate($tech, 'USD');
        $this->assertEquals(4.51, $rateUsd);
    }

    public function test_fixed_basic_component_derives_correct_hourly_rate(): void
    {
        // Technician with Annual CTC 600,000 INR and fixed basic item of 300,000 (annual)
        // Monthly Basic = 300,000 / 12 = 25,000 INR.
        // Hourly rate in INR = 25,000 / 208 = 120.19 INR.
        $tech = $this->createTechnicianWithSalary(600000.00, 'fixed', 300000.00);

        $rateInr = $this->woService->calculateTechnicianHourlyRate($tech, 'INR');
        $this->assertEquals(120.19, $rateInr);
    }

    public function test_missing_employee_or_salary_structure_safely_returns_zero(): void
    {
        // User with no employee profile
        $userWithoutEmp = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Ghost User',
            'email'     => 'ghost_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
        ]);

        $rate = $this->woService->calculateTechnicianHourlyRate($userWithoutEmp);
        $this->assertEquals(0.00, $rate);

        // Null user
        $rateNull = $this->woService->calculateTechnicianHourlyRate(null);
        $this->assertEquals(0.00, $rateNull);
    }

    public function test_configured_working_hours_per_month_is_respected(): void
    {
        // Custom working hours: 160 hours/month (40 hrs/wk * 4 wks)
        Config::set('production.maintenance_working_hours_per_month', 160.0);

        // Annual CTC = 1,200,000 INR. 50% basic = 600,000/yr => 50,000/mo.
        // Hourly = 50,000 / 160 = 312.50 INR.
        $tech = $this->createTechnicianWithSalary(1200000.00, 'percentage_of_ctc', 50.00);

        $rate = $this->woService->calculateTechnicianHourlyRate($tech, 'INR');
        $this->assertEquals(312.50, $rate);
    }

    public function test_create_view_displays_calculated_hourly_rate_and_active_currency(): void
    {
        $tech = $this->createTechnicianWithSalary(1800000.00, 'percentage_of_ctc', 50.00);

        $response = $this->get(route('production.maintenance.work-orders.create'));

        $response->assertStatus(200);
        // Should contain data-rate="360.58" and active currency symbol "₹"
        $response->assertSee('data-rate="360.58"', false);
        $response->assertSee('₹', false);
    }

    public function test_work_order_store_normalizes_incoming_active_currency_rates_to_base(): void
    {
        // Form submitted in active currency (INR = 80.0 per USD)
        // User enters hourly rate = 400.00 INR
        // Stored rate in DB must be normalized to base USD: 400.00 / 80.0 = 5.00 USD
        $response = $this->post(route('production.maintenance.work-orders.store'), [
            'machine_id'          => $this->machine->id,
            'type'                => 'breakdown',
            'priority'            => 'high',
            'problem_description' => 'Motor vibration',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->adminUser->id,
                    'technician_name' => $this->adminUser->name,
                    'hourly_rate'     => 400.00, // 400 INR
                ],
            ],
        ]);

        $response->assertRedirect();

        $wo = ProductionMaintenanceWorkOrder::where('machine_id', $this->machine->id)->first();
        $this->assertNotNull($wo);

        $assignment = $wo->assignments()->first();
        $this->assertNotNull($assignment);
        // Stored rate should be 5.00 USD in base currency
        $this->assertEquals(5.00, (float) $assignment->hourly_rate);
    }

    public function test_add_assignment_normalizes_hourly_rate_to_base_currency(): void
    {
        $wo = ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $this->tenant->id,
            'company_id'          => $this->company->id,
            'branch_id'           => $this->branch->id,
            'work_order_number'   => 'WO-TEST-001',
            'machine_id'          => $this->machine->id,
            'type'                => 'preventive',
            'priority'            => 'medium',
            'status'              => 'in_progress',
        ]);

        // Post new assignment with rate 800.00 INR (should be 10.00 USD)
        $response = $this->post(route('production.maintenance.work-orders.add-assignment', $wo->id), [
            'assignments' => [
                [
                    'assignment_type'     => 'external',
                    'technician_name'     => 'Expert Contractor',
                    'expected_work_hours' => 4.0,
                    'hourly_rate'         => 800.00,
                ],
            ],
        ]);

        $response->assertRedirect();

        $assignment = $wo->assignments()->first();
        $this->assertNotNull($assignment);
        $this->assertEquals(10.00, (float) $assignment->hourly_rate);
    }

    public function test_complete_work_order_normalizes_costs_without_double_conversion(): void
    {
        $wo = ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $this->tenant->id,
            'company_id'          => $this->company->id,
            'branch_id'           => $this->branch->id,
            'work_order_number'   => 'WO-TEST-002',
            'machine_id'          => $this->machine->id,
            'type'                => 'breakdown',
            'priority'            => 'high',
            'status'              => 'in_progress',
            'actual_start'        => now()->subHours(2),
        ]);

        // Pre-existing assignment with base stored rate = 10.00 USD (e.g. 800 INR)
        $assignment = ProductionMaintenanceWorkOrderAssignment::create([
            'tenant_id'       => $this->tenant->id,
            'work_order_id'   => $wo->id,
            'assignment_type' => 'internal',
            'technician_id'   => $this->adminUser->id,
            'technician_name' => $this->adminUser->name,
            'hourly_rate'     => 10.00, // stored in base USD
            'worked_hours'    => 2.0,
            'assigned_at'     => now()->subHours(2),
        ]);

        // In complete modal, user updates worked hours and sends rate in INR (800.00 INR = 10.00 USD)
        // User also specifies additional_cost in INR (1600.00 INR = 20.00 USD)
        $response = $this->post(route('production.maintenance.work-orders.complete', $wo->id), [
            'completion_action' => 'restore',
            'work_performed'    => 'Replaced bearings and aligned motor',
            'additional_cost'   => 1600.00, // 1600 INR => 20.00 USD
            'assignments'       => [
                [
                    'id'           => $assignment->id,
                    'worked_hours' => 2.5,
                    'hourly_rate'  => 800.00, // 800 INR => 10.00 USD
                ],
            ],
        ]);

        $response->assertRedirect();

        $completedWo = $wo->fresh();
        $this->assertEquals('completed', $completedWo->status);

        // Assignment rate in base should be 10.00 USD
        $freshAssignment = $assignment->fresh();
        $this->assertEquals(10.00, (float) $freshAssignment->hourly_rate);
        $this->assertEquals(2.5, (float) $freshAssignment->worked_hours);

        // Mechanic cost in base: 2.5 hrs * $10.00/hr = $25.00
        $this->assertEquals(25.00, (float) $completedWo->mechanic_cost);

        // Additional cost in base: 1600 INR / 80 = $20.00
        $this->assertEquals(20.00, (float) $completedWo->additional_cost);

        // Total cost in base: $25.00 + $20.00 = $45.00
        $this->assertEquals(45.00, (float) $completedWo->total_cost);
    }
}
