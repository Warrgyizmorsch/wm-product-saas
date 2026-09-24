<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\Purchase\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

return [
    [
        'key' => 'purchase.pending_orders', 'title' => 'Pending Purchase Orders', 'module' => 'purchase', 'permission' => 'purchase.orders.view',
        'type' => 'kpi', 'icon' => 'feather-truck', 'w' => 3, 'h' => 2,
        'description' => 'Purchase orders not yet completed or cancelled.',
        'data' => function (WidgetContext $c): array {
            $open = PurchaseOrder::query()->whereNotIn(DB::raw('LOWER(status)'), ['completed', 'closed', 'cancelled']);

            return ['value' => number_format((clone $open)->count()), 'sub' => 'Worth '.number_format((float) (clone $open)->sum('grand_total'), 2), 'tone' => 'warning'];
        },
    ],
];
