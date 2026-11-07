<?php

namespace App\Mail;

use App\Models\HouseholdInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HouseholdInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public HouseholdInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to the household ":name"', [
                'name' => $this->invitation->household->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.household-invitation',
            with: [
                'household' => $this->invitation->household->name,
                'invitedBy' => $this->invitation->invitedBy?->author?->name,
                'url' => route('household-invitations.show', ['token' => $this->token]),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
