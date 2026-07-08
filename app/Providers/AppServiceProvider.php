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
    }

    public function boot(): void
    {
        // ── Production Policies ───────────────────────────────────────────────
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
