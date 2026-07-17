<?php

namespace App\Providers;

use App\Core\Tenant\TenantContext;
use App\Support\Tenancy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);
        $this->app->singleton(TenantContext::class, fn ($app) => $app->make(Tenancy::class));

        \Illuminate\Support\Facades\Auth::provider('tenant-eloquent', function ($app, array $config) {
            return new \App\Support\Auth\TenantAwareUserProvider($app['hash'], $config['model']);
        });

        // ── Production: BOM (Frozen) ──────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Production\Repositories\ProductionBomRepositoryInterface::class,
            \App\Domains\Production\Repositories\ProductionBomRepository::class
        );

        // ── Production: Work Center ───────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Production\Repositories\WorkCenterRepositoryInterface::class,
            \App\Domains\Production\Repositories\WorkCenterRepository::class
        );

        // ── Production: Machine ───────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Production\Repositories\MachineRepositoryInterface::class,
            \App\Domains\Production\Repositories\MachineRepository::class
        );

        // ── Production: Routing ───────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Production\Repositories\RoutingRepositoryInterface::class,
            \App\Domains\Production\Repositories\RoutingRepository::class
        );

        // ── Projects: Project ─────────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\ProjectRepositoryInterface::class,
            \App\Domains\Projects\Repositories\ProjectRepository::class
        );

        // ── Projects: Activity Log ────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\ActivityLogRepositoryInterface::class,
            \App\Domains\Projects\Repositories\ActivityLogRepository::class
        );
            // ── Production: KPI Targets ───────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Production\Repositories\KpiTargetRepositoryInterface::class,
            \App\Domains\Production\Repositories\KpiTargetRepository::class
        );

        // ── Projects: Project Member ──────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\ProjectMemberRepositoryInterface::class,
            \App\Domains\Projects\Repositories\ProjectMemberRepository::class
        );

        // ── Projects: Milestone ───────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\MilestoneRepositoryInterface::class,
            \App\Domains\Projects\Repositories\MilestoneRepository::class
        );

        // ── Projects: Task List ───────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\TaskListRepositoryInterface::class,
            \App\Domains\Projects\Repositories\TaskListRepository::class
        );

        // ── Projects: Task ─────────────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\TaskRepositoryInterface::class,
            \App\Domains\Projects\Repositories\TaskRepository::class
        );

        // ── Projects: Sub Task ────────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\SubTaskRepositoryInterface::class,
            \App\Domains\Projects\Repositories\SubTaskRepository::class
        );

        // ── Projects: Task Dependency ─────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Projects\Repositories\TaskDependencyRepositoryInterface::class,
            \App\Domains\Projects\Repositories\TaskDependencyRepository::class
        );

        // ── Accounting: Chart of Accounts ─────────────────────────────────────
        $this->app->bind(
            \App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface::class,
            \App\Domains\Accounting\Repositories\ChartOfAccountRepository::class
        );

        // ── Accounting: Fiscal Year ────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Accounting\Repositories\FiscalYearRepositoryInterface::class,
            \App\Domains\Accounting\Repositories\FiscalYearRepository::class
        );

        // ── Accounting: Accounting Period ─────────────────────────────────────
        $this->app->bind(
            \App\Domains\Accounting\Repositories\AccountingPeriodRepositoryInterface::class,
            \App\Domains\Accounting\Repositories\AccountingPeriodRepository::class
        );

        // ── Accounting: Journal ────────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Accounting\Repositories\JournalRepositoryInterface::class,
            \App\Domains\Accounting\Repositories\JournalRepository::class
        );

        // ── Accounting: Tax Rate ───────────────────────────────────────────────
        $this->app->bind(
            \App\Domains\Accounting\Repositories\TaxRateRepositoryInterface::class,
            \App\Domains\Accounting\Repositories\TaxRateRepository::class
        );
    }

    public function boot(): void
    {
        // ── Cross-module morph map (Journal.reference_type/reference_id) ───────
        // Journal::reference() is a real morphTo(); these short, stable keys are
        // what gets stored in reference_type, never the FQCN. Using morphMap()
        // (additive) rather than enforceMorphMap() deliberately — the latter
        // requires every morphTo() relation app-wide to resolve through the map,
        // which breaks unrelated existing polymorphic relations (e.g. Projects'
        // ActivityLog, HRMS's Document) that still store raw FQCNs.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'invoice' => \App\Domains\Sales\Models\Invoice::class,
            'customer_payment' => \App\Domains\Sales\Models\CustomerPayment::class,
            'delivery_order' => \App\Domains\Sales\Models\DeliveryOrder::class,
        ]);

        // ── Domain Event Listeners ───────────────────────────────────────────
        // Registered explicitly (not via discoverEventsWithin()/EventServiceProvider
        // subclassing, which silently stops auto-discovering listeners outside
        // app/Listeners) so every cross-module wire is auditable from this one file.
        \Illuminate\Support\Facades\Event::listen(
            \App\Domains\Production\Events\BomApproved::class,
            \App\Domains\Production\Listeners\CalculateBomCost::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domains\Sales\Events\InvoicePosted::class,
            \App\Domains\Accounting\Listeners\PostInvoiceJournal::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domains\Sales\Events\CustomerPaymentReceived::class,
            \App\Domains\Accounting\Listeners\PostCustomerPaymentJournal::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domains\Inventory\Events\StockOutflowRecorded::class,
            \App\Domains\Accounting\Listeners\PostCogsJournal::class
        );

        // ── Production Policies ───────────────────────────────────────────────
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionKpiTarget::class,
            \App\Domains\Production\Policies\KpiTargetPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionBom::class,
            \App\Domains\Production\Policies\ProductionBomPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\WorkCenter::class,
            \App\Domains\Production\Policies\WorkCenterPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\Machine::class,
            \App\Domains\Production\Policies\MachinePolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\Routing::class,
            \App\Domains\Production\Policies\RoutingPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionPlan::class,
            \App\Domains\Production\Policies\ProductionPlanPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionOrder::class,
            \App\Domains\Production\Policies\ProductionOrderPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionOperatorAssignment::class,
            \App\Domains\Production\Policies\AdvancedMesPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionSchedule::class,
            \App\Domains\Production\Policies\ProductionSchedulePolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionBatch::class,
            \App\Domains\Production\Policies\AdvancedMesPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionSerialNumber::class,
            \App\Domains\Production\Policies\AdvancedMesPolicy::class
        );

        // Quality Management Policies
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionQualityPlan::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionQualityInspection::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionNcr::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionCapa::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionReworkOrder::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionScrapDisposal::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Production\Models\ProductionDeviation::class,
            \App\Domains\Production\Policies\QualityManagementPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\CRM\Models\Lead::class,
            \App\Domains\CRM\Policies\LeadPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\CRM\Models\Customer::class,
            \App\Domains\CRM\Policies\CustomerPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\CRM\Models\Quotation::class,
            \App\Domains\CRM\Policies\QuotationPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Tenant::class,
            \App\Domains\Platform\Policies\TenantPolicy::class
        );

        // ── Inventory Policies ────────────────────────────────────────────────
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Inventory\Models\Product::class,
            \App\Domains\Inventory\Policies\ProductPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Inventory\Models\Warehouse::class,
            \App\Domains\Inventory\Policies\WarehousePolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Inventory\Models\Uom::class,
            \App\Domains\Inventory\Policies\UomPolicy::class
        );

        // ── Sales Policies ─────────────────────────────────────────────────────
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Sales\Models\SalesOrder::class,
            \App\Domains\Sales\Policies\SalesOrderPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Sales\Models\DeliveryOrder::class,
            \App\Domains\Sales\Policies\DeliveryOrderPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Sales\Models\DispatchOrder::class,
            \App\Domains\Sales\Policies\DispatchOrderPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Sales\Models\Invoice::class,
            \App\Domains\Sales\Policies\InvoicePolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Sales\Models\CustomerPayment::class,
            \App\Domains\Sales\Policies\CustomerPaymentPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Sales\Models\SalesReturn::class,
            \App\Domains\Sales\Policies\SalesReturnPolicy::class
        );

        // ── Projects Policies ─────────────────────────────────────────────────
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Projects\Models\Project::class,
            \App\Domains\Projects\Policies\ProjectPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Projects\Models\ProjectMember::class,
            \App\Domains\Projects\Policies\ProjectMemberPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Projects\Models\Milestone::class,
            \App\Domains\Projects\Policies\MilestonePolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Projects\Models\TaskList::class,
            \App\Domains\Projects\Policies\TaskListPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Projects\Models\Task::class,
            \App\Domains\Projects\Policies\TaskPolicy::class
        );

        // ── Accounting Policies ────────────────────────────────────────────────
        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Accounting\Models\ChartOfAccount::class,
            \App\Domains\Accounting\Policies\ChartOfAccountPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Accounting\Models\FiscalYear::class,
            \App\Domains\Accounting\Policies\FiscalYearPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Accounting\Models\AccountingPeriod::class,
            \App\Domains\Accounting\Policies\AccountingPeriodPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Accounting\Models\Journal::class,
            \App\Domains\Accounting\Policies\JournalPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Domains\Accounting\Models\TaxRate::class,
            \App\Domains\Accounting\Policies\TaxRatePolicy::class
        );

        // ── Access (RBAC admin) Policies ──────────────────────────────────────
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\User::class,
            \App\Domains\Access\Policies\UserPolicy::class
        );

        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\Access\Role::class,
            \App\Domains\Access\Policies\RolePolicy::class
        );
    }
}
