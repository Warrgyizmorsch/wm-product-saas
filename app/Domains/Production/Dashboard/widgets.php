<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;

$active = fn () => ProductionOrder::query()->whereNotIn('status', [ProductionOrder::STATUS_COMPLETED, ProductionOrder::STATUS_CLOSED, ProductionOrder::STATUS_CANCELLED]);
$downtime = fn (WidgetContext $c) => ProductionMachineDowntime::query()->whereBetween('start_time', [$c->period->from, $c->period->to]);

return [
    [
        'key' => 'production.open_orders', 'title' => 'Open Production Orders', 'module' => 'production', 'permission' => 'production.intelligence.view',
        'type' => 'kpi', 'icon' => 'feather-settings', 'w' => 3, 'h' => 2,
        'description' => 'Production orders still in progress.',
        'data' => fn (WidgetContext $c): array => ['value' => number_format($active()->count()), 'sub' => null, 'tone' => 'info'],
    ],
    [
        'key' => 'production.orders_by_status', 'title' => 'Production Orders by Status', 'module' => 'production', 'permission' => 'production.intelligence.view',
        'type' => 'donut', 'icon' => 'feather-pie-chart', 'w' => 4, 'h' => 4,
        'description' => 'Open production orders grouped by status.',
        'data' => function (WidgetContext $c) use ($active): array {
            $rows = $active()->selectRaw('status, count(*) as total')->groupBy('status')->orderByDesc('total')->get();

            return ['labels' => $rows->pluck('status')->map(fn ($s) => ucfirst(str_replace('_', ' ', (string) $s)))->all(), 'values' => $rows->pluck('total')->map(fn ($n) => (int) $n)->all()];
        },
    ],
    [
        'key' => 'production.downtime_hours', 'title' => 'Machine Downtime', 'module' => 'production', 'permission' => 'production.intelligence.view',
        'type' => 'kpi', 'icon' => 'feather-clock', 'w' => 3, 'h' => 2, 'settings' => ['period' => true],
        'description' => 'Total hours machines were down in the period.',
        'data' => function (WidgetContext $c) use ($downtime): array {
            $rows = $downtime($c);

            return ['value' => number_format((float) (clone $rows)->sum('duration_minutes') / 60, 1).' h', 'sub' => number_format((clone $rows)->count()).' stoppages · '.$c->period->label(), 'tone' => 'danger'];
        },
    ],
    [
        'key' => 'production.downtime_by_category', 'title' => 'Downtime by Cause', 'module' => 'production', 'permission' => 'production.intelligence.view',
        'type' => 'donut', 'icon' => 'feather-pie-chart', 'w' => 4, 'h' => 4, 'settings' => ['period' => true],
        'description' => 'Machine downtime hours grouped by cause.',
        'data' => function (WidgetContext $c) use ($downtime): array {
            $rows = $downtime($c)->selectRaw('category, sum(duration_minutes) as minutes')->groupBy('category')->orderByDesc('minutes')->limit(8)->get();

            return [
                'labels' => $rows->pluck('category')->map(fn ($s) => ucfirst(str_replace('_', ' ', (string) ($s ?: 'Uncategorised'))))->all(),
                'values' => $rows->pluck('minutes')->map(fn ($m) => round((float) $m / 60, 1))->all(),
            ];
        },
    ],
];
