<?php

namespace App\Auth;

use SocialiteProviders\OIDC\Provider;

class OidcProvider extends Provider
{
    /**
     * Also verify issuer, audience and expiry of the ID token.
     *
     * @param  string  $jwt
     * @param  string  $code
     * @return object
     */
    protected function decodeJWT($jwt, $code)
    {
        $payload = parent::decodeJWT($jwt, $code);

        OidcClaims::assertValid($payload, (string) $this->getOpenIdConfig()['issuer'], $this->clientId);

        return $payload;
    }
}
