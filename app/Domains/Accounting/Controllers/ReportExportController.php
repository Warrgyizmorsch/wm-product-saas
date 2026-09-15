<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Support\ReportTables;
use App\Exports\AccountingReportExport;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF / Excel export for every accounting report. Runs the report's own
 * controller with the same query string, so the export has exactly the
 * figures, filters and permission check of the page it came from.
 */
class ReportExportController extends Controller
{
    private const CONTROLLERS = [
        'day-book' => DayBookController::class,
        'trial-balance' => TrialBalanceController::class,
        'general-ledger' => GeneralLedgerController::class,
        'party-ledger' => PartyLedgerController::class,
        'balance-sheet' => BalanceSheetController::class,
        'profit-loss' => ProfitLossController::class,
        'ar-aging' => ArAgingController::class,
        'ap-aging' => ApAgingController::class,
        'cash-flow' => CashFlowController::class,
        'gst-summary' => GstSummaryController::class,
        'gstr1' => Gstr1Controller::class,
        'gstr3b' => Gstr3bController::class,
        'audit-trail' => AccountingAuditLogController::class,
        'budget-vs-actual' => BudgetVsActualController::class,
        'vouchers-by-staff' => StaffActivityReportController::class,
    ];

    public function __construct(
        private readonly ReportTables $tables,
    ) {
    }

    public function export(Request $request, string $report, string $format): Response
    {
        abort_unless(isset(self::CONTROLLERS[$report]), 404);

        $page = app(self::CONTROLLERS[$report])->index($request);

        if (! $page instanceof View) {
            return $page;
        }

        $table = $this->tables->build($report, $page->getData());
        $filename = Str::studly($report).'_'.now()->format('Ymd');

        if ($format === 'pdf') {
            return Pdf::loadView('modules.accounting.reports.export-pdf', ['report' => $table])
                ->setPaper('a4', $table['orientation'])
                ->download($filename.'.pdf');
        }

        return Excel::download(new AccountingReportExport($table), $filename.'.xlsx');
    }
}
