<?php
$en = include 'lang/en/production.php';
$check = [
    'open_dispatch_board',
    'history',
    'change_schedule_start_date',
    'whats_next',
    'forward',
    'backward_jit',
    'due_date_target',
    'scheduled_at',
    'source',
    'day',
    'week',
    'month',
    'cancel',
    'close',
    'blocked',
    'closed',
    'scheduled_minutes',
    'available_capacity',
    'utilization_percent',
    'capacity_status',
    'overloaded',
    'normal_capacity',
    'can_release',
    'has_warnings',
    'release_schedule_to_shop_floor',
];
foreach ($check as $k) {
    echo "$k => " . ($en[$k] ?? 'MISSING') . "\n";
}
