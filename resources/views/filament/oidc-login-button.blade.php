{{-- Above the login and registration form; $register: the registration page (needs AUTH_OIDC_REGISTRATION). --}}
@if (config('login.oidc.enabled') && (! ($register ?? false) || config('login.oidc.registration')))
    @php($provider = App\Auth\OidcLabel::get())
    <div class="flex flex-col gap-y-4">
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
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('No :provider account yet?', ['provider' => $provider]) }}
                <a class="underline" href="{{ config('login.oidc.signup_url') }}">{{ __('Create one') }}</a>
            </p>
        @endif

        @if (($register ?? false) || config('login.local.enabled'))
            <div class="flex items-center gap-x-3 text-sm text-gray-500 dark:text-gray-400">
                <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                {{ __('or') }}
                <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
            </div>
        @endif
    </div>
@endif
