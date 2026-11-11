<?php

namespace App\Domains\Production\Support;

/**
 * WC-IMPL-04 (WC-08 rule 4, WC-46 — Method A): calculation snapshots store NUMBERS (base currency) and a
 * message type; the sentences are built here at display time with format_currency(), so they always use the
 * viewer's tenant currency and convert from base currency.
 *
 * Old snapshots that only stored the finished text keep showing that text as-is.
 * Money is shown with 4 decimals (no rounding of rates / costs for display).
 */
class CalculationText
{
    private const DEC = 4;

    private static function money($v): string
    {
        return format_currency((float) ($v ?? 0), self::DEC);
    }

    /** Machine "operating cost" explanation (snapshot key: explanation_type). */
    public static function machineExplanation(array $m): string
    {
        $type = $m['explanation_type'] ?? null;
        if ($type === null) {
            return (string) ($m['cost_explanation'] ?? '');
        }

        $op = $m['operating_cost_per_hour'] ?? 0;

        return match ($type) {
            'additional' => 'Includes ' . self::money($m['additional_cost_per_hour'] ?? 0) . '/hr additional cost for consumables, lubricants, or tooling wear.',
            'deductible' => 'Includes ' . self::money($m['deductibles_per_hour'] ?? 0) . '/hr deductibles for power subsidies, solar credits, or discounts.',
            'matches'    => 'Matches calculated base energy consumption rate (' . self::money($op) . '/hr).',
            'flat'       => 'Flat operating cost specified (' . self::money($op) . '/hr).',
            'energy'     => 'Calculated from energy consumption (' . number_format((float) ($m['consumption_per_hour'] ?? 0), 4) . ' units @ '
                            . self::money($m['energy_rate_per_unit'] ?? 0) . '/unit = ' . self::money($op) . '/hr).',
            default      => 'No energy consumption or operating cost entered (' . self::money(0) . '/hr).',
        };
    }

    /** Machine daily cost formula. */
    public static function machineCostFormula(array $m): string
    {
        if (isset($m['cost_formula']) && !isset($m['explanation_type'])) {
            return (string) $m['cost_formula'];
        }

        return self::money($m['operating_cost_per_hour'] ?? 0) . '/hr x ' . format_hours((float) ($m['section_operating_hrs'] ?? 0))
            . ' section operating hrs = ' . self::money($m['daily_cost'] ?? 0) . '/day';
    }

    /** Section internal employee daily / hourly cost formula. */
    public static function employeeFormula(array $e): string
    {
        if (isset($e['formula'])) {
            return (string) $e['formula'];
        }

        $daily = $e['daily_salary'] ?? $e['daily_cost'] ?? 0;
        $hours = (float) ($e['shift_clock_hours'] ?? $e['shift_duration_hours'] ?? 0);

        return 'Annual CTC (' . self::money($e['annual_ctc'] ?? 0) . ') / 12 = Monthly CTC (' . self::money($e['monthly_salary'] ?? $e['hrms_net_monthly_salary'] ?? 0) . ')'
            . ' / 30 days = ' . self::money($daily) . '/day; ' . self::money($daily) . ' / ' . format_hours($hours)
            . ' shift clock hrs = ' . self::money($e['hourly_rate'] ?? $e['hourly_salary_rate'] ?? 0) . '/hr';
    }

    /** Contractor allocation daily cost formula. */
    public static function externalFormula(array $a): string
    {
        if (isset($a['formula'])) {
            return (string) $a['formula'];
        }

        return (int) ($a['headcount'] ?? 0) . ' workers x ' . self::money($a['pay_grade_rate'] ?? 0) . '/hr x '
            . format_hours((float) ($a['shift_clock_hours'] ?? $a['shift_duration_hours'] ?? 0)) . ' shift clock hrs = '
            . self::money($a['daily_cost'] ?? 0) . '/day';
    }

    /** Work Center direct staff allocated daily / hourly cost formula. */
    public static function directStaffFormula(array $e): string
    {
        if (isset($e['formula'])) {
            return (string) $e['formula'];
        }

        $wcCount = (int) ($e['active_wc_count'] ?? $e['total_active_work_centers'] ?? 1);

        return 'Annual CTC (' . self::money($e['annual_ctc'] ?? 0) . ') / 12 = Monthly CTC (' . self::money($e['monthly_salary'] ?? $e['monthly_ctc'] ?? $e['hrms_net_monthly_salary'] ?? 0) . ')'
            . ' / 30 days = ' . self::money($e['base_daily_salary'] ?? 0) . '/day / ' . $wcCount . ' active WCs = '
            . self::money($e['daily_cost'] ?? $e['daily_salary'] ?? 0) . '/day; / ' . format_hours((float) ($e['shift_duration_hours'] ?? 0))
            . ' shift clock hrs (breaks included) = ' . self::money($e['hourly_rate'] ?? 0) . '/hr';
    }

    /** Timeline interval explanation (labor variants carry money). */
    public static function intervalExplanation(array $i): string
    {
        $type = $i['explanation_type'] ?? null;
        if ($type === null) {
            return (string) ($i['explanation'] ?? '');
        }

        $shifts = is_array($i['active_shift_ids'] ?? null) ? count($i['active_shift_ids']) : 1;
        $rate = self::money($i['running_total_labor_rate'] ?? 0);

        return $type === 'labor_overlap'
            ? 'Shift Overlap: ' . $shifts . ' shifts active concurrently. Labor burn rate surges to ' . $rate . '/hr.'
            : 'Regular Window: 1 shift active. Labor burn rate: ' . $rate . '/hr.';
    }
}
