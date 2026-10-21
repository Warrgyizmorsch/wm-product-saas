<?php

return [
    'header' => env('TENANT_HEADER', 'X-Tenant'),

    // Comma-separated tenant slugs the seeders should limit themselves to (SEED_TENANT=acme,beta).
    // Empty = every tenant. Lets you seed one tenant without touching the others.
    'seed_only' => array_filter(array_map('trim', explode(',', (string) env('SEED_TENANT', '')))),

    'local_fallback_slug' => env('TENANT_LOCAL_FALLBACK_SLUG', 'warrgyizmorsch'),

    /*
    | Single-client install: every request belongs to this one tenant, whatever
    | the host or header. Plans, usage limits, billing, tenant switching and the
    | tenant console are switched off. hidden_modules (comma-separated, e.g.
    | production,projects,visitor) are left out of the sidebar and blocked.
    | Create the tenant with: php artisan client:install
    */
    'single_client' => [
        'slug' => env('SINGLE_CLIENT_SLUG'),
        'hidden_modules' => array_values(array_filter(array_map('trim', explode(',', (string) env('SINGLE_CLIENT_HIDDEN_MODULES', ''))))),
    ],

    'central_domains' => array_filter(
        array_map('trim', explode(',', env('CENTRAL_DOMAINS', 'localhost,127.0.0.1')))
    ),

    /*
    | Platform administrator created by Database\Seeders\PlatformAdminSeeder.
    | Nothing is created unless both email and password are set; an existing
    | admin's password is never overwritten. Or run:
    |   php artisan rbac:create-user --platform
    */
    'platform_admin' => [
        'name' => env('PLATFORM_ADMIN_NAME', 'Platform Admin'),
        'email' => env('PLATFORM_ADMIN_EMAIL'),
        'password' => env('PLATFORM_ADMIN_PASSWORD'),
    ],
];
