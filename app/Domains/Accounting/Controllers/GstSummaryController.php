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

class GstSummaryController extends Controller
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

        $invoices = Invoice::whereBetween('invoice_date', [$from, $to])->where('status', '!=', 'Cancelled')->get();
        $salesReturns = SalesReturn::with('invoice')->whereBetween('return_date', [$from, $to])->where('status', 'Completed')->get();
        $bills = VendorBill::whereBetween('bill_date', [$from, $to])->where('status', '!=', 'Cancelled')->get();
        $purchaseReturns = PurchaseReturn::with('vendorBill')->whereBetween('return_date', [$from, $to])->where('status', 'Completed')->get();

        $output = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        foreach ($invoices as $invoice) {
            $output['taxable'] += (float) $invoice->subtotal;
            $output['cgst'] += (float) $invoice->cgst_amount;
            $output['sgst'] += (float) $invoice->sgst_amount;
            $output['igst'] += (float) $invoice->igst_amount;
        }
        foreach ($salesReturns as $return) {
            $split = GstReportPeriod::splitReturnTax($return, $return->invoice);
            $output['taxable'] -= $split['taxable'];
            $output['cgst'] -= $split['cgst'];
            $output['sgst'] -= $split['sgst'];
            $output['igst'] -= $split['igst'];
        }

        // Purchases under reverse charge (gst_type prefixed 'rcm') are kept out of
        // regular Input GST/ITC: the buyer self-assesses and pays this tax in cash
        // (it can't net against output liability), so it's reported as its own
        // liability line, with the matching ITC shown separately once paid —
        // mirrors GSTR-3B tables 3.1(d) and 4(A)(3) rather than folding it into
        // ordinary purchase ITC as the previous version silently did.
        $input = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        $rcm = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        foreach ($bills as $bill) {
            $bucket = str_starts_with((string) $bill->gst_type, 'rcm') ? 'rcm' : 'input';
            $target = $bucket === 'rcm' ? $rcm : $input;
            $target['taxable'] += (float) $bill->subtotal;
            $target['cgst'] += (float) $bill->cgst_amount;
            $target['sgst'] += (float) $bill->sgst_amount;
            $target['igst'] += (float) $bill->igst_amount;
            ${$bucket} = $target;
        }
        foreach ($purchaseReturns as $return) {
            $bucket = str_starts_with((string) $return->vendorBill?->gst_type, 'rcm') ? 'rcm' : 'input';
            $target = $bucket === 'rcm' ? $rcm : $input;
            $split = GstReportPeriod::splitReturnTax($return, $return->vendorBill);
            $target['taxable'] -= $split['taxable'];
            $target['cgst'] -= $split['cgst'];
            $target['sgst'] -= $split['sgst'];
            $target['igst'] -= $split['igst'];
            ${$bucket} = $target;
        }

        // RCM tax paid is itself claimable as ITC (same amounts) once deposited —
        // shown as its own reconciling line rather than netted away silently.
        $rcmItc = $rcm;

        $payable = [
            'cgst' => $output['cgst'] - $input['cgst'] + $rcm['cgst'] - $rcmItc['cgst'],
            'sgst' => $output['sgst'] - $input['sgst'] + $rcm['sgst'] - $rcmItc['sgst'],
            'igst' => $output['igst'] - $input['igst'] + $rcm['igst'] - $rcmItc['igst'],
        ];
        $payable['total'] = $payable['cgst'] + $payable['sgst'] + $payable['igst'];

        return view('modules.accounting.reports.gst-summary', [
            'from' => $from,
            'to' => $to,
            'output' => $output,
            'input' => $input,
            'rcm' => $rcm,
            'rcmItc' => $rcmItc,
            'payable' => $payable,
            'filerGstin' => Company::first()?->gst_number,
        ]);
    }
}
