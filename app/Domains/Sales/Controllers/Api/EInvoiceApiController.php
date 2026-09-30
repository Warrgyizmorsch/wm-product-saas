<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Services\EInvoiceService;
use App\Domains\Sales\Services\EWayBillService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EInvoiceApiController extends Controller
{
    public function __construct(
        protected EInvoiceService $eInvoiceService,
        protected EWayBillService $eWayBillService
    ) {}

    /**
     * POST /api/sales/invoices/{id}/einvoice/generate
     * Generate E-Invoice (IRN, QR Code, Ack No)
     */
    public function generateEInvoice(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => "Invoice #{$id} not found."], 404);
        }

        $this->authorize('update', $invoice);

        $result = $this->eInvoiceService->generate($invoice);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/sales/invoices/{id}/einvoice/cancel
     * Cancel E-Invoice (IRN)
     */
    public function cancelEInvoice(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => "Invoice #{$id} not found."], 404);
        }

        $this->authorize('update', $invoice);

        $validator = Validator::make($request->all(), [
            'reason'  => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation reason is required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = $this->eInvoiceService->cancel($invoice, $request->input('reason'), (string)$request->input('remarks', ''));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/sales/invoices/{id}/eway-bill/generate
     * Generate GST compliant E-Way Bill
     */
    public function generateEWayBill(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => "Invoice #{$id} not found."], 404);
        }

        $this->authorize('update', $invoice);

        $validator = Validator::make($request->all(), [
            'transport_mode'     => ['required', 'string'],
            'transporter_id'     => ['nullable', 'string', 'max:20'],
            'transporter_name'   => ['nullable', 'string', 'max:150'],
            'vehicle_no'         => ['nullable', 'string', 'max:20'],
            'vehicle_type'       => ['nullable', 'string', 'in:R,O,regular,odc'],
            'transport_doc_no'   => ['nullable', 'string', 'max:50'],
            'transport_doc_date' => ['nullable', 'date'],
            'distance_km'        => ['required', 'numeric', 'min:1', 'max:4000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = $this->eWayBillService->generate($invoice, $validator->validated());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /api/sales/invoices/{id}/eway-bill/cancel
     * Cancel E-Way Bill
     */
    public function cancelEWayBill(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return response()->json(['success' => false, 'message' => "Invoice #{$id} not found."], 404);
        }

        $this->authorize('update', $invoice);

        $validator = Validator::make($request->all(), [
            'reason'  => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation reason is required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $result = $this->eWayBillService->cancel($invoice, $request->input('reason'), (string)$request->input('remarks', ''));

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/sales/invoices/{id}/einvoice/export-json
     * Export Invoice in Official Government NIC GEPP JSON Format
     */
    public function exportJson(Request $request, int $id)
    {
        $invoice = Invoice::with(['items.product', 'customer', 'salesOrder.customer'])->find($id);
        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }
        $this->authorize('view', $invoice);

        $jsonData = $this->eInvoiceService->buildNicGeppJson($invoice);

        return response()->json([
            'success' => true,
            'data'    => $jsonData,
        ]);
    }
}
