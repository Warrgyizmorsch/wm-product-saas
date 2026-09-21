<?php

namespace App\Core\Dashboard;

use Illuminate\Support\Carbon;

/** The date range a dashboard (or one widget) is looking at. */
final class Period
{
    public const PRESETS = [
        'today' => 'Today',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_30_days' => 'Last 30 days',
        'this_quarter' => 'This quarter',
        'this_year' => 'This year',
        'fiscal_year' => 'Fiscal year to date',
        'custom' => 'Custom range',
    ];

    public const DEFAULT = 'this_month';

    public function __construct(
        public readonly string $preset,
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {
    }

    public static function resolve(?string $preset, ?string $from = null, ?string $to = null, ?Carbon $today = null): self
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $preset = array_key_exists((string) $preset, self::PRESETS) ? $preset : self::DEFAULT;

        [$start, $end] = match ($preset) {
            'today' => [$today->copy(), $today->copy()],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'last_30_days' => [$today->copy()->subDays(29), $today->copy()],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()],
            // Accounting widgets swap in the tenant's real fiscal-year start.
            'this_year', 'fiscal_year' => [$today->copy()->startOfYear(), $today->copy()],
            'custom' => [self::date($from) ?? $today->copy()->startOfMonth(), self::date($to) ?? $today->copy()],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        return new self($preset, $start->copy()->startOfDay(), $end->copy()->endOfDay());
    }

    public function label(): string
    {
        return $this->from->isSameDay($this->to)
            ? $this->from->format('d M Y')
            : $this->from->format('d M Y').' – '.$this->to->format('d M Y');
    }

    private static function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
