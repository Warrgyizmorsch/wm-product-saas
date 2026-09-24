<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

return [
    [
        'key' => 'sales.open_orders', 'title' => 'Open Sales Orders', 'module' => 'sales', 'permission' => 'sales.orders.view',
        'type' => 'kpi', 'icon' => 'feather-shopping-cart', 'w' => 3, 'h' => 2,
        'description' => 'Sales orders not yet completed or cancelled.',
        'data' => fn (WidgetContext $c): array => [
            'value' => number_format(SalesOrder::query()->whereNotIn(DB::raw('LOWER(status)'), ['draft', 'completed', 'delivered', 'closed', 'cancelled'])->count()),
            'sub' => null,
            'tone' => 'warning',
        ],
    ],
    [
        'key' => 'sales.outstanding_invoices', 'title' => 'Outstanding Invoices', 'module' => 'sales', 'permission' => 'sales.invoices.view',
        'type' => 'kpi', 'icon' => 'feather-file-text', 'w' => 3, 'h' => 2,
        'description' => 'Total balance still due on customer invoices.',
        'data' => function (WidgetContext $c): array {
            $due = Invoice::query()->where('balance_due', '>', 0);

            return [
                'value' => number_format((float) (clone $due)->sum('balance_due'), 2),
                'sub' => number_format((clone $due)->whereDate('due_date', '<', now())->count()).' overdue',
                'tone' => 'danger',
            ];
        },
    ],
];
