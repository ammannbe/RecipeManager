@component('mail::message')
# {{ __(':name requested new entries', ['name' => $requestedBy]) }}

{{ __('They were requested while importing the recipe ":recipe" and can already be used there. Other users only see them once you approve them.', ['recipe' => $recipe]) }}

@foreach ($items as $item)
- {{ $item['label'] }}: [{{ $item['value'] }}]({{ $item['url'] }})
@endforeach

{{ __('Approve each entry or merge it into an existing one ("Requested" filter in the list).') }}
@endcomponent
