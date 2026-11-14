<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * Announcement of the AccountManager by e-mail (channel email), in the user's locale, with a signed unsubscribe
 * link and List-Unsubscribe (one-click, RFC 8058) for its kind: maintenance or news.
 */
class AnnouncementMail extends Mailable
{
    use Queueable;

    public function __construct(public Announcement $announcement, public User $user) {}

    public static function unsubscribeUrl(User $user, Announcement $announcement): string
    {
        return URL::signedRoute('announcements.unsubscribe', [
            'user' => $user->getKey(),
            'kind' => $announcement->isMaintenance() ? 'maintenance' : 'news',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->announcement->title());
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.self::unsubscribeUrl($this->user, $this->announcement).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        // body_html comes sanitized from the AccountManager. Blank lines would end the HTML block in the Markdown mail.
        $body = (string) preg_replace("/\n\s*\n/", "\n", trim($this->announcement->bodyHtml()));

        return new Content(
            markdown: 'mail.announcement',
            with: [
                'title' => $this->announcement->title(),
                'body' => $body,
                'imageUrl' => $this->announcement->image_url,
                'link' => $this->announcement->link,
                'maintenance' => $this->announcement->isMaintenance(),
                'unsubscribeUrl' => self::unsubscribeUrl($this->user, $this->announcement),
            ],
        );
    }
}
