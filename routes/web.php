<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/', 'recipes.index')->name('recipes.index');
Volt::route('/recipes/{recipe}', 'recipes.show')->name('recipes.show');

Route::post('/profile/{locale}', [ProfileController::class, 'locale'])->name('profile.locale');
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/recipes', 'settings.recipes')->name('settings.recipes');
});
