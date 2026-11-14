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
    public const KINDS = ['news' => 'announcement_emails', 'maintenance' => 'maintenance_emails'];

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
    public function confirmUnsubscribe(User $user, string $kind): View
    {
        return view('announcements.unsubscribe', ['kind' => $kind, 'done' => false]);
    }

    /**
     * The confirmation's button and the one-click List-Unsubscribe of mail clients (RFC 8058, no CSRF token).
     */
    public function unsubscribe(User $user, string $kind): View
    {
        $user->forceFill([self::KINDS[$kind] => false])->save();

        return view('announcements.unsubscribe', ['kind' => $kind, 'done' => true]);
    }
}
