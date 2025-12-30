<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Signer;
use App\Models\SigningProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable for sending signing request notifications.
 *
 * Sends a professional email to signers with a unique signing link.
 */
class SigningRequestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public readonly SigningProcess $signingProcess,
        public readonly Signer $signer
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $documentName = $this->getDocumentName();

        return new Envelope(
            from: new Address(
                address: config('mail.from.address', 'noreply@ancla.app'),
                name: config('mail.from.name', 'Firmalum')
            ),
            subject: "Firma requerida: {$documentName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.signing-request',
            with: [
                'signingProcess' => $this->signingProcess,
                'signer' => $this->signer,
                'signingUrl' => $this->getSigningUrl(),
                'promoterName' => $this->getPromoterName(),
                'documentName' => $this->getDocumentName(),
                'customMessage' => $this->signingProcess->custom_message,
                'deadline' => $this->signingProcess->deadline_at,
                'hasDeadline' => $this->signingProcess->deadline_at !== null,
            ],
        );
    }

    /**
     * Get the signing URL for the signer.
     */
    private function getSigningUrl(): string
    {
        return url("/sign/{$this->signer->token}");
    }

    /**
     * Get the promoter's name.
     */
    private function getPromoterName(): string
    {
        return $this->signingProcess->createdBy->name ?? 'Firmalum';
    }

    /**
     * Get the document name.
     */
    private function getDocumentName(): string
    {
        $document = $this->signingProcess->document;

        // Try to get title from pdf_metadata
        if ($document->pdf_metadata && isset($document->pdf_metadata['title'])) {
            return $document->pdf_metadata['title'];
        }

        // Fallback to original filename without extension
        if ($document->original_filename) {
            return pathinfo($document->original_filename, PATHINFO_FILENAME);
        }

        return 'Documento';
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
