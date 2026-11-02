{{-- Panel brand: the app icon and name; inline styles because the panel uses Filament's prebuilt CSS. --}}
<span style="display: inline-flex; align-items: center; gap: 0.625rem;">
    <img src="{{ asset('favicon-96x96.png') }}" alt="" style="height: 2rem; width: 2rem; border-radius: 0.5rem;">
    <span>{{ config('app.name') }}</span>
</span>
