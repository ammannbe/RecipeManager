<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\Auth\OidcNudgeController;
use App\Http\Controllers\CookbookInvitationController;
use App\Http\Controllers\HouseholdInvitationController;
use App\Http\Controllers\ImportPhotoController;
use App\Http\Controllers\McpAuthorizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RecipeController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/season', [RecipeController::class, 'season'])->name('recipes.season');
Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
Route::get('/recipes/{recipe}/photos/{filename}', [RecipeController::class, 'photo'])
    ->name('recipes.photo');

// The AI photo of the import wizard, before the recipe exists.
Route::get('/import-photos/{filename}', ImportPhotoController::class)
    ->middleware('auth')
    ->name('import-photos.show');

Route::get('/cookbook-invitations/{token}', [CookbookInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('cookbook-invitations.show');

Route::get('/household-invitations/{token}', [HouseholdInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('household-invitations.show');

Route::get('/locale/{locale}', [ProfileController::class, 'locale'])->name('profile.locale');

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/oidc/redirect', [OidcController::class, 'redirect'])->name('oidc.redirect');
    Route::get('/auth/oidc/callback', [OidcController::class, 'callback'])->name('oidc.callback');
});

// "Later" on the banner offering to link the OIDC sign-in (AUTH_OIDC_NUDGE).
Route::post('/auth/oidc/nudge/dismiss', [OidcNudgeController::class, 'dismiss'])
    ->middleware(['auth', 'throttle:20,1'])
    ->name('oidc.nudge.dismiss');

// Approval of AI clients (MCP) with the permissions the user ticked, then Passport's approval.
Route::post('/oauth/authorize/mcp', [McpAuthorizationController::class, 'approve'])
    ->middleware('auth')
    ->name('mcp.authorize.approve');

// Announcements (ANNOUNCEMENTS_DRIVER=account-manager): closing the banner, Web Push subscriptions of the browser
// (profile), unsubscribing from announcement e-mails (signed link, POST also as one-click List-Unsubscribe).
Route::middleware(['auth', 'throttle:30,1'])->group(function (): void {
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');
    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
});

Route::middleware(['signed', 'throttle:20,1'])->group(function (): void {
    Route::get('/announcements/unsubscribe/{user}/{kind}', [AnnouncementController::class, 'confirmUnsubscribe'])
        ->whereIn('kind', array_keys(AnnouncementController::KINDS))
        ->name('announcements.unsubscribe');
    Route::post('/announcements/unsubscribe/{user}/{kind}', [AnnouncementController::class, 'unsubscribe'])
        ->whereIn('kind', array_keys(AnnouncementController::KINDS))
        ->withoutMiddleware(PreventRequestForgery::class);
});
