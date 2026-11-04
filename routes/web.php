<?php

use App\Http\Controllers\Auth\OidcController;
use App\Http\Controllers\CookbookInvitationController;
use App\Http\Controllers\McpAuthorizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/season', [RecipeController::class, 'season'])->name('recipes.season');
Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
Route::get('/recipes/{recipe}/photos/{filename}', [RecipeController::class, 'photo'])
    ->name('recipes.photo');

Route::get('/cookbook-invitations/{token}', [CookbookInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('cookbook-invitations.show');

Route::get('/locale/{locale}', [ProfileController::class, 'locale'])->name('profile.locale');

Route::middleware('throttle:20,1')->group(function () {
    Route::get('/auth/oidc/redirect', [OidcController::class, 'redirect'])->name('oidc.redirect');
    Route::get('/auth/oidc/callback', [OidcController::class, 'callback'])->name('oidc.callback');
});

// Approval of AI clients (MCP) with the permissions the user ticked, then Passport's approval.
Route::post('/oauth/authorize/mcp', [McpAuthorizationController::class, 'approve'])
    ->middleware('auth')
    ->name('mcp.authorize.approve');
