<?php

return [
    'header' => env('TENANT_HEADER', 'X-Tenant'),

    'central_domains' => array_filter(
        array_map('trim', explode(',', env('CENTRAL_DOMAINS', 'localhost,127.0.0.1')))
    ),
];
