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

        // Split purchases by gst_type: 'rcm*' bills are inward supplies liable to
        // reverse charge (Table 3.1(d)) — a self-assessed liability the buyer pays,
        // distinct from ordinary purchase ITC (Table 4(A)(5)). The matching credit
        // is reported separately too (Table 4(A)(3)) rather than folded into
        // ordinary ITC as the previous version silently did.
        $itc = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        $inwardRcm = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        foreach (VendorBill::whereBetween('bill_date', [$from, $to])->where('status', '!=', 'Cancelled')->get() as $bill) {
            $bucket = str_starts_with((string) $bill->gst_type, 'rcm') ? 'rcm' : 'itc';
            $target = $bucket === 'rcm' ? $inwardRcm : $itc;
            $target['taxable'] += (float) $bill->subtotal;
            $target['cgst'] += (float) $bill->cgst_amount;
            $target['sgst'] += (float) $bill->sgst_amount;
            $target['igst'] += (float) $bill->igst_amount;
            ${$bucket === 'rcm' ? 'inwardRcm' : 'itc'} = $target;
        }
        foreach (PurchaseReturn::with('vendorBill')->whereBetween('return_date', [$from, $to])->where('status', 'Completed')->get() as $return) {
            $bucket = str_starts_with((string) $return->vendorBill?->gst_type, 'rcm') ? 'rcm' : 'itc';
            $target = $bucket === 'rcm' ? $inwardRcm : $itc;
            $split = GstReportPeriod::splitReturnTax($return, $return->vendorBill);
            $target['taxable'] -= $split['taxable'];
            $target['cgst'] -= $split['cgst'];
            $target['sgst'] -= $split['sgst'];
            $target['igst'] -= $split['igst'];
            ${$bucket === 'rcm' ? 'inwardRcm' : 'itc'} = $target;
        }

        // RCM tax is itself claimable as ITC (same amounts) once deposited — Table
        // 4(A)(3). Note this still nets to the same figure as blending it into
        // Table 4(A)(5) would; the RCM portion of the tax must be discharged in
        // cash regardless of overall ITC credit position, which "Total Net
        // Payable" below does not model — it's a summary total, not a filing form.
        $rcmItc = $inwardRcm;

        $netPayable = [
            'cgst' => $outward['cgst'] + $inwardRcm['cgst'] - $itc['cgst'] - $rcmItc['cgst'],
            'sgst' => $outward['sgst'] + $inwardRcm['sgst'] - $itc['sgst'] - $rcmItc['sgst'],
            'igst' => $outward['igst'] + $inwardRcm['igst'] - $itc['igst'] - $rcmItc['igst'],
        ];
        $netPayable['total'] = $netPayable['cgst'] + $netPayable['sgst'] + $netPayable['igst'];

        return view('modules.accounting.reports.gstr3b', [
            'from' => $from,
            'to' => $to,
            'outward' => $outward,
            'inwardRcm' => $inwardRcm,
            'itc' => $itc,
            'rcmItc' => $rcmItc,
            'netPayable' => $netPayable,
            'filerGstin' => Company::first()?->gst_number,
        ]);
    }
}
