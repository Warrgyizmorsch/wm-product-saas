<?php

return [
    'header' => env('TENANT_HEADER', 'X-Tenant'),

    'local_fallback_slug' => env('TENANT_LOCAL_FALLBACK_SLUG', 'warrgyizmorsch'),

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
