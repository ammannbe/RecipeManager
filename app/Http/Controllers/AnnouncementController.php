<?php

namespace App\Http\Controllers;

use App\Announcements\Announcements;
use App\Models\Announcement;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Closing the announcement banner and unsubscribing from announcement e-mails (signed link of AnnouncementMail).
 */
class AnnouncementController extends Controller
{
    public function dismiss(Request $request, Announcement $announcement): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            Announcements::dismiss($user, $announcement);
        }

        return redirect()->back(fallback: Filament::getPanel('app')->getUrl());
    }

    /**
     * The link in the mail shows a confirmation (link scanners must not unsubscribe anybody).
     */
    public function confirmUnsubscribe(User $user): View
    {
        return view('announcements.unsubscribe', ['user' => $user, 'done' => false]);
    }

    /**
     * The confirmation's button and the one-click List-Unsubscribe of mail clients (RFC 8058, no CSRF token):
     * no more announcement e-mails, also no maintenance notices.
     */
    public function unsubscribe(User $user): View
    {
        $user->forceFill(['announcement_emails' => Announcements::EMAILS_NONE])->save();

        return view('announcements.unsubscribe', ['user' => $user, 'done' => true]);
    }
}
