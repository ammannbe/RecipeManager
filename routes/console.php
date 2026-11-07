<?php

use App\Models\CookbookInvitation;
use App\Models\HouseholdInvitation;
use App\Models\RecipeDraft;
use App\Models\User;
use App\Services\RecipePhotos;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

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

Artisan::command('household-invitations:prune', function () {
    $pruned = HouseholdInvitation::query()
        ->where(fn ($query) => $query->where('expires_at', '<', now())->orWhereNotNull('accepted_at'))
        ->delete();

    $this->info("Pruned {$pruned} invitation(s).");
})->purpose('Delete expired and accepted household invitations');

Schedule::command('household-invitations:prune')->daily();

Artisan::command('recipe-drafts:prune', function () {
    $pruned = RecipeDraft::query()->where('expires_at', '<=', now())->delete();

    // AI photos of import wizards that were left before importing.
    $disk = Storage::disk('local');
    $photos = collect($disk->allFiles(RecipePhotos::IMPORT_DIRECTORY))
        ->filter(fn (string $path): bool => $disk->lastModified($path) < now()->subDay()->getTimestamp());
    $disk->delete($photos->all());

    $this->info("Pruned {$pruned} recipe draft(s) and {$photos->count()} import photo(s).");
})->purpose('Delete expired recipe drafts of companion apps and old import photos');

Schedule::command('recipe-drafts:prune')->daily();

Artisan::command('users:prune-unverified {--days=7}', function () {
    $pruned = 0;

    User::query()
        ->withTrashed()
        ->whereNull('email_verified_at')
        ->where('admin', false)
        ->where('created_at', '<', now()->subDays((int) $this->option('days')))
        ->with('author')
        ->each(function (User $user) use (&$pruned): void {
            DB::transaction(function () use ($user): void {
                $author = $user->author;

                $user->forceDelete();

                // Keep the author if anything else still refers to it; force delete to free its name.
                if ($author && ! $author->recipes()->exists() && ! $author->cookbooks()->exists()) {
                    $author->forceDelete();
                }
            });

            $pruned++;
        });

    $this->info("Pruned {$pruned} unverified user(s).");
})->purpose('Delete users who did not verify their e-mail address, together with their unused author');

Schedule::command('users:prune-unverified')->daily();
