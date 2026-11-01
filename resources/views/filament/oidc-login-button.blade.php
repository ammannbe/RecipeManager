@if (config('login.oidc.enabled'))
    <div class="flex flex-col gap-y-4">
        @if (config('login.local.enabled'))
            <div class="flex items-center gap-x-3 text-sm text-gray-500 dark:text-gray-400">
                <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                {{ __('or') }}
                <span class="h-px flex-1 bg-gray-200 dark:bg-white/10"></span>
            </div>
        @endif

        <x-filament::button
            tag="a"
            :href="route('oidc.redirect')"
            color="gray"
            icon="heroicon-o-key"
            size="lg"
        >
            {{ __('Sign in with :provider', ['provider' => config('login.oidc.label')]) }}
        </x-filament::button>
    </div>
@endif
