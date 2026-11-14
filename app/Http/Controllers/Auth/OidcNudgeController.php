<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OidcNudge;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OidcNudgeController extends Controller
{
    /**
     * "Later" on the link banner.
     */
    public function dismiss(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            OidcNudge::dismiss($user);
        }

        return redirect()->back(fallback: Filament::getPanel('app')->getUrl());
    }
}
