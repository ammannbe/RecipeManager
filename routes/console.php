<?php

use App\Models\CookbookInvitation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('cookbook-invitations:prune', function () {
    $pruned = CookbookInvitation::query()
        ->where(fn ($query) => $query->where('expires_at', '<', now())->orWhereNotNull('accepted_at'))
        ->delete();

    $this->info("Pruned {$pruned} invitation(s).");
})->purpose('Delete expired and accepted cookbook invitations');

Schedule::command('cookbook-invitations:prune')->daily();
