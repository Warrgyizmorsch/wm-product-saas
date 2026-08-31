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

        $input = ['taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
        foreach ($bills as $bill) {
            $input['taxable'] += (float) $bill->subtotal;
            $input['cgst'] += (float) $bill->cgst_amount;
            $input['sgst'] += (float) $bill->sgst_amount;
            $input['igst'] += (float) $bill->igst_amount;
        }
        foreach ($purchaseReturns as $return) {
            $split = GstReportPeriod::splitReturnTax($return, $return->vendorBill);
            $input['taxable'] -= $split['taxable'];
            $input['cgst'] -= $split['cgst'];
            $input['sgst'] -= $split['sgst'];
            $input['igst'] -= $split['igst'];
        }

        $payable = [
            'cgst' => $output['cgst'] - $input['cgst'],
            'sgst' => $output['sgst'] - $input['sgst'],
            'igst' => $output['igst'] - $input['igst'],
        ];
        $payable['total'] = $payable['cgst'] + $payable['sgst'] + $payable['igst'];

        return view('modules.accounting.reports.gst-summary', [
            'from' => $from,
            'to' => $to,
            'output' => $output,
            'input' => $input,
            'payable' => $payable,
            'filerGstin' => Company::first()?->gst_number,
        ]);
    }
}
