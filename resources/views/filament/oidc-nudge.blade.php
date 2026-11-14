{{-- Banner offering unlinked users to link the OIDC sign-in (App\Auth\OidcNudge, AUTH_OIDC_NUDGE). --}}
{{-- Inline styles: the panel uses Filament's prebuilt CSS, which lacks most Tailwind utilities. --}}
@if (App\Auth\OidcNudge::shows(auth()->user()))
    @php($provider = App\Auth\OidcLabel::get())
    <div
        data-oidc-nudge
        role="region"
        aria-label="{{ __('Link with :provider', ['provider' => $provider]) }}"
        style="margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-radius: 0.75rem; border: 1px solid var(--primary-300); background: color-mix(in oklab, var(--primary-500) 10%, transparent);"
    >
        <x-filament::icon icon="heroicon-o-link" style="width: 1.5rem; height: 1.5rem; color: var(--primary-600);" />
        <div style="flex: 1 1 16rem;">
            <p style="margin: 0; font-weight: 600;">{{ __('Link with :provider', ['provider' => $provider]) }}</p>
            <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--gray-500);">
                {{ __('One account for all Narrenhaus apps: sign in once, households and access everywhere.') }}
            </p>
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
            <x-filament::button
                tag="a"
                :href="App\Filament\Auth\EditProfile::getUrl(['action' => 'linkOidc'], panel: 'app')"
                icon="heroicon-o-link"
            >
                {{ __('Link now') }}
            </x-filament::button>
            <form method="POST" action="{{ route('oidc.nudge.dismiss') }}" style="margin: 0;">
                @csrf
                <x-filament::button type="submit" color="gray">
                    {{ __('Later') }}
                </x-filament::button>
            </form>
        </div>
    </div>
@endif
