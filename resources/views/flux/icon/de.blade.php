{{-- Credit: Quitto (https://www.quitto.ch) --}}

@php
$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });
@endphp

<svg
    {{ $attributes->class($classes) }}
    data-flux-icon
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-slot="icon"
>
    <path fill="#ffce00" d="M0 16h24v8H0Zm0 0"/>
    <path fill="#000000" d="M0 0h24v8H0Zm0 0"/>
    <path fill="#d00d00" d="M0 8h24v8H0Zm0 0"/>
</svg>
