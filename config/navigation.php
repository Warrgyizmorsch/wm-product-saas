<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Placeholder entries
    |--------------------------------------------------------------------------
    |
    | Menu entries without a route are screens that don't exist yet. They are
    | hidden so users never click into a dead link; turn this on while
    | building to see where they will sit.
    |
    */

    'show_placeholders' => (bool) env('NAVIGATION_SHOW_PLACEHOLDERS', false),

    /*
    |--------------------------------------------------------------------------
    | Sidebar sections
    |--------------------------------------------------------------------------
    |
    | Top-to-bottom order of the sidebar sections. Each module adds its own
    | entries to these sections from app/Domains/{Module}/Routes/menu.php
    | (see App\Core\Navigation\MenuBuilder). `label` may be a translation key;
    | `default` is used when that key has no translation.
    |
    */

    'sections' => [
        'workspace' => ['label' => 'ui.workspace', 'default' => 'Workspace'],
        'revenue_cycle' => ['label' => 'ui.revenue_cycle', 'default' => 'Revenue Cycle'],
        'supply_chain' => ['label' => 'ui.supply_chain', 'default' => 'Supply Chain'],
        'production' => ['label' => 'ui.production', 'default' => 'Production'],
        'hrms' => ['label' => 'ui.hrms', 'default' => 'HRMS'],
        'finance' => ['label' => 'ui.finance_people', 'default' => 'Finance & People'],
        'platform_admin' => ['label' => 'ui.platform_admin', 'default' => 'Platform Admin'],
    ],

];
