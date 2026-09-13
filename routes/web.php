<?php

use App\Http\Controllers\CookbookInvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecipeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RecipeController::class, 'index'])->name('recipes.index');
Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
Route::get('/recipes/{recipe}/photos/{filename}', [RecipeController::class, 'photo'])
    ->name('recipes.photo');

Route::get('/cookbook-invitations/{token}', [CookbookInvitationController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('cookbook-invitations.show');

Route::post('/profile/{locale}', [ProfileController::class, 'locale'])->name('profile.locale');
