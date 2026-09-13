@component('mail::message')
# {{ __('You have been invited to the cookbook ":name"', ['name' => $cookbook]) }}

@if ($invitedBy)
{{ __(':name would like to share a cookbook with you.', ['name' => $invitedBy]) }}
@else
{{ __('Someone would like to share a cookbook with you.') }}
@endif

@component('mail::button', ['url' => $url])
{{ __('Accept invitation') }}
@endcomponent

{{ __('The invitation is valid until :date.', ['date' => $expiresAt->isoFormat('LLL')]) }}

{{ __('If you did not expect this invitation, you can ignore this email.') }}
@endcomponent
