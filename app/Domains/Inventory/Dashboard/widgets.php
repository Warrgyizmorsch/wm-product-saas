<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\Inventory\Models\Product;

$lowStock = fn () => Product::query()->where('reorder_point', '>', 0)->limit(500)->get()
    ->filter(fn (Product $p) => (float) $p->total_stock <= (float) $p->reorder_point);
$qty = fn ($n): string => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

return [
    [
        'key' => 'inventory.low_stock_count', 'title' => 'Low Stock Items', 'module' => 'inventory', 'permission' => 'inventory.products.view',
        'type' => 'kpi', 'icon' => 'feather-alert-triangle', 'w' => 3, 'h' => 2,
        'description' => 'Products at or below their reorder point.',
        'data' => fn (WidgetContext $c): array => ['value' => number_format($lowStock()->count()), 'sub' => 'at or below reorder point', 'tone' => 'warning'],
    ],
    [
        'key' => 'inventory.low_stock_list', 'title' => 'Reorder Now', 'module' => 'inventory', 'permission' => 'inventory.products.view',
        'type' => 'list', 'icon' => 'feather-box', 'w' => 4, 'h' => 4, 'settings' => ['limit' => true],
        'description' => 'The products furthest below their reorder point.',
        'data' => fn (WidgetContext $c): array => ['rows' => $lowStock()
            ->sortBy(fn (Product $p) => (float) $p->total_stock - (float) $p->reorder_point)->take($c->limit)
            ->map(fn (Product $p) => ['label' => (string) $p->name, 'value' => $qty($p->total_stock).' / '.$qty($p->reorder_point)])
            ->values()->all()],
    ],
];
