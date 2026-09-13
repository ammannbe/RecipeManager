<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function locale(string $locale): RedirectResponse
    {
        abort_unless(SetLocale::isAvailable($locale), 404);

        session()->put('locale', $locale);

        // Logged-in users keep their choice across sessions and devices.
        user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
