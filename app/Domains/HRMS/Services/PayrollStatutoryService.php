<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollStatutoryLine;
use Illuminate\Support\Collection;

/**
 * PF and ESI per employee for a posted payroll run — the source of the EPFO
 * ECR file and the ESIC monthly contribution return.
 *
 * PF split (EPF Scheme): the employee pays 12% of EPF wages; the employer's
 * matching 12% goes 8.33% of EPS wages (capped at 15,000) to the pension
 * fund and the rest (3.67%) to EPF. EDLI wages share the EPS cap.
 * ESI: the employer pays 3.25% of the same wages the employee's 0.75% is on.
 */
class PayrollStatutoryService
{
    public const PENSION_WAGE_CEILING = 15000.00;
    public const EPS_RATE = 0.0833;

    /**
     * @param array<int, array> $calcs employee id => PayrollCalculationService::calculateSalary() result
     */
    public function captureRun(PayrollRun $run, Collection $employees, array $calcs, ?int $companyId = null): void
    {
        foreach ($employees as $employee) {
            if (isset($calcs[$employee->id])) {
                $this->capture($run, $employee, $calcs[$employee->id], $companyId);
            }
        }
    }

    public function capture(PayrollRun $run, Employee $employee, array $calc, ?int $companyId = null): ?PayrollStatutoryLine
    {
        $line = $this->figures($employee, $calc);

        if ($line['gross_wages'] <= 0 && $line['epf_employee'] <= 0 && $line['esi_employee'] <= 0) {
            return null;
        }

        return PayrollStatutoryLine::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => (int) ($run->tenant_id ?? $employee->tenant_id), 'payroll_run_id' => $run->id, 'employee_id' => $employee->id],
            $line + [
                'company_id' => $companyId ?? $run->company_id ?? $employee->company_id,
                'payroll_month' => (string) $run->payroll_month,
                'employee_name' => (string) $employee->full_name,
                'uan_number' => $employee->uan_number,
                'esi_ip_number' => $employee->esi_ip_number,
            ],
        );
    }

    /**
     * The return figures for one employee's month, from the salary calculation.
     *
     * @return array<string, float>
     */
    public function figures(Employee $employee, array $calc): array
    {
        $items = $calc['items'] ?? $calc['computed_components'] ?? [];
        $summary = $calc['summary'] ?? [];
        $config = config('payroll.employer_contributions');

        $gross = 0.00;
        foreach ($items as $item) {
            if (($item['type'] ?? null) === 'earning') {
                $gross += (float) ($item['calculated_value'] ?? 0);
            }
        }
        $gross = max(0.0, $gross - (float) ($summary['lop_deduction'] ?? 0));

        // PF
        $pf = round((float) ($items['PF']['calculated_value'] ?? 0), 2);
        $pfRate = max(0.0001, (float) $config['employee_pf_percent'] / 100);
        $epfWages = $pf > 0 ? round((float) ($items['PF']['statutory_wages'] ?? $pf / $pfRate)) : 0.0;
        $uncapped = $pf > 0 ? (float) ($items['PF']['uncapped_wages'] ?? $epfWages) : 0.0;
        $epsWages = $pf > 0 ? round(min($uncapped ?: $epfWages, self::PENSION_WAGE_CEILING)) : 0.0;
        $employerPf = $config['employer_pf_matches_employee'] ? round($pf) : 0.0;
        $eps = $pf > 0 ? min(round($epsWages * self::EPS_RATE), $employerPf) : 0.0;

        // ESI
        $esi = round((float) ($items['ESI']['calculated_value'] ?? 0), 2);
        $esiRate = max(0.0001, (float) $config['employee_esi_percent'] / 100);
        $esiWages = $esi > 0 ? round((float) ($items['ESI']['statutory_wages'] ?? $esi / $esiRate)) : 0.0;
        $esiEmployer = $esi > 0 ? ceil($esiWages * (float) $config['employer_esi_percent'] / 100) : 0.0;

        return [
            'gross_wages' => round($gross, 2),
            'paid_days' => round((float) ($summary['paid_days'] ?? 0), 2),
            'ncp_days' => round((float) ($summary['lop_days'] ?? 0), 2),
            'epf_wages' => $epfWages,
            'eps_wages' => $epsWages,
            'edli_wages' => $epsWages,
            'epf_employee' => round($pf),
            'eps_employer' => $eps,
            'epf_employer_diff' => max(0.0, $employerPf - $eps),
            'esi_wages' => $esiWages,
            'esi_employee' => $esi,
            'esi_employer' => (float) $esiEmployer,
        ];
    }

    /** Lines of the posted runs for a month (several runs can pay one month). */
    public function month(string $month): Collection
    {
        return PayrollStatutoryLine::query()->where('payroll_month', $month)->orderBy('employee_name')->get();
    }

    /**
     * EPFO ECR 2.0: one line per member, 11 fields joined by #~#:
     * UAN, name, gross wages, EPF wages, EPS wages, EDLI wages, EPF (employee),
     * EPS (employer), EPF-EPS difference, NCP days, refund of advances.
     */
    public function ecr(Collection $lines): string
    {
        return $lines->filter(fn (PayrollStatutoryLine $l) => $l->hasPf() && filled($l->uan_number))
            ->map(fn (PayrollStatutoryLine $l) => implode('#~#', [
                $l->uan_number,
                strtoupper(preg_replace('/[^A-Za-z .]/', '', $l->employee_name)),
                (int) round($l->gross_wages),
                (int) round($l->epf_wages),
                (int) round($l->eps_wages),
                (int) round($l->edli_wages),
                (int) round($l->epf_employee),
                (int) round($l->eps_employer),
                (int) round($l->epf_employer_diff),
                (int) round($l->ncp_days),
                0,
            ]))
            ->implode("\n");
    }

    /**
     * Rows of the ESIC portal's monthly contribution upload: IP number, name,
     * days paid, total monthly wages, reason code (0 when wages were paid;
     * left blank otherwise for the user to fill), last working day.
     *
     * @return list<list<mixed>>
     */
    public function esiRows(Collection $lines): array
    {
        return $lines->filter(fn (PayrollStatutoryLine $l) => $l->hasEsi())
            ->map(fn (PayrollStatutoryLine $l) => [
                (string) $l->esi_ip_number,
                $l->employee_name,
                (int) round($l->paid_days),
                (int) round($l->esi_wages),
                $l->paid_days > 0 ? 0 : '',
                '',
            ])->values()->all();
    }

    /** Things the portal would reject, so they can be fixed first. */
    public function issues(Collection $lines): array
    {
        $issues = [];

        $noUan = $lines->filter(fn ($l) => $l->hasPf() && blank($l->uan_number))->pluck('employee_name');
        if ($noUan->isNotEmpty()) {
            $issues[] = 'PF deducted but no UAN (left out of the ECR): ' . $noUan->implode(', ') . '.';
        }

        $noIp = $lines->filter(fn ($l) => $l->hasEsi() && blank($l->esi_ip_number))->pluck('employee_name');
        if ($noIp->isNotEmpty()) {
            $issues[] = 'ESI deducted but no IP number: ' . $noIp->implode(', ') . '.';
        }

        return $issues;
    }
}
