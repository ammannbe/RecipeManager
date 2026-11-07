<?php

namespace Tests\Feature\Concerns;

use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\Response;

/**
 * Socialite mock for the OIDC login (identity "sub-123", cook@example.com, verified).
 */
trait MocksOidcProvider
{
    /**
     * @param  array<string, mixed>  $claims
     */
    protected function identity(array $claims = []): SocialiteUser
    {
        $claims = [
            'sub' => 'sub-123',
            'email' => 'cook@example.com',
            'email_verified' => true,
            'name' => 'Pocket Cook',
            ...$claims,
        ];

        return (new SocialiteUser)->setRaw($claims)->map([
            'id' => $claims['sub'],
            'email' => $claims['email'],
            'name' => $claims['name'],
            'nickname' => null,
        ]);
    }

    protected function provider(): Mockery\MockInterface
    {
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('scopes')->andReturnSelf();

        Socialite::shouldReceive('driver')->with('oidc')->andReturn($provider);

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return TestResponse<Response>
     */
    protected function signIn(array $claims = []): TestResponse
    {
        $this->provider()->shouldReceive('user')->andReturn($this->identity($claims));

        return $this->get(route('oidc.callback'));
    }
}
