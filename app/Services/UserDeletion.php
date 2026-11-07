<?php

namespace App\Services;

use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

/**
 * Deletes a user for good (webhook user.deleted of the AccountManager, docs/ENTITLEMENTS-API.md 5.2), like
 * users:prune-unverified but with contents:
 *
 * - the user's cookbooks with all their recipes (like deleting a user always did, also when shared with
 *   other users or households) and the user's recipes without cookbook, permanently with their photos;
 * - recipes the user added to cookbooks of others stay in those cookbooks and move to the cookbook's
 *   author, so the author of the deleted user can go;
 * - memberships in cookbooks and households (UserObserver: admin-less local households go to their
 *   longest member), OAuth tokens, clients and MCP grants, sessions, drafts and import photos.
 *
 * AI usage rows stay without user (statistics). Idempotent: an already deleted user is not found again.
 */
class UserDeletion
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $author = $user->author;

            if ($author !== null) {
                Cookbook::withTrashed()->where('author_id', $author->getKey())->get()->each(function (Cookbook $cookbook): void {
                    Recipe::withTrashed()->where('cookbook_id', $cookbook->getKey())->get()->each(fn (Recipe $recipe) => $recipe->forceDelete());
                    $cookbook->forceDelete();
                });

                Recipe::withTrashed()->where('author_id', $author->getKey())->get()->each(function (Recipe $recipe): void {
                    $cookbookAuthor = Cookbook::withTrashed()->whereKey($recipe->cookbook_id)->value('author_id');

                    if ($cookbookAuthor === null) {
                        $recipe->forceDelete();

                        return;
                    }

                    $recipe->forceFill(['author_id' => $cookbookAuthor])->saveQuietly();
                });
            }

            $user->tokens()->get()->each(function ($token): void {
                DB::table('oauth_refresh_tokens')->where('access_token_id', $token->getKey())->delete();
                $token->delete();
            });
            DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->delete();
            Passport::clientModel()::query()->where('owner_type', $user->getMorphClass())->where('owner_id', $user->getKey())->delete();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            // UserObserver::deleting drops cookbook and household memberships; drafts and MCP grants cascade.
            $user->forceDelete();

            if ($author !== null && ! $author->recipes()->withTrashed()->exists() && ! $author->cookbooks()->withTrashed()->exists()) {
                $author->forceDelete();
            }
        });

        Storage::disk('local')->deleteDirectory(RecipePhotos::IMPORT_DIRECTORY.'/'.$user->getKey());
    }
}
