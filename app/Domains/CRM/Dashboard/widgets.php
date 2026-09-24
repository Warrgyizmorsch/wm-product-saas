<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

$closed = ['won', 'closed won', 'lost', 'closed lost'];

return [
    [
        'key' => 'crm.open_leads', 'title' => 'Open Leads', 'module' => 'crm', 'permission' => 'crm.leads.view',
        'type' => 'kpi', 'icon' => 'feather-users', 'w' => 3, 'h' => 2, 'settings' => ['period' => true],
        'description' => 'Leads that are still being worked, and how many arrived in the period.',
        'data' => fn (WidgetContext $c): array => [
            'value' => number_format(Lead::query()->whereNotIn(DB::raw('LOWER(status)'), $closed)->count()),
            'sub' => number_format(Lead::query()->whereBetween('created_at', [$c->period->from, $c->period->to])->count()).' new · '.$c->period->label(),
            'tone' => 'primary',
        ],
    ],
    [
        'key' => 'crm.pipeline', 'title' => 'Leads by Status', 'module' => 'crm', 'permission' => 'crm.leads.view',
        'type' => 'donut', 'icon' => 'feather-pie-chart', 'w' => 4, 'h' => 4, 'settings' => ['period' => true],
        'description' => 'Leads created in the period, split by status.',
        'data' => function (WidgetContext $c): array {
            $rows = Lead::query()->whereBetween('created_at', [$c->period->from, $c->period->to])
                ->select('status', DB::raw('count(*) as total'))->groupBy('status')->orderByDesc('total')->limit(8)->get();

            return ['labels' => $rows->pluck('status')->map(fn ($s) => ucfirst((string) $s))->all(), 'values' => $rows->pluck('total')->map(fn ($n) => (int) $n)->all()];
        },
    ],
    [
        'key' => 'crm.sales_leaderboard', 'title' => 'Sales Leaderboard', 'module' => 'crm', 'permission' => 'crm.deals.view',
        'type' => 'list', 'icon' => 'feather-award', 'w' => 4, 'h' => 4, 'settings' => ['period' => true, 'limit' => true],
        'description' => 'Who won the most deal value in the period.',
        'data' => function (WidgetContext $c): array {
            $won = CrmDeal::query()->whereBetween('created_at', [$c->period->from, $c->period->to])
                ->whereIn(DB::raw('LOWER(stage)'), ['won', 'closed won'])
                ->select('owner_id', DB::raw('sum(estimated_value) as revenue'), DB::raw('count(*) as deals'))
                ->groupBy('owner_id')->orderByDesc('revenue')->limit($c->limit)->get();
            $names = User::query()->whereIn('id', $won->pluck('owner_id'))->pluck('name', 'id');

            return ['rows' => $won->map(fn ($row) => [
                'label' => (string) ($names[$row->owner_id] ?? 'Unassigned'),
                'value' => number_format((float) $row->revenue, 2).' · '.$row->deals.' won',
            ])->all()];
        },
    ],
    [
        'key' => 'crm.open_quotations', 'title' => 'Open Quotations', 'module' => 'crm', 'permission' => 'crm.quotations.view',
        'type' => 'kpi', 'icon' => 'feather-file-text', 'w' => 3, 'h' => 2,
        'description' => 'Quotations still waiting to be sent, approved or accepted.',
        'data' => function (WidgetContext $c): array {
            $open = Quotation::query()->whereIn('status', ['draft', 'pending_approval', 'sent']);

            return ['value' => number_format((clone $open)->count()), 'sub' => 'Worth '.number_format((float) (clone $open)->sum('total_amount'), 2), 'tone' => 'info'];
        },
    ],
];
