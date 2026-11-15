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

<small>{{ __('You get this e-mail because of your settings in :app.', ['app' => config('app.name')]) }} [{{ __('Unsubscribe from news by e-mail') }}]({{ $unsubscribeUrl }})</small>
@endcomponent
