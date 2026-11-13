{{-- Above the login and registration form; $register: the registration page (needs AUTH_OIDC_REGISTRATION). --}}
{{-- Inline styles: the panel uses Filament's prebuilt CSS, which lacks most Tailwind utilities. --}}
@if (config('login.oidc.enabled') && (! ($register ?? false) || config('login.oidc.registration')))
    @php($provider = App\Auth\OidcLabel::get())
    <div style="display: flex; flex-direction: column; row-gap: 1rem;">
        <x-filament::button
            tag="a"
            :href="route('oidc.redirect')"
            color="gray"
            icon="heroicon-o-key"
            size="lg"
        >
            {{ __(($register ?? false) ? 'Register with :provider' : 'Sign in with :provider', ['provider' => $provider]) }}
        </x-filament::button>

        @if (config('login.oidc.signup_url'))
            <p style="margin: 0; text-align: center; font-size: 0.875rem; color: var(--gray-500);">
                {{ __('No :provider account yet?', ['provider' => $provider]) }}
                <x-filament::link :href="config('login.oidc.signup_url')">{{ __('Create one') }}</x-filament::link>
            </p>
        @endif

        @if (($register ?? false) || config('login.local.enabled') || App\Filament\Auth\Login::connectsCompanionApp())
            <div style="display: flex; align-items: center; column-gap: 0.75rem; font-size: 0.875rem; color: var(--gray-500);">
                <span style="height: 1px; flex: 1; background: currentColor; opacity: 0.3;"></span>
                {{ __('or') }}
                <span style="height: 1px; flex: 1; background: currentColor; opacity: 0.3;"></span>
            </div>
        @endif
    </div>
@endif
