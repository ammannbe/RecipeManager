<?php

use App\Http\Controllers\Api\AccountManagerWebhookController;
use App\Http\Controllers\Api\FoodController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\RecipeDraftController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VocabularyController;
use App\Http\Middleware\DenyMcpTokens;
use App\Http\Middleware\EnsureEntitlementAccess;
use Illuminate\Support\Facades\Route;

// JSON API for companion apps like Shoppinglist, authenticated with Passport access tokens.
Route::middleware(['auth:api', DenyMcpTokens::class, EnsureEntitlementAccess::class, 'throttle:120,1'])->group(function (): void {
    Route::get('/user', [UserController::class, 'show'])->name('api.user');
    Route::get('/foods', [FoodController::class, 'index'])->name('api.foods.index');
    Route::post('/recipes/search', [RecipeController::class, 'search'])->name('api.recipes.search');
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('api.recipes.show');
    Route::get('/vocabulary', [VocabularyController::class, 'index'])->name('api.vocabulary');
    Route::post('/recipe-drafts', [RecipeDraftController::class, 'store'])->name('api.recipe-drafts.store');
    Route::get('/recipe-drafts/{draft}', [RecipeDraftController::class, 'show'])->whereUuid('draft')->name('api.recipe-drafts.show');
});

// Events of the AccountManager (households, entitlements, user.deleted), signed, no session or CSRF.
Route::post('/account-manager/webhook', AccountManagerWebhookController::class)->middleware('throttle:120,1')->name('account-manager.webhook');
