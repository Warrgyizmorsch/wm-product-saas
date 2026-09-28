<?php

namespace App\Services;

use App\Domains\CRM\Models\Quotation;
use App\Domains\Sales\Models\Invoice;
use App\Models\WhatsAppConfiguration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public function getConfig(): WhatsAppConfiguration
    {
        return WhatsAppConfiguration::getForCurrentContext();
    }

    private function sessionKey(): string
    {
        return $this->getConfig()->session_key;
    }

    private function getBridgeUrl(): string
    {
        $url = $this->getConfig()->bridge_url ?: 'http://127.0.0.1:3210';
        return rtrim($url, '/');
    }

    private function getBridgeToken(): string
    {
        return $this->getConfig()->bridge_token ?: 'wm_erp_whatsapp_secret_token_2026';
    }

    public function getStatus(): array
    {
        $config = $this->getConfig();
        $url = $this->getBridgeUrl() . '/sessions/' . $config->session_key;

        try {
            $response = Http::acceptJson()
                ->withToken($this->getBridgeToken())
                ->timeout(5)
                ->get($url);

            $data = $response->json() ?: ['status' => 'disconnected', 'qr' => null];

            // Update Database record with live state
            if (isset($data['status'])) {
                $updateData = ['status' => $data['status']];
                if (!empty($data['user']['id'])) {
                    $updateData['phone_number'] = preg_replace('/\D/', '', explode(':', $data['user']['id'])[0]);
                }
                if (!empty($data['user']['name'])) {
                    $updateData['account_name'] = $data['user']['name'];
                }
                $config->update($updateData);
            }

            return $data;
        } catch (ConnectionException $e) {
            $config->update(['status' => 'offline']);
            return [
                'status'  => 'offline',
                'message' => 'WhatsApp bridge service is offline. Please start the Node.js bridge server.',
            ];
        }
    }

    public function connect(): array
    {
        $config = $this->getConfig();
        $url = $this->getBridgeUrl() . '/sessions/' . $config->session_key . '/connect';

        try {
            $response = Http::acceptJson()
                ->withToken($this->getBridgeToken())
                ->timeout(10)
                ->post($url);

            $data = $response->json() ?: ['status' => 'disconnected'];
            if (isset($data['status'])) {
                $config->update(['status' => $data['status']]);
            }
            return $data;
        } catch (ConnectionException $e) {
            $config->update(['status' => 'offline']);
            return ['status' => 'offline', 'message' => 'WhatsApp bridge service is offline.'];
        }
    }

    public function disconnect(): array
    {
        $config = $this->getConfig();
        $url = $this->getBridgeUrl() . '/sessions/' . $config->session_key;

        try {
            $response = Http::acceptJson()
                ->withToken($this->getBridgeToken())
                ->timeout(10)
                ->delete($url);

            $config->update(['status' => 'disconnected', 'phone_number' => null, 'account_name' => null]);
            return $response->json() ?: ['status' => 'disconnected'];
        } catch (ConnectionException $e) {
            return ['status' => 'offline', 'message' => 'WhatsApp bridge service is offline.'];
        }
    }

    public function sendMessage(string $mobile, string $message, ?string $quotedMsgId = null): array
    {
        $mobileTrim = trim($mobile);
        if (str_contains($mobileTrim, '@lid') || str_contains($mobileTrim, '@s.whatsapp.net')) {
            $targetNumber = $mobileTrim;
        } else {
            $cleanNumber = preg_replace('/\D/', '', $mobileTrim);
            if (str_starts_with($cleanNumber, '0')) {
                $cleanNumber = preg_replace('/^0+/', '', $cleanNumber);
            }
            if (strlen($cleanNumber) === 10) {
                $cleanNumber = '91' . $cleanNumber;
            }
            $targetNumber = $cleanNumber;
        }

        $config = $this->getConfig();
        $url = $this->getBridgeUrl() . '/sessions/' . $config->session_key . '/send-message';

        $payload = [
            'number'  => $targetNumber,
            'message' => $message,
        ];
        if ($quotedMsgId) {
            $payload['message_id'] = $quotedMsgId;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($this->getBridgeToken())
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $resData = $response->json();
                try {
                    \App\Models\WhatsAppMessage::create([
                        'tenant_id'     => $config->tenant_id,
                        'company_id'    => $config->company_id,
                        'branch_id'     => $config->branch_id,
                        'session_key'   => $config->session_key,
                        'sender_number' => $targetNumber,
                        'sender_name'   => 'Outbound ERP',
                        'direction'     => 'outbound',
                        'message_type'  => 'text',
                        'message_body'  => $message,
                        'message_id'    => $resData['messageId'] ?? null,
                        'status'        => 'sent',
                        'received_at'   => now(),
                    ]);
                } catch (\Throwable $e) {}

                return [
                    'success' => true,
                    'message' => "✓ WhatsApp test message successfully sent to {$targetNumber}!",
                    'data'    => $resData,
                ];
            }

            $resJson = $response->json() ?: [];
            return [
                'success' => false,
                'message' => $resJson['message'] ?? 'Failed to send WhatsApp message.',
                'status'  => $resJson['status'] ?? 'error',
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'WhatsApp bridge server is offline or unreachable.',
                'status'  => 'offline',
            ];
        }
    }

    public function sendDocument(string $mobile, string $binary, string $filename, string $caption = '', string $mimetype = 'application/pdf'): array
    {
        $mobileTrim = trim($mobile);
        if (str_contains($mobileTrim, '@lid') || str_contains($mobileTrim, '@s.whatsapp.net')) {
            $targetNumber = $mobileTrim;
        } else {
            $cleanNumber = preg_replace('/\D/', '', $mobileTrim);
            if (str_starts_with($cleanNumber, '0')) {
                $cleanNumber = preg_replace('/^0+/', '', $cleanNumber);
            }
            if (strlen($cleanNumber) === 10) {
                $cleanNumber = '91' . $cleanNumber;
            }
            $targetNumber = $cleanNumber;
        }

        $config = $this->getConfig();
        $url = $this->getBridgeUrl() . '/sessions/' . $config->session_key . '/send-document';

        try {
            $response = Http::acceptJson()
                ->withToken($this->getBridgeToken())
                ->timeout(45)
                ->post($url, [
                    'number'   => $targetNumber,
                    'filename' => $filename,
                    'mimetype' => $mimetype,
                    'caption'  => $caption,
                    'document' => base64_encode($binary),
                ]);

            if ($response->successful()) {
                $resData = $response->json();
                try {
                    \App\Models\WhatsAppMessage::create([
                        'tenant_id'     => $config->tenant_id,
                        'company_id'    => $config->company_id,
                        'branch_id'     => $config->branch_id,
                        'session_key'   => $config->session_key,
                        'sender_number' => $targetNumber,
                        'sender_name'   => 'Outbound ERP',
                        'direction'     => 'outbound',
                        'message_type'  => 'document',
                        'message_body'  => "[Document: {$filename}] " . $caption,
                        'message_id'    => $resData['messageId'] ?? null,
                        'status'        => 'sent',
                        'received_at'   => now(),
                    ]);
                } catch (\Throwable $e) {}

                return [
                    'success' => true,
                    'message' => "✓ WhatsApp document successfully sent to {$targetNumber}!",
                    'data'    => $resData,
                ];
            }

            $resJson = $response->json() ?: [];
            return [
                'success' => false,
                'message' => $resJson['message'] ?? 'Failed to send WhatsApp document.',
                'status'  => $resJson['status'] ?? 'error',
            ];
        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'message' => 'WhatsApp bridge server is offline or unreachable. Please start Node.js bridge server.',
                'status'  => 'offline',
            ];
        }
    }

    public function sendQuotation(Quotation $quotation, string $mobile, ?string $customCaption = null, $customPdfFile = null): array
    {
        $quotation->load(['items.product', 'lead', 'crmDeal', 'crmAccount']);

        if ($customPdfFile && method_exists($customPdfFile, 'isValid') && $customPdfFile->isValid()) {
            $pdfBinary = file_get_contents($customPdfFile->getRealPath());
            $filename = $customPdfFile->getClientOriginalName() ?: "Quotation_{$quotation->quotation_number}.pdf";
        } else {
            $pdf = Pdf::loadView('modules.crm.quotations.pdf', compact('quotation'));
            $pdfBinary = $pdf->output();
            $filename = "Quotation_{$quotation->quotation_number}.pdf";
        }

        $dealTitle = $quotation->crmDeal?->title ?: ($quotation->lead?->title ?: 'your enquiry');
        $totalFormatted = format_currency($quotation->total_amount);

        $defaultCaption = "Dear Valued Client,\n\n"
            . "Please find attached Quotation *{$quotation->quotation_number}* for *{$dealTitle}* (Total: *{$totalFormatted}*).\n\n"
            . "👉 *Please respond with one of the options below:*\n"
            . "1️⃣ Reply *1* or *ACCEPT* to Accept Quotation\n"
            . "2️⃣ Reply *2* or *REJECT [reason]* to Reject Quotation\n\n"
            . "Thank you,\nSales Team";

        $caption = $customCaption ?: $defaultCaption;

        $updateData = ['phone' => $mobile];
        if (in_array($quotation->status, ['Draft', 'Approved', 'Pending Approval'])) {
            $updateData['status'] = 'Quotation Sent';
        }
        $quotation->update($updateData);

        if (isset($updateData['status'])) {
            try {
                app(\App\Domains\CRM\Services\QuotationService::class)->handleQuotationStatusChange($quotation, 'Quotation Sent');
            } catch (\Throwable $e) {}
        }

        return $this->sendDocument(
            mobile: $mobile,
            binary: $pdfBinary,
            filename: $filename,
            caption: $caption
        );
    }

    public function sendInvoice(Invoice $invoice, string $mobile, ?string $customCaption = null, $customPdfFile = null): array
    {
        $invoice->load(['items.product', 'customer', 'salesOrder.customer', 'allocations']);

        if ($customPdfFile && method_exists($customPdfFile, 'isValid') && $customPdfFile->isValid()) {
            $pdfBinary = file_get_contents($customPdfFile->getRealPath());
            $filename = $customPdfFile->getClientOriginalName() ?: "Tax_Invoice_{$invoice->invoice_number}.pdf";
        } else {
            $adjustedAmount = $invoice->allocations->sum('allocated_amount');
            $balanceDue     = $invoice->balance_due;

            $pdf = Pdf::loadView('modules.sales.invoices.pdf', compact('invoice', 'adjustedAmount', 'balanceDue'));
            $pdfBinary = $pdf->output();
            $filename = "Tax_Invoice_{$invoice->invoice_number}.pdf";
        }

        $companyName = tenant() ? tenant()->name : 'Accounts Team';
        $totalFormatted = format_currency($invoice->total_amount);
        $balanceFormatted = format_currency($invoice->balance_due);

        $defaultCaption = "Dear Valued Customer,\n\n"
            . "Please find attached Tax Invoice *{$invoice->invoice_number}*.\n"
            . "💰 *Total Amount:* {$totalFormatted}\n"
            . "💳 *Balance Due:* {$balanceFormatted}\n"
            . "📅 *Due Date:* " . ($invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : 'Immediate') . "\n\n"
            . "Thank you for your business!\n*{$companyName}*";

        $caption = $customCaption ?: $defaultCaption;

        if ($invoice->status === 'Draft') {
            $invoice->update(['status' => 'Sent']);
        }

        return $this->sendDocument(
            mobile: $mobile,
            binary: $pdfBinary,
            filename: $filename,
            caption: $caption
        );
    }
}

