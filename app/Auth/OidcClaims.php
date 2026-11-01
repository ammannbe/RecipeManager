<?php

namespace App\Auth;

use SocialiteProviders\OIDC\InvalidTokenException;

/**
 * Checks of the ID token that the OIDC provider package does not do itself.
 */
class OidcClaims
{
    /**
     * @throws InvalidTokenException
     */
    public static function assertValid(object $payload, string $issuer, string $clientId): void
    {
        if (rtrim((string) ($payload->iss ?? ''), '/') !== rtrim($issuer, '/')) {
            throw new InvalidTokenException('JWT: Invalid issuer.', 401);
        }

        $audiences = (array) ($payload->aud ?? []);

        if (! in_array($clientId, $audiences, true)) {
            throw new InvalidTokenException('JWT: Invalid audience.', 401);
        }

        if (count($audiences) > 1 && ($payload->azp ?? null) !== $clientId) {
            throw new InvalidTokenException('JWT: Invalid authorized party.', 401);
        }

        if (! isset($payload->exp) || (int) $payload->exp < now()->getTimestamp()) {
            throw new InvalidTokenException('JWT: Token has expired.', 401);
        }
    }
}
