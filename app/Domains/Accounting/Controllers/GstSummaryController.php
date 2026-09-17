<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Services\GstSummaryService;
use App\Domains\Accounting\Support\GstReportPeriod;
use App\Domains\HRMS\Models\Company;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GstSummaryController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly GstSummaryService $gst,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        [$from, $to] = GstReportPeriod::resolve($request);

        return view('modules.accounting.reports.gst-summary', [
            'from' => $from,
            'to' => $to,
            'filerGstin' => Company::first()?->gst_number,
        ] + $this->gst->summary($from, $to));
    }
}
