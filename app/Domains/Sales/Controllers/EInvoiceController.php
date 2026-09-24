<?php

namespace App\Domains\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Services\EInvoiceService;
use App\Domains\Sales\Services\EWayBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EInvoiceController extends Controller
{
    public function __construct(
        protected EInvoiceService $eInvoiceService,
        protected EWayBillService $eWayBillService
    ) {}

    /**
     * Generate E-Invoice (IRN)
     */
    public function generateEInvoice(Request $request, int $id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('update', $invoice);

        $result = $this->eInvoiceService->generate($invoice);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Cancel E-Invoice (IRN)
     */
    public function cancelEInvoice(Request $request, int $id): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('update', $invoice);

        $reason = $request->input('cancel_reason') ?? $request->input('reason');
        $remarks = $request->input('cancel_remarks') ?? $request->input('remarks', '');

        if (empty($reason)) {
            return redirect()->back()->with('error', 'The cancellation reason field is required.');
        }

        $result = $this->eInvoiceService->cancel($invoice, (string)$reason, (string)$remarks);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Generate E-Way Bill
     */
    public function generateEWayBill(Request $request, int $id)
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'transport_mode'     => ['required', 'string'],
            'transporter_id'     => ['nullable', 'string', 'max:20'],
            'transporter_name'   => ['nullable', 'string', 'max:150'],
            'vehicle_no'         => ['required_if:transport_mode,1,road', 'nullable', 'string', 'max:20'],
            'vehicle_type'       => ['required_if:transport_mode,1,road', 'nullable', 'string', 'in:R,O,regular,odc'],
            'transport_doc_no'   => ['nullable', 'string', 'max:50'],
            'transport_doc_date' => ['nullable', 'date'],
            'distance_km'        => ['required', 'numeric', 'min:1', 'max:4000'],
        ]);

        $result = $this->eWayBillService->generate($invoice, $validated);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Cancel E-Way Bill
     */
    public function cancelEWayBill(Request $request, int $id): RedirectResponse
    {
        $invoice = Invoice::findOrFail($id);
        $this->authorize('update', $invoice);

        $reason = $request->input('cancel_reason') ?? $request->input('reason');
        $remarks = $request->input('cancel_remarks') ?? $request->input('remarks', '');

        if (empty($reason)) {
            return redirect()->back()->with('error', 'The cancellation reason field is required.');
        }

        $result = $this->eWayBillService->cancel($invoice, (string)$reason, (string)$remarks);

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Export Invoice in Official Government NIC GEPP JSON Format (for direct portal bulk upload)
     */
    public function exportJson(Request $request, int $id)
    {
        $invoice = Invoice::with(['items.product', 'customer', 'salesOrder.customer'])->findOrFail($id);
        $this->authorize('view', $invoice);

        $jsonData = $this->eInvoiceService->buildNicGeppJson($invoice);
        $jsonString = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $filename = 'EInvoice_' . str_replace(['/', '\\', ' '], '_', $invoice->invoice_number) . '_NIC.json';

        return response($jsonString, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
