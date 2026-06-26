<?php

use App\Core\Tenant\TenantContext;
use App\Models\Tenant;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return app(TenantContext::class)->id();
    }
}

if (! function_exists('tenant_context')) {
    function tenant_context(): TenantContext
    {
        return app(TenantContext::class);
    }
}
