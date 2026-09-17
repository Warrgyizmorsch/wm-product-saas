<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Services\StaffActivityReportService;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StaffActivityReportController extends Controller
{
    public function __construct(
        private readonly StaffActivityReportService $report,
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : now();

        return view('modules.accounting.reports.vouchers-by-staff', [
            'from' => $from,
            'to' => $to,
            'documentTypes' => $this->report->documentTypes(),
        ] + $this->report->build($from, $to));
    }
}
