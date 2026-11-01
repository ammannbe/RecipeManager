<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

class OAuthClient extends Client
{
    /**
     * Own companion apps (config "passport.trusted_clients") need no approval. Unverified users
     * still go through the approval screen, which sends them to the e-mail verification first.
     *
     * @param  array<int, mixed>  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return static::isTrusted((string) $this->getKey())
            && $user instanceof User
            && $user->hasVerifiedEmail();
    }

    public static function isTrusted(?string $clientId): bool
    {
        return $clientId !== null && in_array($clientId, config('passport.trusted_clients'), true);
    }
}
