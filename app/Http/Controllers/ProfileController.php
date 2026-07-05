<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function locale(string $locale): RedirectResponse
    {
        if (! array_key_exists($locale, config('app.locales'))) {
            abort(400);
        }

        session()->put('locale', $locale);
        app()->setLocale($locale);

        return back();
    }
}
