<?php

namespace App\Console\Commands;

use App\Auth\OidcAccount;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Way back to the local login when the OIDC provider is gone (or for a single account without
 * access to it): unlinks the accounts without a password; the users set one with "Forgot
 * password". Like unlinking in the profile, mirrored households become pending.
 */
class UnlinkOidc extends Command
{
    protected $signature = 'users:unlink-oidc {emails?* : E-mail addresses of the accounts} {--all : Every linked account}';

    protected $description = 'Unlink accounts from the OIDC sign-in, so that they can set a password with "Forgot password"';

    public function handle(OidcAccount $accounts): int
    {
        $emails = (array) $this->argument('emails');

        if ($emails === [] && ! $this->option('all')) {
            $this->error('Name e-mail addresses or use --all.');

            return self::FAILURE;
        }

        $users = User::query()
            ->whereNotNull('oidc_sub')
            ->unless($this->option('all'), fn ($query) => $query->whereIn('email', $emails))
            ->get();

        if ($this->option('all') && ! $this->confirm("Unlink {$users->count()} account(s)?", true)) {
            return self::FAILURE;
        }

        $users->each(fn (User $user) => $accounts->unlink($user, null));

        $this->info("Unlinked {$users->count()} account(s). They set a password with \"Forgot password\" on the login page.");

        return self::SUCCESS;
    }
}
