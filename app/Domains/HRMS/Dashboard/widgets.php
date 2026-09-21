<?php

// Dashboard widgets for the common dashboard. See App\Core\Dashboard\WidgetRegistry.

use App\Core\Dashboard\WidgetContext;
use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\LeaveRequest;

return [
    [
        'key' => 'hrms.headcount', 'title' => 'Active Employees', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'kpi', 'icon' => 'feather-users', 'w' => 3, 'h' => 2,
        'description' => 'Employees currently active.',
        'data' => fn (WidgetContext $c): array => ['value' => number_format(Employee::query()->where('status', true)->count()), 'sub' => null, 'tone' => 'info'],
    ],
    [
        'key' => 'hrms.present_today', 'title' => 'Present Today', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'kpi', 'icon' => 'feather-user-check', 'w' => 3, 'h' => 2,
        'description' => 'Employees who have checked in today.',
        'data' => fn (WidgetContext $c): array => [
            'value' => number_format(Attendance::query()->whereDate('date', today())->whereNotNull('check_in')->count()),
            'sub' => 'of '.number_format(Employee::query()->where('status', true)->count()).' active',
            'tone' => 'success',
        ],
    ],
    [
        'key' => 'hrms.pending_leaves', 'title' => 'Pending Leave Requests', 'module' => 'hrms', 'permission' => 'hrms.leave_requests.view',
        'type' => 'kpi', 'icon' => 'feather-calendar', 'w' => 3, 'h' => 2,
        'description' => 'Leave requests waiting for a decision.',
        'data' => fn (WidgetContext $c): array => ['value' => number_format(LeaveRequest::query()->where('status', 'pending')->count()), 'sub' => null, 'tone' => 'warning'],
    ],
    [
        'key' => 'hrms.attendance_today', 'title' => 'Attendance Today', 'module' => 'hrms', 'permission' => 'hrms.attendance.view',
        'type' => 'donut', 'icon' => 'feather-pie-chart', 'w' => 4, 'h' => 4,
        'description' => 'Today\'s attendance records split by status.',
        'data' => function (WidgetContext $c): array {
            $rows = Attendance::query()->whereDate('date', today())->selectRaw('status, count(*) as total')->groupBy('status')->orderByDesc('total')->get();

            return ['labels' => $rows->pluck('status')->map(fn ($s) => ucfirst(str_replace('_', ' ', (string) ($s ?: 'present'))))->all(), 'values' => $rows->pluck('total')->map(fn ($n) => (int) $n)->all()];
        },
    ],
    [
        'key' => 'hrms.birthdays', 'title' => 'Upcoming Birthdays', 'module' => 'hrms', 'permission' => 'hrms.employees.view',
        'type' => 'list', 'icon' => 'feather-gift', 'w' => 4, 'h' => 4, 'settings' => ['limit' => true],
        'description' => 'Employee birthdays in the next 30 days.',
        'data' => function (WidgetContext $c): array {
            $today = today();

            $rows = Employee::query()->where('status', true)->whereNotNull('date_of_birth')->get(['id', 'full_name', 'date_of_birth'])
                ->map(function (Employee $e) use ($today) {
                    $next = $e->date_of_birth->copy()->year($today->year);

                    return ['name' => (string) $e->full_name, 'next' => $next->lt($today) ? $next->addYear() : $next];
                })
                ->filter(fn (array $r) => $r['next']->diffInDays($today, true) <= 30)
                ->sortBy(fn (array $r) => $r['next']->timestamp)
                ->take($c->limit);

            return ['rows' => $rows->map(fn (array $r) => [
                'label' => $r['name'],
                'value' => $r['next']->isSameDay($today) ? 'Today' : $r['next']->format('d M'),
            ])->values()->all()];
        },
    ],
];
