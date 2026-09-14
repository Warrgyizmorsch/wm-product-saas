<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailBody,
        public ?string $pdfBinary = null,
        public ?string $pdfFileName = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->mailBody,
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if (!empty($this->pdfBinary)) {
            $fileName = $this->pdfFileName ?: 'Quotation.pdf';
            $attachments[] = Attachment::fromData(fn () => $this->pdfBinary, $fileName)
                ->withMime('application/pdf');
        }

        return $attachments;
    }
}
