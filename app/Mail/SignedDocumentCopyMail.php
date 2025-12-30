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
 * Email notification with signed document copy for signers.
 */
class SignedDocumentCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public SigningProcess $signingProcess,
        public Signer $signer,
        public string $downloadToken
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Signed Document is Ready - '.$this->getDocumentName(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.signed-document-copy',
            with: [
                'signerName' => $this->signer->name,
                'documentName' => $this->getDocumentName(),
                'downloadUrl' => $this->getDownloadUrl(),
                'expiresAt' => $this->signer->download_expires_at,
                'processUuid' => $this->signingProcess->uuid,
                'verificationCode' => $this->signingProcess->verificationCode?->code,
                'verificationUrl' => $this->getVerificationUrl(),
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
     * Get download URL.
     */
    private function getDownloadUrl(): string
    {
        return route('document.download', [
            'token' => $this->downloadToken,
        ]);
    }

    /**
     * Get verification URL.
     */
    private function getVerificationUrl(): ?string
    {
        if (! $this->signingProcess->verificationCode) {
            return null;
        }

        return route('verification.show', [
            'code' => $this->signingProcess->verificationCode->code,
        ]);
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
