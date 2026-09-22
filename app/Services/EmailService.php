<?php

namespace App\Services;

use App\Models\EmailConfiguration;
use App\Models\EmailMessage;
use App\Models\EmailAttachment;
use App\Mail\QuotationMailable;
use App\Mail\InvoiceMailable;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Sales\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send an Outbound Email via configured SMTP.
     */
    public function sendEmail(array $data, array $uploadedFiles = []): EmailMessage
    {
        $toEmail = is_array($data['to']) ? implode(', ', $data['to']) : $data['to'];
        $subject = $data['subject'] ?? '(No Subject)';
        $bodyHtml = $data['body_html'] ?? $data['body'] ?? '';
        $bodyPlain = strip_tags($bodyHtml);

        $account = null;
        if (!empty($data['account_id'])) {
            $account = EmailConfiguration::whereKey($data['account_id'])
                ->where('tenant_id', current_tenant_id())
                ->where('is_active', true)
                ->first();
        }
        if (!$account) {
            $account = EmailConfiguration::forCurrentContext()->where('is_default', true)->where('is_active', true)->first()
                    ?: EmailConfiguration::forCurrentContext()->where('is_active', true)->first()
                    ?: EmailConfiguration::where('is_active', true)->first();
        }

        if (!$account || empty($account->host) || empty($account->username)) {
            throw new \RuntimeException('No active Email SMTP account found in Database for the current Tenant/Company/Branch. Please configure SMTP under Email Settings.');
        }

        $fromEmail = $account->email_address;
        $fromName = $account->from_name ?: $account->name;

        // Dynamic Mail Configuration strictly from DB Account
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $account->host,
            'mail.mailers.smtp.port' => (int) $account->port,
            'mail.mailers.smtp.encryption' => $account->encryption === 'none' ? null : ($account->encryption ?: 'tls'),
            'mail.mailers.smtp.username' => $account->username,
            'mail.mailers.smtp.password' => $account->password,
            'mail.from.address' => $fromEmail,
            'mail.from.name' => $fromName,
        ]);
        Mail::purge('smtp');

        $threadId = $data['thread_id'] ?? (string) Str::uuid();

        // Dispatch via Laravel Mail
        Mail::send([], [], function ($msg) use ($toEmail, $fromEmail, $fromName, $subject, $bodyHtml, $uploadedFiles) {
            $msg->to($toEmail)
                ->from($fromEmail, $fromName)
                ->subject($subject)
                ->html($bodyHtml);

            foreach ($uploadedFiles as $file) {
                if (is_array($file) && isset($file['path'], $file['name'])) {
                    $msg->attach($file['path'], [
                        'as' => $file['name'],
                        'mime' => $file['mime'] ?? 'application/pdf',
                    ]);
                } elseif (is_object($file) && method_exists($file, 'getRealPath')) {
                    $msg->attach($file->getRealPath(), [
                        'as' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                    ]);
                }
            }
        });

        // Save sent email record
        $emailRecord = EmailMessage::create([
            'email_configuration_id' => $account?->id,
            'thread_id' => $threadId,
            'message_id' => '<' . Str::uuid() . '@' . parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) . '>',
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_plain' => $bodyPlain,
            'is_read' => true,
            'has_attachments' => !empty($uploadedFiles),
            'customer_email' => EmailMessage::extractCleanEmail($toEmail),
            'received_at' => now(),
        ]);

        return $emailRecord;
    }

    /**
     * Generate Quotation PDF and send via SMTP with attachment
     */
    public function sendQuotationEmail(Quotation $quotation, array $data): EmailMessage
    {
        $quotation->load(['items.product', 'deal.contact', 'account', 'lead']);

        // Generate PDF Binary using Dompdf
        $pdf = Pdf::loadView('modules.crm.quotations.pdf', compact('quotation'));
        $pdfBinary = $pdf->output();
        $pdfFileName = "Quotation_{$quotation->quotation_number}.pdf";

        $toEmail = $data['to_email'] ?? ($quotation->email ?: ($quotation->prepared_for_email !== '—' ? $quotation->prepared_for_email : ($quotation->deal?->contact?->email ?: $quotation->lead?->email)));
        if (empty($toEmail)) {
            throw new \InvalidArgumentException("No valid client email address found for Quotation {$quotation->quotation_number}.");
        }

        $subject = $data['subject'] ?? "Quotation {$quotation->quotation_number} - " . ($quotation->deal?->title ?? config('app.name'));
        $bodyHtml = $data['body_html'] ?? $data['body'] ?? "Dear Valued Client,\n\nPlease find attached Quotation {$quotation->quotation_number} for your review.\n\nBest regards,\nSales Team";

        $account = null;
        if (!empty($data['account_id'])) {
            $account = EmailConfiguration::whereKey($data['account_id'])
                ->where('tenant_id', current_tenant_id())
                ->where('is_active', true)
                ->first();
        }
        if (!$account) {
            $account = EmailConfiguration::forCurrentContext()->where('is_default', true)->where('is_active', true)->first()
                    ?: EmailConfiguration::forCurrentContext()->where('is_active', true)->first()
                    ?: EmailConfiguration::where('is_active', true)->first();
        }

        if (!$account || empty($account->host) || empty($account->username)) {
            throw new \RuntimeException('No active Email SMTP account found in Database for the current Tenant/Company/Branch. Please configure SMTP under Email Settings.');
        }

        $fromEmail = $account->email_address;
        $fromName = $account->from_name ?: $account->name;

        // Dynamic Mail configuration strictly from DB Account
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $account->host,
            'mail.mailers.smtp.port' => (int) $account->port,
            'mail.mailers.smtp.encryption' => $account->encryption === 'none' ? null : ($account->encryption ?: 'tls'),
            'mail.mailers.smtp.username' => $account->username,
            'mail.mailers.smtp.password' => $account->password,
            'mail.from.address' => $fromEmail,
            'mail.from.name' => $fromName,
        ]);
        Mail::purge('smtp');

        $mailable = new QuotationMailable($subject, nl2br($bodyHtml), $pdfBinary, $pdfFileName);
        Mail::to($toEmail)->send($mailable);

        // Update Quotation Status if Draft
        if ($quotation->status === 'Draft') {
            $quotation->update(['status' => 'Quotation Sent']);
        }

        // Log sent message
        $emailRecord = EmailMessage::create([
            'email_configuration_id' => $account?->id,
            'thread_id' => (string) Str::uuid(),
            'message_id' => '<' . Str::uuid() . '@' . parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) . '>',
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => $subject,
            'body_html' => nl2br($bodyHtml),
            'body_plain' => strip_tags($bodyHtml),
            'is_read' => true,
            'has_attachments' => true,
            'customer_email' => EmailMessage::extractCleanEmail($toEmail),
            'received_at' => now(),
        ]);

        return $emailRecord;
    }

    /**
     * Generate Invoice PDF and send via SMTP with attachment
     */
    public function sendInvoiceEmail(Invoice $invoice, array $data, $customPdfFile = null): EmailMessage
    {
        $invoice->load(['items.product', 'customer', 'salesOrder.customer', 'allocations']);

        if ($customPdfFile && method_exists($customPdfFile, 'isValid') && $customPdfFile->isValid()) {
            $pdfBinary = file_get_contents($customPdfFile->getRealPath());
            $pdfFileName = $customPdfFile->getClientOriginalName() ?: "Tax_Invoice_{$invoice->invoice_number}.pdf";
        } else {
            $adjustedAmount = $invoice->allocations->sum('allocated_amount');
            $balanceDue     = $invoice->balance_due;

            // Generate PDF Binary using Dompdf
            $pdf = Pdf::loadView('modules.sales.invoices.pdf', compact('invoice', 'adjustedAmount', 'balanceDue'));
            $pdfBinary = $pdf->output();
            $pdfFileName = "Tax_Invoice_{$invoice->invoice_number}.pdf";
        }

        $toEmail = $data['to_email'] ?? ($invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: null));
        if (empty($toEmail)) {
            throw new \InvalidArgumentException("No valid client email address found for Invoice {$invoice->invoice_number}.");
        }

        $companyName = tenant() ? tenant()->name : config('app.name');
        $subject = $data['subject'] ?? "Tax Invoice {$invoice->invoice_number} - {$companyName}";
        $bodyHtml = $data['body_html'] ?? $data['body'] ?? "Dear Valued Customer,\n\nPlease find attached Tax Invoice {$invoice->invoice_number} for your records.\n\nTotal Amount: " . format_currency($invoice->total_amount) . "\nBalance Due: " . format_currency($invoice->balance_due) . "\n\nBest regards,\n" . $companyName;

        $account = null;
        if (!empty($data['account_id'])) {
            $account = EmailConfiguration::whereKey($data['account_id'])
                ->where('tenant_id', current_tenant_id())
                ->where('is_active', true)
                ->first();
        }
        if (!$account) {
            $account = EmailConfiguration::forCurrentContext()->where('is_default', true)->where('is_active', true)->first()
                    ?: EmailConfiguration::forCurrentContext()->where('is_active', true)->first()
                    ?: EmailConfiguration::where('is_active', true)->first();
        }

        if (!$account || empty($account->host) || empty($account->username)) {
            throw new \RuntimeException('No active Email SMTP account found in Database for the current Tenant/Company/Branch. Please configure SMTP under Email Settings.');
        }

        $fromEmail = $account->email_address;
        $fromName = $account->from_name ?: $account->name;

        // Dynamic Mail configuration strictly from DB Account
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $account->host,
            'mail.mailers.smtp.port' => (int) $account->port,
            'mail.mailers.smtp.encryption' => $account->encryption === 'none' ? null : ($account->encryption ?: 'tls'),
            'mail.mailers.smtp.username' => $account->username,
            'mail.mailers.smtp.password' => $account->password,
            'mail.from.address' => $fromEmail,
            'mail.from.name' => $fromName,
        ]);
        Mail::purge('smtp');

        $mailable = new InvoiceMailable($subject, nl2br($bodyHtml), $pdfBinary, $pdfFileName);
        Mail::to($toEmail)->send($mailable);

        // Update Invoice Status if Draft to Sent
        if ($invoice->status === 'Draft') {
            $invoice->update(['status' => 'Sent']);
        }

        // Log sent message
        $emailRecord = EmailMessage::create([
            'email_configuration_id' => $account?->id,
            'thread_id' => (string) Str::uuid(),
            'message_id' => '<' . Str::uuid() . '@' . parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) . '>',
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_name' => $fromName,
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => $subject,
            'body_html' => nl2br($bodyHtml),
            'body_plain' => strip_tags($bodyHtml),
            'is_read' => true,
            'has_attachments' => true,
            'customer_email' => EmailMessage::extractCleanEmail($toEmail),
            'received_at' => now(),
        ]);

        return $emailRecord;
    }

    /**
     * Test SMTP and IMAP Socket Connections with live Authentication.
     */
    public function testConnections(EmailConfiguration $account): array
    {
        // 1. Test SMTP
        $smtp = $this->openSocket($account->host, (int) $account->port, $account->encryption === 'ssl');
        $this->expectSmtp($smtp, [220]);
        fwrite($smtp, "EHLO localhost\r\n");
        $this->expectSmtp($smtp, [250]);

        if ($account->encryption === 'tls') {
            fwrite($smtp, "STARTTLS\r\n");
            $this->expectSmtp($smtp, [220]);
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('SMTP TLS handshake failed.');
            }
            fwrite($smtp, "EHLO localhost\r\n");
            $this->expectSmtp($smtp, [250]);
        }

        fwrite($smtp, "AUTH LOGIN\r\n");
        $this->expectSmtp($smtp, [334]);
        fwrite($smtp, base64_encode((string) $account->username) . "\r\n");
        $this->expectSmtp($smtp, [334]);
        fwrite($smtp, base64_encode((string) $account->password) . "\r\n");
        $this->expectSmtp($smtp, [235]);
        fwrite($smtp, "QUIT\r\n");
        fclose($smtp);

        // 2. Test IMAP if incoming host configured
        $imapTested = false;
        if (!empty($account->incoming_host)) {
            $imap = $this->openSocket($account->incoming_host, (int) $account->incoming_port, $account->incoming_encryption === 'ssl');
            fgets($imap);
            $user = '"' . addcslashes($account->incoming_username ?: $account->email_address, "\\\"") . '"';
            $password = '"' . addcslashes((string) $account->incoming_password, "\\\"") . '"';
            fwrite($imap, "T1 LOGIN {$user} {$password}\r\n");
            
            $response = '';
            while (($line = fgets($imap)) !== false) {
                $response .= $line;
                if (str_starts_with($line, 'T1 ')) break;
            }
            fclose($imap);

            if (!str_contains($response, 'T1 OK')) {
                throw new \RuntimeException('IMAP credentials authentication failed.');
            }
            $imapTested = true;
        }

        return ['smtp' => true, 'imap' => $imapTested];
    }

    private function openSocket(?string $host, int $port, bool $ssl)
    {
        if (!$host || $port < 1) throw new \RuntimeException('Invalid host or port.');
        $proto = $ssl ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($proto . $host . ':' . $port, $errno, $errstr, 10);
        if (!$socket) throw new \RuntimeException("Socket connection failed to {$host}:{$port} - {$errstr} ({$errno})");
        stream_set_timeout($socket, 10);
        return $socket;
    }

    private function expectSmtp($socket, array $codes): string
    {
        $res = '';
        while (($line = fgets($socket)) !== false) {
            $res .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        $code = (int) substr($res, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException("SMTP rejected command (code {$code}). Response: {$res}");
        }
        return $res;
    }
}
