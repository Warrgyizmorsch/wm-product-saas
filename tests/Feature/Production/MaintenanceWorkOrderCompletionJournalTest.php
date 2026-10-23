<?php

namespace Tests\Feature\Production;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\MaintenanceWorkOrderJournalService;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderAssignment;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MaintenanceSpareService;
use App\Domains\Production\Services\MaintenanceWorkOrderService;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MaintenanceWorkOrderCompletionJournalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Warehouse $warehouse;
    private Product $spareProductA;
    private Product $spareProductB;
    private MaintenanceWorkOrderService $woService;
    private MaintenanceSpareService $spareService;
    private MaintenanceWorkOrderJournalService $mwoJournalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();

        $this->tenant = Tenant::create([
            'name'   => 'Test Maintenance Tenant',
            'slug'   => 'test-maint-tenant-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->company = Company::create([
            'tenant_id'    => $this->tenant->id,
            'company_name' => 'Test Maintenance Company',
            'currency'     => 'INR',
        ]);

        \App\Models\Currency::firstOrCreate(
            ['code' => 'INR'],
            ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 2, 'is_active' => true]
        );

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Maintenance Admin',
            'email'     => 'maintadmin_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->actingAs($this->user);
        app(TenantContext::class)->setTenant($this->tenant);
        app(Tenancy::class)->setTenant($this->tenant);
        app()->instance('tenant', $this->tenant);

        // Seed accounting books for test tenant
        $this->seedAccountingChartOfAccounts($this->tenant->id);

        $this->workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'company_id'            => $this->company->id,
            'name'                  => 'Main Machining Center',
            'code'                  => 'WC-MC-01',
            'cost_per_hour'         => 60.00,
            'capacity_per_hour'     => 10.00,
            'efficiency_percentage' => 100.00,
            'status'                => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'          => $this->tenant->id,
            'company_id'         => $this->company->id,
            'work_center_id'     => $this->workCenter->id,
            'name'               => 'Lathe 01',
            'code'               => 'MCH-LTH-01',
            'status'             => Machine::STATUS_ACTIVE,
            'current_state'      => 'Idle',
            'maintenance_status' => 'none',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'company_id' => $this->company->id,
            'name'       => 'Central Spare Store',
            'code'       => 'WH-CSS-01',
        ]);

        $this->spareProductA = Product::create([
            'tenant_id'                  => $this->tenant->id,
            'name'                       => 'Bearing 6205',
            'sku'                        => 'SPR-BRG-6205',
            'cost_price'                 => 50.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->spareProductB = Product::create([
            'tenant_id'                  => $this->tenant->id,
            'name'                       => 'Oil Filter OF-10',
            'sku'                        => 'SPR-FLT-OF10',
            'cost_price'                 => 30.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        // Inflow stock into warehouse
        StockService::recordInflow(
            $this->tenant->id,
            $this->spareProductA->id,
            $this->warehouse->id,
            20.00,
            50.00,
            'Opening Stock'
        );

        StockService::recordInflow(
            $this->tenant->id,
            $this->spareProductB->id,
            $this->warehouse->id,
            20.00,
            30.00,
            'Opening Stock'
        );

        $this->woService = app(MaintenanceWorkOrderService::class);
        $this->spareService = app(MaintenanceSpareService::class);
        $this->mwoJournalService = app(MaintenanceWorkOrderJournalService::class);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function seedAccountingChartOfAccounts(int $tenantId): void
    {
        // 1000 Assets (Header)
        $assetHeader = ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '1000',
            'name'           => 'Assets',
            'type'           => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'system_key'     => 'assets',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // 1200 Inventory (Child postable)
        ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '1200',
            'name'           => 'Inventory',
            'type'           => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'parent_id'      => $assetHeader->id,
            'system_key'     => 'inventory',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // 2000 Liabilities (Header)
        $liabilityHeader = ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '2000',
            'name'           => 'Liabilities',
            'type'           => ChartOfAccount::TYPE_LIABILITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'system_key'     => 'liabilities',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // 2015 Creditors for Expenses (Child postable)
        ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '2015',
            'name'           => 'Creditors for Expenses',
            'type'           => ChartOfAccount::TYPE_LIABILITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'parent_id'      => $liabilityHeader->id,
            'system_key'     => 'creditors_for_expenses',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // 2070 Outstanding Expenses (Child postable)
        ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '2070',
            'name'           => 'Outstanding Expenses',
            'type'           => ChartOfAccount::TYPE_LIABILITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'parent_id'      => $liabilityHeader->id,
            'system_key'     => 'outstanding_expenses',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // 5000 Expenses (Header)
        $expenseHeader = ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '5000',
            'name'           => 'Expenses',
            'type'           => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'system_key'     => 'expenses',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // 5530 Repairs & Maintenance (Child postable)
        ChartOfAccount::create([
            'tenant_id'      => $tenantId,
            'code'           => '5530',
            'name'           => 'Repairs & Maintenance',
            'type'           => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'parent_id'      => $expenseHeader->id,
            'system_key'     => 'repairs_and_maintenance',
            'is_system'      => true,
            'is_active'      => true,
        ]);

        // Create Fiscal Year & Monthly Periods
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id'  => $tenantId,
            'name'       => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date'   => now()->endOfYear()->toDateString(),
        ]);
    }

    /** 1. Successful completion posts the correct balanced journal. */
    public function test_successful_completion_posts_correct_balanced_journal(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'company_id'          => $this->company->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Motor vibration and heating',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'External Mech John',
                    'hourly_rate'     => 100.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        // Issue 2 bearings (2 * 50 = 100.00)
        $spare = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->spareProductA->id,
            $this->warehouse->id,
            2.0
        );
        $this->spareService->issueSparePart($spare->id, $this->tenant->id, 2.0, $this->user->id);

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        // Complete work order with 2 hours for mechanic and additional cost of 50.00
        $completedWo = $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Replaced motor bearing and aligned coupling',
            laborHours: 0.0,
            mechanicType: null,
            externalMechanicCost: 0.0,
            internalMechanicCost: 0.0,
            checklistJson: null,
            scrapMachine: false,
            scrapValue: 0.0,
            decisionNote: null,
            completedAt: null,
            additionalCost: 50.00,
            externalPartsPurchased: false,
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 2.00,
                    'hourly_rate'  => 100.00,
                ],
            ]
        );

        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_COMPLETED, $completedWo->status);

        // Verify Journal was posted
        $journal = $completedWo->journal;
        $this->assertNotNull($journal, 'Journal should have been posted for completed work order.');
        $this->assertEquals(Journal::SOURCE_PRODUCTION, $journal->source);
        $this->assertEquals('maintenance_work_order', $journal->reference_type);
        $this->assertEquals($wo->id, $journal->reference_id);
        $this->assertEquals($this->tenant->id, $journal->tenant_id);
        $this->assertEquals($this->company->id, $journal->company_id);

        // Total eligible expense: Spares (100) + Mech (200) + Additional (50) = 350.00
        $this->assertEquals(350.00, (float) $journal->total_debit);
        $this->assertEquals(350.00, (float) $journal->total_credit);

        // Verify lines
        $entries = $journal->entries()->get();
        $this->assertCount(4, $entries);

        $repairEntry = $entries->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '5530')->value('id'));
        $this->assertNotNull($repairEntry);
        $this->assertEquals(350.00, (float) $repairEntry->debit);
        $this->assertEquals(0.00, (float) $repairEntry->credit);

        $invEntry = $entries->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '1200')->value('id'));
        $this->assertNotNull($invEntry);
        $this->assertEquals(0.00, (float) $invEntry->debit);
        $this->assertEquals(100.00, (float) $invEntry->credit);

        $creditorsEntry = $entries->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '2015')->value('id'));
        $this->assertNotNull($creditorsEntry);
        $this->assertEquals(0.00, (float) $creditorsEntry->debit);
        $this->assertEquals(200.00, (float) $creditorsEntry->credit);

        $outstandingEntry = $entries->firstWhere('chart_of_account_id', ChartOfAccount::where('code', '2070')->value('id'));
        $this->assertNotNull($outstandingEntry);
        $this->assertEquals(0.00, (float) $outstandingEntry->debit);
        $this->assertEquals(50.00, (float) $outstandingEntry->credit);
    }

    /** 2. Inventory credit uses existing account resolution logic. */
    public function test_inventory_credit_uses_existing_account_resolution_logic(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Inventory resolution check',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                ],
            ],
        ]);

        $spare = $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->spareProductB->id,
            $this->warehouse->id,
            3.0
        );
        $this->spareService->issueSparePart($spare->id, $this->tenant->id, 3.0, $this->user->id);

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Done');

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);

        $invAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '1200')->first();
        $invEntry = $journal->entries()->where('chart_of_account_id', $invAccount->id)->first();
        $this->assertNotNull($invEntry);
        $this->assertEquals(90.00, (float) $invEntry->credit); // 3 * 30.00
    }

    /** 3. External mechanic costs use finalized hours and hourly rates with hours_to_decimal. */
    public function test_external_mechanic_costs_use_finalized_hours_and_hourly_rates(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'company_id'          => $this->company->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Test external mechanic calculation',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Specialist Dave',
                    'hourly_rate'     => 120.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Finished',
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 1.50,
                    'hourly_rate'  => 120.00,
                ],
            ]
        );

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);

        // 1.5 hours * 120 = 180.00
        $creditorsAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '2015')->first();
        $creditorsEntry = $journal->entries()->where('chart_of_account_id', $creditorsAccount->id)->first();
        $this->assertNotNull($creditorsEntry);
        $this->assertEquals(180.00, (float) $creditorsEntry->credit);
        $this->assertStringContainsString('Specialist Dave', $creditorsEntry->description);
        $this->assertStringContainsString('Specialist Dave: ₹180', $journal->memo);
    }

    /** 4. Multiple external mechanics are handled correctly. */
    public function test_multiple_external_mechanics_are_handled_correctly(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'company_id'          => $this->company->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Two external mechanics',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Mech Alpha',
                    'hourly_rate'     => 100.00,
                ],
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Mech Beta',
                    'hourly_rate'     => 80.00,
                ],
            ],
        ]);

        $assignments = $wo->assignments()->get();
        $asnAlpha = $assignments->firstWhere('technician_name', 'Mech Alpha');
        $asnBeta = $assignments->firstWhere('technician_name', 'Mech Beta');

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Repairs complete',
            assignmentsData: [
                [
                    'id'           => $asnAlpha->id,
                    'worked_hours' => 2.00, // 200.00
                    'hourly_rate'  => 100.00,
                ],
                [
                    'id'           => $asnBeta->id,
                    'worked_hours' => 2.50, // 2.5 hrs * 80 = 200.00
                    'hourly_rate'  => 80.00,
                ],
            ]
        );

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);

        $creditorsAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '2015')->first();
        $mechEntries = $journal->entries()->where('chart_of_account_id', $creditorsAccount->id)->get();
        $this->assertCount(2, $mechEntries);

        $alphaEntry = $mechEntries->first(fn($e) => str_contains($e->description, 'Mech Alpha'));
        $this->assertNotNull($alphaEntry);
        $this->assertEquals(200.00, (float) $alphaEntry->credit);

        $betaEntry = $mechEntries->first(fn($e) => str_contains($e->description, 'Mech Beta'));
        $this->assertNotNull($betaEntry);
        $this->assertEquals(200.00, (float) $betaEntry->credit);

        // Narration contains both
        $this->assertStringContainsString('Mech Alpha: ₹200', $journal->memo);
        $this->assertStringContainsString('Mech Beta: ₹200', $journal->memo);
    }

    /** 5. Internal mechanic costs are not separately posted. */
    public function test_internal_mechanic_costs_are_not_separately_posted(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Internal technician only',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                    'hourly_rate'     => 50.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $completedWo = $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 4.00, // 200.00 internal cost
                    'hourly_rate'  => 50.00,
                ],
            ]
        );

        // Internal mechanic has cost in maintenance module
        $this->assertEquals(200.00, (float) $completedWo->mechanic_cost);

        // But GL journal has zero eligible cost -> NO journal created
        $journal = $completedWo->fresh()->journal;
        $this->assertNull($journal, 'Internal mechanic costs must not produce a journal entry.');
    }

    /** 6. Additional unpaid expenses credit 2070 Outstanding Expenses. */
    public function test_additional_unpaid_expenses_credit_outstanding_expenses(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Overhead expense test',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                ],
            ],
        ]);

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            additionalCost: 350.00
        );

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);

        $outstandingAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '2070')->first();
        $entry = $journal->entries()->where('chart_of_account_id', $outstandingAccount->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals(350.00, (float) $entry->credit);

        $repairAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '5530')->first();
        $debitEntry = $journal->entries()->where('chart_of_account_id', $repairAccount->id)->first();
        $this->assertEquals(350.00, (float) $debitEntry->debit);
    }

    /** 7. Paid additional expenses are not incorrectly credited if additional_cost is 0. */
    public function test_additional_cost_zero_does_not_credit_outstanding_expenses(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Zero additional cost test',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Mech Ext',
                    'hourly_rate'     => 100.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            additionalCost: 0.00,
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 1.00,
                    'hourly_rate'  => 100.00,
                ],
            ]
        );

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);

        $outstandingAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '2070')->first();
        $entry = $journal->entries()->where('chart_of_account_id', $outstandingAccount->id)->first();
        $this->assertNull($entry, 'Zero additional cost should not credit 2070.');
    }

    /** 8. Inventory-issued parts and externally purchased parts are not double counted; narration appended. */
    public function test_inventory_and_external_parts_narration_flag(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'External parts purchased flag test',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                ],
            ],
        ]);

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Used external gasket',
            additionalCost: 150.00,
            externalPartsPurchased: true
        );

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);
        $this->assertStringContainsString(
            'Additional/overhead expenses include externally supplied repair parts not issued from inventory.',
            $journal->memo
        );
    }

    /** 9. Requested but unissued spare parts are not included in journal. */
    public function test_requested_but_unissued_spare_parts_are_not_included(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Unissued spare parts check',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                ],
            ],
        ]);

        // Request 5 bearings, but DO NOT issue them
        $this->spareService->addSpareRequest(
            $wo->id,
            $this->tenant->id,
            $this->spareProductA->id,
            $this->warehouse->id,
            5.0
        );

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Done');

        $journal = $wo->fresh()->journal;
        $this->assertNull($journal, 'Unissued spares have 0 cost and should not post a journal.');
    }

    /** 10. A work order with zero eligible cost does not produce a zero-value journal. */
    public function test_zero_eligible_cost_does_not_produce_journal(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'No costs at all',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                ],
            ],
        ]);

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Inspected only');

        $this->assertDatabaseMissing('journals', [
            'reference_type' => 'maintenance_work_order',
            'reference_id'   => $wo->id,
        ]);
    }

    /** 11. Repeating completion does not produce a duplicate journal. */
    public function test_repeating_completion_does_not_produce_duplicate_journal(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Idempotency test',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Mech Rep',
                    'hourly_rate'     => 50.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $completed1 = $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 1.00,
                    'hourly_rate'  => 50.00,
                ],
            ]
        );
        $this->assertNotNull($completed1->journal);

        $initialJournalId = $completed1->journal->id;

        // Call completeWorkOrder again
        $completed2 = $this->woService->completeWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Done again');
        $this->assertEquals($initialJournalId, $completed2->journal->id);

        // Directly call postCompletionJournal again
        $retryJournal = $this->mwoJournalService->postCompletionJournal($wo);
        $this->assertEquals($initialJournalId, $retryJournal->id);

        $journalCount = Journal::where('reference_type', 'maintenance_work_order')
            ->where('reference_id', $wo->id)
            ->count();
        $this->assertEquals(1, $journalCount, 'Exactly one journal must exist for the work order.');
    }

    /** 12. Cancelled work orders do not receive completion journals. */
    public function test_cancelled_work_orders_do_not_receive_completion_journals(): void
    {
        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_PREVENTIVE,
            'problem_description' => 'Cancellation journal check',
            'assignments'         => [
                [
                    'assignment_type' => 'internal',
                    'technician_id'   => $this->user->id,
                    'technician_name' => $this->user->name,
                ],
            ],
        ]);

        $this->woService->cancelWorkOrder($wo->id, $this->tenant->id, $this->user->id, 'Cancelled');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot post a completion journal for a cancelled Work Order');

        $this->mwoJournalService->postCompletionJournal($wo->fresh());
    }

    /** 13. Missing, inactive, or group accounts fail with meaningful error and abort posting. */
    public function test_missing_or_inactive_account_throws_exception(): void
    {
        // Deactivate 5530
        $repAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '5530')->first();
        $repAccount->update(['is_active' => false]);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Inactive account check',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Mech Fail',
                    'hourly_rate'     => 100.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Account 'Repairs & Maintenance' (5530) for Repairs & Maintenance Expense is inactive.");

        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 1.00,
                    'hourly_rate'  => 100.00,
                ],
            ]
        );
    }

    /** 14. Group account cannot be posted directly to. */
    public function test_group_account_throws_exception(): void
    {
        // Create child under 5530 to turn 5530 into a group account
        $repAccount = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '5530')->first();
        ChartOfAccount::create([
            'tenant_id'      => $this->tenant->id,
            'code'           => '5531',
            'name'           => 'Sub Repairs',
            'type'           => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'parent_id'      => $repAccount->id,
            'is_system'      => false,
            'is_active'      => true,
        ]);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Group account check',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Mech Group',
                    'hourly_rate'     => 100.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot post directly to group account 'Repairs & Maintenance' (5530)");

        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 1.00,
                    'hourly_rate'  => 100.00,
                ],
            ]
        );
    }

    /** 15. Tenant scoping is strictly respected. */
    public function test_tenant_scoping_is_strictly_respected(): void
    {
        $otherTenant = Tenant::create([
            'name'   => 'Other Tenant',
            'slug'   => 'other-tenant-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'company_id'          => $this->company->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Tenant isolation check',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Isolated Mech',
                    'hourly_rate'     => 75.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);
        $this->woService->completeWorkOrder(
            $wo->id,
            $this->tenant->id,
            $this->user->id,
            'Done',
            assignmentsData: [
                [
                    'id'           => $asn->id,
                    'worked_hours' => 2.00,
                    'hourly_rate'  => 75.00,
                ],
            ]
        );

        $journal = $wo->fresh()->journal;
        $this->assertNotNull($journal);
        $this->assertEquals($this->tenant->id, $journal->tenant_id);

        // Verify other tenant cannot see this journal via their scope
        $otherJournals = Journal::withoutGlobalScopes()
            ->where('tenant_id', $otherTenant->id)
            ->where('reference_type', 'maintenance_work_order')
            ->get();
        $this->assertEmpty($otherJournals);
    }

    /** 16. Accounting validation failure rolls back the work order completion transaction. */
    public function test_accounting_failure_rolls_back_work_order_completion(): void
    {
        // Deactivate 2015 account to force an accounting validation failure
        $creditors = ChartOfAccount::where('tenant_id', $this->tenant->id)->where('code', '2015')->first();
        $creditors->update(['is_active' => false]);

        $wo = $this->woService->createWorkOrder($this->tenant->id, [
            'machine_id'          => $this->machine->id,
            'type'                => ProductionMaintenanceWorkOrder::TYPE_BREAKDOWN,
            'problem_description' => 'Atomic rollback check',
            'assignments'         => [
                [
                    'assignment_type' => 'external',
                    'technician_name' => 'Rollback Mech',
                    'hourly_rate'     => 100.00,
                ],
            ],
        ]);

        $asn = $wo->assignments()->first();

        $this->woService->startWorkOrder($wo->id, $this->tenant->id, $this->user->id);

        try {
            $this->woService->completeWorkOrder(
                $wo->id,
                $this->tenant->id,
                $this->user->id,
                'Done',
                assignmentsData: [
                    [
                        'id'           => $asn->id,
                        'worked_hours' => 1.00,
                        'hourly_rate'  => 100.00,
                    ],
                ]
            );
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Creditors for Expenses', $e->getMessage());
        }

        // Work order must remain in_progress (rolled back)
        $wo->refresh();
        $this->assertEquals(ProductionMaintenanceWorkOrder::STATUS_IN_PROGRESS, $wo->status);
        $this->assertNull($wo->completed_by);
        $this->assertNull($wo->actual_end);

        // No journals posted
        $this->assertDatabaseMissing('journals', [
            'reference_type' => 'maintenance_work_order',
            'reference_id'   => $wo->id,
        ]);
    }
}
