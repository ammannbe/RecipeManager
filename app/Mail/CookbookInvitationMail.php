<?php

namespace App\Mail;

use App\Models\CookbookInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CookbookInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CookbookInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to the cookbook ":name"', [
                'name' => $this->invitation->cookbook->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.cookbook-invitation',
            with: [
                'cookbook' => $this->invitation->cookbook->name,
                'invitedBy' => $this->invitation->invitedBy?->author?->name,
                'url' => route('cookbook-invitations.show', ['token' => $this->token]),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
