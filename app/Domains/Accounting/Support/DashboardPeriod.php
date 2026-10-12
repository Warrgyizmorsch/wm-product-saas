<?php

namespace App\Domains\Accounting\Support;

use Illuminate\Support\Carbon;

/**
 * The date range the Accounting dashboard reports on, plus the equal-length
 * range immediately before it for "vs previous period" comparisons.
 */
final class DashboardPeriod
{
    public const PRESETS = [
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_quarter' => 'This Quarter',
        'fiscal_year' => 'Fiscal Year to Date',
        'custom' => 'Custom Range',
    ];

    private function __construct(
        public readonly string $preset,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly Carbon $previousFrom,
        public readonly Carbon $previousTo,
    ) {
    }

    /**
     * @param array{preset?: ?string, from?: ?string, to?: ?string} $input
     */
    public static function resolve(array $input, Carbon $today, ?Carbon $fiscalYearStart = null): self
    {
        $preset = array_key_exists((string) ($input['preset'] ?? ''), self::PRESETS) ? $input['preset'] : 'this_month';
        $today = $today->copy()->startOfDay();

        [$from, $to] = match ($preset) {
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today->copy()],
            'fiscal_year' => [($fiscalYearStart ?? $today->copy()->startOfYear())->copy(), $today->copy()],
            'custom' => [self::date($input['from'] ?? null) ?? $today->copy()->startOfMonth(), self::date($input['to'] ?? null) ?? $today->copy()],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        [$previousFrom, $previousTo] = self::comparative($preset, $from, $to, $fiscalYearStart);

        return new self($preset, $from, $to, $previousFrom->startOfDay(), $previousTo->endOfDay());
    }

    /**
     * Like-for-like comparison: MTD against the same days of last month, QTD
     * against the same days into last quarter, YTD against the same span of
     * last fiscal year, a whole month against the whole month before. Only a
     * custom range falls back to the equal-length window just before it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function comparative(string $preset, Carbon $from, Carbon $to, ?Carbon $fiscalYearStart): array
    {
        $span = self::daysBetween($from, $to) - 1;
        $sameSpanFrom = fn (Carbon $start, Carbon $limit) => [$start, $start->copy()->addDays($span)->min($limit)];

        return match ($preset) {
            'this_month' => $sameSpanFrom(
                $from->copy()->subMonthNoOverflow()->startOfMonth(),
                $from->copy()->subMonthNoOverflow()->endOfMonth(),
            ),
            'last_month' => [
                $from->copy()->subMonthNoOverflow()->startOfMonth(),
                $from->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_quarter' => $sameSpanFrom(
                $from->copy()->subMonthsNoOverflow(3)->firstOfQuarter(),
                $from->copy()->subMonthsNoOverflow(3)->lastOfQuarter(),
            ),
            'fiscal_year' => $sameSpanFrom(
                $from->copy()->subYearNoOverflow(),
                ($fiscalYearStart ?? $from)->copy()->subDay(),
            ),
            default => [$from->copy()->subDays($span + 1), $from->copy()->subDay()],
        };
    }

    public function presetLabel(): string
    {
        return self::PRESETS[$this->preset] ?? self::PRESETS['custom'];
    }

    public function days(): int
    {
        return self::daysBetween($this->from, $this->to);
    }

    public function label(): string
    {
        return $this->from->format('d M Y').' – '.$this->to->format('d M Y');
    }

    public function previousLabel(): string
    {
        return $this->previousFrom->format('d M Y').' – '.$this->previousTo->format('d M Y');
    }

    private static function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) round(abs($from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()))) + 1;
    }

    private static function date(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
