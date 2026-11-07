<?php

namespace App\Auth;

use App\Models\Author;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser as SocialiteUser;

/**
 * Resolves the local user for an OpenID Connect login.
 *
 * Accounts are matched by the provider's subject ("sub"). A local account is only
 * linked through an e-mail address that the provider marks as verified. Linking makes
 * the account SSO-only (App\Auth\OidcAccount::link()).
 */
class OidcLogin
{
    public function __construct(private readonly OidcAccount $accounts) {}

    public function resolve(SocialiteUser $identity, ?User $linkTo = null): User
    {
        $sub = $this->subject($identity);

        $linked = User::withTrashed()->where('oidc_sub', $sub)->first();

        if ($linkTo) {
            return $this->link($linkTo, $sub, $linked);
        }

        if ($linked) {
            if ($linked->trashed()) {
                throw OidcLoginException::because('This account is disabled.');
            }

            return $linked;
        }

        $email = mb_strtolower(trim((string) $identity->getEmail()));

        if ($email === '' || ! $this->emailIsVerified((array) $identity->getRaw())) {
            throw OidcLoginException::because('Your e-mail address must be verified with the sign-in provider.');
        }

        $existing = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing) {
            return $this->linkByEmail($existing, $sub);
        }

        if (! config('login.oidc.registration')) {
            throw OidcLoginException::because('There is no account for this e-mail address.');
        }

        return $this->register($identity, $sub, $email);
    }

    /**
     * Before unlinking: the signed-in user confirms the sign-in at the provider.
     */
    public function confirm(SocialiteUser $identity, User $user): void
    {
        if (! $user->isLinkedToOidc() || ! hash_equals((string) $user->oidc_sub, $this->subject($identity))) {
            throw OidcLoginException::because('This sign-in belongs to another account.');
        }
    }

    private function subject(SocialiteUser $identity): string
    {
        $sub = (string) $identity->getId();

        if ($sub === '') {
            throw OidcLoginException::because('The sign-in did not return a user.');
        }

        $this->assertAllowed((array) $identity->getRaw());

        return $sub;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertAllowed(array $claims): void
    {
        $group = config('login.oidc.required_group');

        if ($group && ! in_array($group, (array) ($claims['groups'] ?? []), true)) {
            throw OidcLoginException::because('You do not have access to this app.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function emailIsVerified(array $claims): bool
    {
        $verified = $claims['email_verified'] ?? false;

        return $verified === true || $verified === 'true';
    }

    private function link(User $user, string $sub, ?User $linked): User
    {
        if ($linked && ! $linked->is($user)) {
            throw OidcLoginException::because('This sign-in is already linked to another account.');
        }

        if ($user->oidc_sub !== null && $user->oidc_sub !== $sub) {
            throw OidcLoginException::because('This account is already linked to another sign-in.');
        }

        if (! $linked) {
            $this->accounts->link($user, $sub);
        }

        return $user;
    }

    private function linkByEmail(User $user, string $sub): User
    {
        if ($user->trashed()) {
            throw OidcLoginException::because('This account is disabled.');
        }

        if ($user->oidc_sub !== null) {
            throw OidcLoginException::because('This account is already linked to another sign-in.');
        }

        $wasVerified = $user->hasVerifiedEmail();

        // An unverified account may belong to someone who registered the address without owning
        // it: the verified owner takes it over (linking drops the password and the other sessions).
        $this->accounts->link($user, $sub, $wasVerified ? [] : ['email_verified_at' => now()]);

        if (! $wasVerified) {
            event(new Verified($user));
        }

        return $user;
    }

    private function register(SocialiteUser $identity, string $sub, string $email): User
    {
        $user = DB::transaction(function () use ($identity, $sub, $email): User {
            $author = Author::query()->create(['name' => $this->uniqueAuthorName($identity, $email)]);

            $user = new User(['email' => $email]);
            $user->forceFill([
                'author_id' => $author->id,
                'oidc_sub' => $sub,
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    private function uniqueAuthorName(SocialiteUser $identity, string $email): string
    {
        $base = trim((string) ($identity->getName() ?: $identity->getNickname() ?: Str::before($email, '@')));
        $base = Str::limit($base, 45, '');

        $name = $base;
        $suffix = 2;

        while (Author::withTrashed()->where('name', $name)->exists()) {
            $name = "{$base} {$suffix}";
            $suffix++;
        }

        return $name;
    }
}
