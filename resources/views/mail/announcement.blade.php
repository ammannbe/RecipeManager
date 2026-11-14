@component('mail::message')
# {{ $title }}

@if ($imageUrl)
<img src="{{ $imageUrl }}" alt="" style="max-width: 100%; height: auto; border-radius: 8px;">
@endif

{!! $body !!}

@if ($link)
@component('mail::button', ['url' => $link])
{{ __('Learn more') }}
@endcomponent
@endif

<small>{{ $maintenance ? __('You get maintenance notices of :app by e-mail.', ['app' => config('app.name')]) : __('You get news of :app by e-mail because you asked for it.', ['app' => config('app.name')]) }} [{{ __('Unsubscribe') }}]({{ $unsubscribeUrl }}) · {{ __('Change it in your profile.') }}</small>
@endcomponent
