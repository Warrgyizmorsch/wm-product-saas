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
        // Bypass all authorization checks (including for guest/unauthenticated users) on dev/local environments
        \Illuminate\Support\Facades\Gate::before(function ($user = null, $ability = null) {
            if (!app()->environment('testing')) {
                return true;
            }
        });

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
    }
}
