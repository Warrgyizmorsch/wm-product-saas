<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Support\GstReportPeriod;
use App\Domains\HRMS\Models\Company;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesReturn;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class Gstr3bController extends Controller
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

        $outward = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        foreach (Invoice::whereBetween('invoice_date', [$from, $to])->where('status', '!=', 'Cancelled')->get() as $invoice) {
            $outward['taxable'] += (float) $invoice->subtotal;
            $outward['cgst'] += (float) $invoice->cgst_amount;
            $outward['sgst'] += (float) $invoice->sgst_amount;
            $outward['igst'] += (float) $invoice->igst_amount;
        }
        foreach (SalesReturn::with('invoice')->whereBetween('return_date', [$from, $to])->where('status', 'Completed')->get() as $return) {
            $split = GstReportPeriod::splitReturnTax($return, $return->invoice);
            $outward['taxable'] -= $split['taxable'];
            $outward['cgst'] -= $split['cgst'];
            $outward['sgst'] -= $split['sgst'];
            $outward['igst'] -= $split['igst'];
        }

        $itc = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        foreach (VendorBill::whereBetween('bill_date', [$from, $to])->where('status', '!=', 'Cancelled')->get() as $bill) {
            $itc['taxable'] += (float) $bill->subtotal;
            $itc['cgst'] += (float) $bill->cgst_amount;
            $itc['sgst'] += (float) $bill->sgst_amount;
            $itc['igst'] += (float) $bill->igst_amount;
        }
        foreach (PurchaseReturn::with('vendorBill')->whereBetween('return_date', [$from, $to])->where('status', 'Completed')->get() as $return) {
            $split = GstReportPeriod::splitReturnTax($return, $return->vendorBill);
            $itc['taxable'] -= $split['taxable'];
            $itc['cgst'] -= $split['cgst'];
            $itc['sgst'] -= $split['sgst'];
            $itc['igst'] -= $split['igst'];
        }

        $netPayable = [
            'cgst' => $outward['cgst'] - $itc['cgst'],
            'sgst' => $outward['sgst'] - $itc['sgst'],
            'igst' => $outward['igst'] - $itc['igst'],
        ];
        $netPayable['total'] = $netPayable['cgst'] + $netPayable['sgst'] + $netPayable['igst'];

        return view('modules.accounting.reports.gstr3b', [
            'from' => $from,
            'to' => $to,
            'outward' => $outward,
            'itc' => $itc,
            'netPayable' => $netPayable,
            'filerGstin' => Company::first()?->gst_number,
        ]);
    }
}
