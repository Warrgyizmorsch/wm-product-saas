<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Models\Notification;
use App\Services\Access\AccessService;
use App\Services\Approval\ApprovalCenterService;
use Illuminate\Support\Facades\Route;

return [
    [
        'key' => 'platform.pending_approvals', 'title' => 'Pending Approvals', 'module' => 'platform', 'permission' => null,
        'type' => 'list', 'icon' => 'feather-check-circle', 'w' => 4, 'h' => 4, 'settings' => ['limit' => true],
        'description' => 'Items waiting for your approval across modules.',
        'data' => function (WidgetContext $c): array {
            $result = app(ApprovalCenterService::class)->getPendingApprovals($c->user);

            return ['rows' => collect($result['items'] ?? [])->take($c->limit)->map(fn (array $item) => [
                'label' => trim(($item['type'] ?? '').' '.($item['title'] ?? 'Approval')),
                'value' => (string) ($item['module'] ?? ''),
            ])->values()->all()];
        },
    ],
    [
        'key' => 'platform.notifications', 'title' => 'Notifications', 'module' => 'platform', 'permission' => null,
        'type' => 'list', 'icon' => 'feather-bell', 'w' => 4, 'h' => 4, 'settings' => ['limit' => true],
        'description' => 'Your latest unread notifications.',
        'data' => fn (WidgetContext $c): array => ['rows' => Notification::forUser($c->user->id)->unread()->orderByDesc('created_at')->limit($c->limit)->get()->map(fn (Notification $n) => [
            'label' => (string) $n->title,
            'value' => $n->created_at->diffForHumans(),
        ])->all()],
    ],
    [
        'key' => 'platform.quick_links', 'title' => 'Quick Links', 'module' => 'platform', 'permission' => null,
        'type' => 'links', 'icon' => 'feather-external-link', 'w' => 3, 'h' => 4,
        'description' => 'Shortcuts to the module dashboards you can open.',
        'data' => function (WidgetContext $c): array {
            $access = app(AccessService::class);
            $plan = tenant_allowed_modules();
            $links = [
                ['Accounting dashboard', 'accounting.dashboard', 'accounting', 'accounting.reports.view'],
                ['CRM dashboard', 'crm.dashboard', 'crm', 'crm.leads.view'],
                ['Supply chain dashboard', 'supply-chain.dashboard', 'inventory', 'inventory.products.view'],
                ['Production dashboard', 'production.dashboard', 'production', 'production.intelligence.view'],
                ['HRMS dashboard', 'hrms.dashboard', 'hrms', 'hrms.employees.view'],
            ];

            return ['rows' => collect($links)
                ->filter(fn (array $l) => Route::has($l[1])
                    && ($plan === null || in_array($l[2], $plan, true))
                    && $access->allows($c->user, $l[3], ['tenant_id' => $c->user->tenant_id]))
                ->map(fn (array $l) => ['label' => $l[0], 'url' => route($l[1])])->values()->all()];
        },
    ],
];
