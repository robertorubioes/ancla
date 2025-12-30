<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public UserInvitation $invitation,
        public Tenant $tenant,
        public User $invitedBy
    ) {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->tenant->name} on Firmalum",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
            with: [
                'invitationUrl' => route('invitation.accept', ['token' => $this->invitation->token]),
                'tenantName' => $this->tenant->name,
                'inviterName' => $this->invitedBy->name,
                'role' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at,
                'message' => $this->invitation->message,
            ],
        );
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
