<?php

namespace App\Filament\Pages;

use App\Announcements\Announcements;
use App\Models\Announcement;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * "News": the announcements of the AccountManager with the channel in_app (title, image, text, link, date), newest
 * first, opened from the bell in the top bar (filament/announcement-bell). Opening the page marks them read; the
 * ones that were unread are highlighted once.
 */
class News extends Page
{
    protected string $view = 'filament.pages.news';

    protected static ?string $slug = 'news';

    protected static bool $shouldRegisterNavigation = false;

    /** @var list<int> */
    public array $unread = [];

    public static function canAccess(): bool
    {
        return Announcements::enabled();
    }

    public function getTitle(): string|Htmlable
    {
        return __('News');
    }

    public function mount(): void
    {
        $user = $this->user();

        $this->unread = Announcements::unreadIds($user, $this->getAnnouncements());

        Announcements::markRead($user, $this->unread);
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function getAnnouncements(): Collection
    {
        return Announcements::news($this->user());
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
