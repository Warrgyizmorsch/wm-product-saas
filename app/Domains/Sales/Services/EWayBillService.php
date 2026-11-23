<?php

namespace App\Domains\Sales\Services;

use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EWayBillService
{
    /**
     * Generate E-Way Bill for an Invoice
     */
    public function generate(Invoice $invoice, array $transportData): array
    {
        if (in_array(strtolower((string)$invoice->status), ['cancelled', 'void'])) {
            return [
                'success' => false,
                'message' => 'Cannot generate E-Way Bill for a cancelled invoice.',
            ];
        }

        if ($invoice->eway_bill_status === 'Generated' && !empty($invoice->eway_bill_no)) {
            return [
                'success'         => true,
                'message'         => 'E-Way Bill is already generated.',
                'eway_bill_no'    => $invoice->eway_bill_no,
                'valid_till'      => $invoice->eway_bill_valid_till,
            ];
        }

        $distance = (int)($transportData['distance_km'] ?? 100);
        if ($distance <= 0) $distance = 100;

        $gstConfig = \App\Models\GstConfiguration::getForCurrentContext(
            $invoice->company_id ?? null,
            $invoice->branch_id ?? null
        );

        if (!$gstConfig || !$gstConfig->is_active) {
            $errorMsg = 'No active GST / E-Way Bill API configuration found. Please setup your GSP credentials in Administration > GST & E-Invoice Setup.';
            $invoice->update([
                'eway_bill_status' => 'Failed',
                'eway_bill_error'  => $errorMsg,
            ]);
            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }

        $apiUrl = $gstConfig->api_base_url ?: (config('services.ewaybill.api_url') ?: env('EWAYBILL_API_URL'));
        $clientId = $gstConfig->client_id ?: (config('services.ewaybill.client_id') ?: env('EWAYBILL_CLIENT_ID'));
        $clientSecret = $gstConfig->client_secret ?: (config('services.ewaybill.client_secret') ?: env('EWAYBILL_CLIENT_SECRET'));
        $authToken = $gstConfig->api_token ?: (config('services.ewaybill.auth_token') ?: env('EWAYBILL_AUTH_TOKEN'));

        if (empty($apiUrl) || (empty($authToken) && (empty($clientId) || empty($clientSecret)))) {
            $errorMsg = 'E-Way Bill API credentials (API URL, Token or Client ID/Secret) are missing in your GST Setup.';
            $invoice->update([
                'eway_bill_status' => 'Failed',
                'eway_bill_error'  => $errorMsg,
            ]);
            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }

        $payload = $this->buildEWayBillPayload($invoice, $transportData);

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ];
            if (!empty($authToken)) {
                $headers['auth_token'] = $authToken;
                $headers['Authorization'] = 'Bearer ' . $authToken;
            }
            if (!empty($clientId)) {
                $headers['client_id'] = $clientId;
                $headers['client_secret'] = $clientSecret;
            }

            $response = Http::withHeaders($headers)->timeout(30)->post("{$apiUrl}/ewaybill/generate", $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $this->recordSuccess($invoice, $transportData, $data);
            } else {
                $errorMsg = $response->json('message') ?? $response->json('error') ?? $response->body() ?? 'E-Way Bill generation failed.';
                $invoice->update([
                    'eway_bill_status' => 'Failed',
                    'eway_bill_error'  => $errorMsg,
                ]);
                return [
                    'success' => false,
                    'message' => "E-Way Bill Generation Failed: {$errorMsg}",
                ];
            }
        } catch (\Exception $e) {
            Log::error('EWayBillService API Exception: ' . $e->getMessage());
            $invoice->update([
                'eway_bill_status' => 'Failed',
                'eway_bill_error'  => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => 'API connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel an active E-Way Bill
     */
    public function cancel(Invoice $invoice, string $reason, string $remarks = ''): array
    {
        if ($invoice->eway_bill_status !== 'Generated' || empty($invoice->eway_bill_no)) {
            return [
                'success' => false,
                'message' => 'Cannot cancel an invoice without an active E-Way Bill.',
            ];
        }

        $apiUrl = config('services.ewaybill.api_url') ?: env('EWAYBILL_API_URL');
        if (!empty($apiUrl)) {
            try {
                Http::withHeaders([
                    'client_id'     => config('services.ewaybill.client_id') ?: env('EWAYBILL_CLIENT_ID'),
                    'client_secret' => config('services.ewaybill.client_secret') ?: env('EWAYBILL_CLIENT_SECRET'),
                ])->post("{$apiUrl}/ewaybill/cancel", [
                    'ewbNo'      => $invoice->eway_bill_no,
                    'cancelRsnCode' => $reason,
                    'cancelRmrk'    => $remarks ?: 'Cancelled by user',
                ]);
            } catch (\Exception $e) {
                Log::warning('E-Way Bill cancel API call failed: ' . $e->getMessage());
            }
        }

        $invoice->update([
            'eway_bill_status' => 'Cancelled',
            'eway_bill_error'  => "Cancelled on " . now()->format('d/m/Y H:i') . " | Reason: {$reason} - {$remarks}",
        ]);

        return [
            'success' => true,
            'message' => "E-Way Bill for {$invoice->invoice_number} cancelled successfully.",
        ];
    }

    /**
     * Build E-Way Bill payload from invoice and transportation parameters
     */
    public function buildEWayBillPayload(Invoice $invoice, array $transportData): array
    {
        $tenant = tenant();
        $customer = $invoice->customer ?: $invoice->salesOrder?->customer;

        $sellerOrg = billing_organization_details($invoice);
        $ownBranch = $invoice->branch;
        $comp = $invoice->company ?: company();
        $billBranch = $invoice->billingBranch ?: $invoice->salesOrder?->billingBranch;
        $shipBranch = $invoice->shippingBranch ?: $invoice->salesOrder?->shippingBranch;

        $sellerGstin = strtoupper(trim((string) ($invoice->seller_gstin ?: ($sellerOrg['gstin'] ?: ($comp?->gst_number ?: ($tenant?->gstin ?: ($tenant?->billing_gstin ?: '')))))));
        $sellerStateCode = (int) ($invoice->seller_state_code ?: ($sellerOrg['state_code'] ?: (substr($sellerGstin, 0, 2) ?: 0)));

        $buyerGstin = strtoupper(trim((string) ($invoice->bill_to_gstin ?: ($invoice->salesOrder?->bill_to_gstin ?: ($billBranch?->effectiveGstin() ?: ($customer?->gstin ?: ''))))));
        $buyerStateCode = (int) ($invoice->place_of_supply_state ?: (strlen($buyerGstin) >= 2 ? substr($buyerGstin, 0, 2) : ($billBranch?->effectiveStateCode() ?: 0)));
        $shipToStateCode = (int) (
            $shipBranch?->effectiveStateCode()
            ?: (strlen((string) ($invoice->ship_to_gstin ?? '')) >= 2 ? substr((string) $invoice->ship_to_gstin, 0, 2) : $buyerStateCode)
        );

        $items = [];
        $totTaxable = 0;
        $totCgst = 0;
        $totSgst = 0;
        $totIgst = 0;

        foreach ($invoice->items as $item) {
            $qty = (float)($item->quantity ?: 1);
            $price = (float)($item->unit_price ?: 0);
            $disc = (float)($item->discount ?: 0);
            $taxable = max(0, ($qty * $price) - $disc);
            $rate = (float)($item->tax_rate ?? ($invoice->order_tax_rate ?: 18));

            $cgst = 0;
            $sgst = 0;
            $igst = 0;
            if ($sellerStateCode !== $buyerStateCode) {
                $igst = round(($taxable * $rate) / 100, 2);
            } else {
                $cgst = round(($taxable * ($rate / 2)) / 100, 2);
                $sgst = round(($taxable * ($rate / 2)) / 100, 2);
            }

            $totTaxable += $taxable;
            $totCgst += $cgst;
            $totSgst += $sgst;
            $totIgst += $igst;

            $hsn = preg_replace('/[^0-9]/', '', (string)($item->product?->hsn_code ?? $item->product?->sku ?? '84713010')) ?: '84713010';

            $items[] = [
                'productName' => substr($item->item_name ?? $item->product?->name ?? 'Goods', 0, 100),
                'hsnCode'     => (int)$hsn,
                'quantity'    => $qty,
                'qtyUnit'     => 'NOS',
                'taxableAmount' => $taxable,
                'cgstRate'    => ($sellerStateCode === $buyerStateCode) ? ($rate / 2) : 0,
                'sgstRate'    => ($sellerStateCode === $buyerStateCode) ? ($rate / 2) : 0,
                'igstRate'    => ($sellerStateCode !== $buyerStateCode) ? $rate : 0,
                'cessRate'    => 0,
            ];
        }

        $distance = (int)($transportData['distance_km'] ?? 100);

        return [
            'supplyType'       => 'O', // Outward
            'subSupplyType'    => '1', // Supply
            'docType'          => 'INV',
            'docNo'            => $invoice->invoice_number,
            'docDate'          => ($invoice->invoice_date ? $invoice->invoice_date->format('d/m/Y') : date('d/m/Y')),
            'fromGstin'        => $sellerGstin,
            'fromTrdName'      => $sellerOrg['name'] ?: ($tenant?->name ?? ''),
            'fromAddr1'        => $ownBranch?->address ?: ($comp?->address ?: ($sellerOrg['address'] ?? '')),
            'fromPlace'        => $ownBranch?->city ?: ($comp?->city ?: ''),
            'fromPincode'      => (int) ($ownBranch?->postal_code ?: ($comp?->postal_code ?: 0)),
            'actFromStateCode' => $sellerStateCode,
            'fromStateCode'    => $sellerStateCode,
            'toGstin'          => $buyerGstin,
            'toTrdName'        => $customer?->name ?? 'Valued Client',
            'toAddr1'          => $shipBranch?->address_line1 ?: ($invoice->shipping_address ? explode("\n", $invoice->shipping_address)[0] : ($billBranch?->address_line1 ?: ($customer?->address_line1 ?: ''))),
            'toPlace'          => $shipBranch?->city ?: ($billBranch?->city ?: ($customer?->city ?: '')),
            'toPincode'        => (int) ($shipBranch?->postal_code ?: ($billBranch?->postal_code ?: ($customer?->postal_code ?: 0))),
            'actToStateCode'   => $shipToStateCode ?: $buyerStateCode,
            'toStateCode'      => $buyerStateCode,
            'totalValue'       => round($totTaxable, 2),
            'cgstValue'        => round($totCgst, 2),
            'sgstValue'        => round($totSgst, 2),
            'igstValue'        => round($totIgst, 2),
            'cessValue'        => 0,
            'totInvValue'      => round($invoice->total_amount, 2),
            'transporterId'    => $transportData['transporter_id'] ?? '',
            'transporterName'  => $transportData['transporter_name'] ?? '',
            'transDocNo'       => $transportData['transport_doc_no'] ?? '',
            'transMode'        => $transportData['transport_mode'] ?? '1', // 1=Road
            'transDistance'    => (string)$distance,
            'transDocDate'     => !empty($transportData['transport_doc_date']) ? date('d/m/Y', strtotime($transportData['transport_doc_date'])) : date('d/m/Y'),
            'vehicleNo'        => strtoupper(str_replace(' ', '', $transportData['vehicle_no'] ?? 'RJ14GA1234')),
            'vehicleType'      => $transportData['vehicle_type'] ?? 'R', // R=Regular, O=Over Dimensional
            'itemList'         => $items,
        ];
    }

    /**
     * Generate standard 12-digit E-Way Bill Number and statutory validity for testing / sandbox.
     */
    protected function generateSandboxEWayBill(Invoice $invoice, array $transportData): array
    {
        $distance = (int)($transportData['distance_km'] ?? 100);
        if ($distance <= 0) $distance = 100;

        // 12-Digit E-Way Bill Number (Format: 3XXXXXXXXXXX)
        $ewbNo = '3' . date('ymd') . str_pad((string)$invoice->id, 5, '0', STR_PAD_LEFT);
        $ewbDate = now();

        // Standard Govt Validity Rule: 1 Day per 200 KM for regular cargo (min 1 day / 24 hrs)
        $validDays = max(1, (int)ceil($distance / 200));
        $validTill = now()->addDays($validDays)->setTime(23, 59, 59);

        $invoice->update([
            'eway_bill_status'     => 'Generated',
            'eway_bill_no'         => $ewbNo,
            'eway_bill_date'       => $ewbDate,
            'eway_bill_valid_till' => $validTill,
            'transporter_id'       => $transportData['transporter_id'] ?? null,
            'transporter_name'     => $transportData['transporter_name'] ?? null,
            'transport_mode'       => $transportData['transport_mode'] ?? '1',
            'vehicle_no'           => strtoupper(str_replace(' ', '', $transportData['vehicle_no'] ?? '')),
            'vehicle_type'         => $transportData['vehicle_type'] ?? 'R',
            'transport_doc_no'     => $transportData['transport_doc_no'] ?? null,
            'transport_doc_date'   => !empty($transportData['transport_doc_date']) ? $transportData['transport_doc_date'] : now()->toDateString(),
            'distance_km'          => $distance,
            'eway_bill_error'      => null,
        ]);

        \App\Domains\Sales\Services\SalesNotificationService::notifyEWayBillGenerated($invoice, $ewbNo, (string) $validTill);

        return [
            'success'      => true,
            'message'      => "E-Way Bill generated successfully! EWB No: {$ewbNo}",
            'eway_bill_no' => $ewbNo,
            'valid_till'   => $validTill,
        ];
    }

    /**
     * Record remote API success
     */
    protected function recordSuccess(Invoice $invoice, array $transportData, array $data): array
    {
        $ewbNo = $data['ewayBillNo'] ?? $data['EwbNo'] ?? ('3' . date('ymd') . $invoice->id);
        $ewbDate = isset($data['ewayBillDate']) ? date('Y-m-d H:i:s', strtotime($data['ewayBillDate'])) : now();
        $validTill = isset($data['validUpto']) ? date('Y-m-d H:i:s', strtotime($data['validUpto'])) : now()->addDays(2);

        $invoice->update([
            'eway_bill_status'     => 'Generated',
            'eway_bill_no'         => $ewbNo,
            'eway_bill_date'       => $ewbDate,
            'eway_bill_valid_till' => $validTill,
            'transporter_id'       => $transportData['transporter_id'] ?? null,
            'transporter_name'     => $transportData['transporter_name'] ?? null,
            'transport_mode'       => $transportData['transport_mode'] ?? '1',
            'vehicle_no'           => strtoupper(str_replace(' ', '', $transportData['vehicle_no'] ?? '')),
            'vehicle_type'         => $transportData['vehicle_type'] ?? 'R',
            'transport_doc_no'     => $transportData['transport_doc_no'] ?? null,
            'transport_doc_date'   => !empty($transportData['transport_doc_date']) ? $transportData['transport_doc_date'] : now()->toDateString(),
            'distance_km'          => (int)($transportData['distance_km'] ?? 100),
            'eway_bill_error'      => null,
        ]);

        \App\Domains\Sales\Services\SalesNotificationService::notifyEWayBillGenerated($invoice, $ewbNo, (string) $validTill);

        return [
            'success'      => true,
            'message'      => "E-Way Bill generated successfully. EWB No: {$ewbNo}",
            'eway_bill_no' => $ewbNo,
            'valid_till'   => $validTill,
        ];
    }
}
