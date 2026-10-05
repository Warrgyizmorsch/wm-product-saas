<?php

namespace App\Domains\Projects\Jobs;

use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendProjectNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $toEmail,
        public readonly string $subject,
        public readonly string $bodyHtml,
        public readonly ?string $actionUrl = null,
        public readonly ?string $toName = null
    ) {
    }

    public function handle(EmailService $emailService): void
    {
        try {
            $emailService->sendEmail([
                'to'        => $this->toEmail,
                'subject'   => $this->subject,
                'body_html' => $this->bodyHtml,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Project notification email dispatch failed via EmailService: " . $e->getMessage() . " - falling back to direct Mail");
            try {
                Mail::send([], [], function ($msg) {
                    $msg->to($this->toEmail, $this->toName)
                        ->subject($this->subject)
                        ->html($this->bodyHtml);
                });
            } catch (\Throwable $fallbackEx) {
                Log::error("Project notification email fallback failed: " . $fallbackEx->getMessage());
            }
        }
    }
}
