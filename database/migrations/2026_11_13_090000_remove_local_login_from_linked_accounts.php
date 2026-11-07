<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts linked to the OIDC sign-in become SSO-only (App\Auth\OidcAccount): no password, no
 * remember-me token, no pending password reset and no session from a local sign-in. Unlinked
 * accounts (e.g. local emergency admins) are not touched. The app has no passkeys or other
 * local sign-in factors to remove.
 */
return new class extends Migration
{
    public function up(): void
    {
        $linked = DB::table('users')->whereNotNull('oidc_sub');

        // A null remember token matches no cookie; the next OIDC sign-in sets a new one.
        (clone $linked)->update(['password' => null, 'remember_token' => null]);

        DB::table('password_reset_tokens')->whereIn('email', (clone $linked)->select('email'))->delete();

        // The linked users sign in again with the provider once.
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->whereIn('user_id', (clone $linked)->select('id'))->delete();
        }
    }

    public function down(): void
    {
        // The passwords are gone; unlinked users set one with "Forgot password" (users:unlink-oidc).
    }
};
