<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Support\GstReportPeriod;
use App\Domains\HRMS\Models\Company;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesReturn;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Gstr1Controller extends Controller
{
    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        [$from, $to] = GstReportPeriod::resolve($request);

        $invoices = Invoice::with('customer')
            ->whereBetween('invoice_date', [$from, $to])
            ->where('status', '!=', 'Cancelled')
            ->get();

        $b2b = $invoices->filter(fn ($invoice) => !empty($invoice->customer?->gstin))->values();
        $b2c = $invoices->filter(fn ($invoice) => empty($invoice->customer?->gstin))->values();

        $returns = SalesReturn::with(['customer', 'invoice'])
            ->whereBetween('return_date', [$from, $to])
            ->where('status', 'Completed')
            ->get()
            ->map(function ($return) {
                $split = GstReportPeriod::splitReturnTax($return, $return->invoice);
                return [
                    'return' => $return,
                    'is_b2b' => !empty($return->customer?->gstin),
                    'split' => $split,
                ];
            });

        $b2bReturns = $returns->filter(fn ($row) => $row['is_b2b'])->values();
        $b2cReturns = $returns->filter(fn ($row) => !$row['is_b2b'])->values();

        $sumInvoices = fn ($rows) => [
            'count' => $rows->count(),
            'taxable' => (float) $rows->sum('subtotal'),
            'cgst' => (float) $rows->sum('cgst_amount'),
            'sgst' => (float) $rows->sum('sgst_amount'),
            'igst' => (float) $rows->sum('igst_amount'),
        ];

        $sumReturns = fn ($rows) => [
            'count' => $rows->count(),
            'taxable' => $rows->sum(fn ($row) => $row['split']['taxable']),
            'cgst' => $rows->sum(fn ($row) => $row['split']['cgst']),
            'sgst' => $rows->sum(fn ($row) => $row['split']['sgst']),
            'igst' => $rows->sum(fn ($row) => $row['split']['igst']),
        ];

        return view('modules.accounting.reports.gstr1', [
            'from' => $from,
            'to' => $to,
            'b2b' => $b2b,
            'b2c' => $b2c,
            'b2bTotals' => $sumInvoices($b2b),
            'b2cTotals' => $sumInvoices($b2c),
            'b2bReturns' => $b2bReturns,
            'b2cReturns' => $b2cReturns,
            'b2bReturnTotals' => $sumReturns($b2bReturns),
            'b2cReturnTotals' => $sumReturns($b2cReturns),
            'filerGstin' => Company::first()?->gst_number,
        ]);
    }
}
