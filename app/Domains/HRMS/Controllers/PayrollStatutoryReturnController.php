<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\PayrollRun;
use App\Domains\HRMS\Models\PayrollStatutoryLine;
use App\Domains\HRMS\Services\PayrollStatutoryService;
use App\Exports\EsiContributionExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * PF and ESI monthly returns from posted payroll: a review screen per month,
 * the EPFO ECR 2.0 upload file and the ESIC contribution sheet.
 */
class PayrollStatutoryReturnController extends Controller
{
    public function __construct(
        private readonly PayrollStatutoryService $statutory,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PayrollRun::class);

        $month = $this->month($request);
        $lines = $this->statutory->month($month);

        return view('modules.hrms.payroll.statutory-returns', [
            'month' => $month,
            'months' => $this->recentMonths(),
            'lines' => $lines,
            'issues' => $this->statutory->issues($lines),
            'pfTotals' => [
                'members' => $lines->filter(fn (PayrollStatutoryLine $l) => $l->hasPf())->count(),
                'epf_wages' => $lines->sum('epf_wages'),
                'epf_employee' => $lines->sum('epf_employee'),
                'eps_employer' => $lines->sum('eps_employer'),
                'epf_employer_diff' => $lines->sum('epf_employer_diff'),
            ],
            'esiTotals' => [
                'members' => $lines->filter(fn (PayrollStatutoryLine $l) => $l->hasEsi())->count(),
                'esi_wages' => $lines->sum('esi_wages'),
                'esi_employee' => $lines->sum('esi_employee'),
                'esi_employer' => $lines->sum('esi_employer'),
            ],
            'canExport' => auth()->user()->can('process', PayrollRun::class),
        ]);
    }

    public function ecr(Request $request): Response
    {
        $this->authorize('process', PayrollRun::class);

        $month = $this->month($request);
        $file = $this->statutory->ecr($this->statutory->month($month));
        abort_if($file === '', 404, 'No PF members with a UAN in this month.');

        return response($file, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ECR_' . str_replace('-', '', $month) . '.txt"',
        ]);
    }

    public function esi(Request $request): Response
    {
        $this->authorize('process', PayrollRun::class);

        $month = $this->month($request);
        $rows = $this->statutory->esiRows($this->statutory->month($month));
        abort_if($rows === [], 404, 'No ESI contributions in this month.');

        return Excel::download(new EsiContributionExport($rows), 'ESIC_MC_' . str_replace('-', '', $month) . '.xlsx');
    }

    private function month(Request $request): string
    {
        $month = (string) $request->input('month', now()->subMonthNoOverflow()->format('Y-m'));
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 404);

        return $month;
    }

    /** The last 15 months, newest first. */
    private function recentMonths(): array
    {
        return collect(range(0, 14))
            ->mapWithKeys(fn (int $i) => [now()->startOfMonth()->subMonths($i)->format('Y-m') => now()->startOfMonth()->subMonths($i)->format('M Y')])
            ->all();
    }
}
