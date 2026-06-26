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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
