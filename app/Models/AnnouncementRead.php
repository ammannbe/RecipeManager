<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Read (opened in "News") and dismissed (banner closed) state of an announcement per user. Stays in the app,
 * also for users without an AccountManager account.
 *
 * @property int $id
 * @property int $announcement_id
 * @property int $user_id
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 */
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
