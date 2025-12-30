<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Signer;
use App\Models\SigningProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email notification when a signing process is cancelled.
 */
class ProcessCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SigningProcess $signingProcess,
        public Signer $signer
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Signing Request Cancelled - '.$this->getDocumentName(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.process-cancelled',
            with: [
                'signerName' => $this->signer->name,
                'documentName' => $this->getDocumentName(),
                'cancellationReason' => $this->signingProcess->cancellation_reason,
                'cancelledAt' => $this->signingProcess->cancelled_at,
                'processUuid' => $this->signingProcess->uuid,
            ],
        );
    }

    /**
     * Get document name.
     */
    private function getDocumentName(): string
    {
        return $this->signingProcess->document->original_name ?? 'Document';
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
