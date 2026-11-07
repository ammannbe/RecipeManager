<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: "All rights reserved." leads to the easter egg page of the AccountManager, if connected (the footer is parsed as Markdown). --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. @if ($easterEgg = \App\Support\EasterEgg::url())[{{ __('All rights reserved.') }}]({{ $easterEgg }})@else{{ __('All rights reserved.') }}@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
