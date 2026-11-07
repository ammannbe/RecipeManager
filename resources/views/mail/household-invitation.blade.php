@component('mail::message')
# {{ __('You have been invited to the household ":name"', ['name' => $household]) }}

@if ($invitedBy)
{{ __(':name invites you to join a household. Cookbooks shared with the household become available to you.', ['name' => $invitedBy]) }}
@else
{{ __('Someone invites you to join a household. Cookbooks shared with the household become available to you.') }}
@endif

@component('mail::button', ['url' => $url])
{{ __('Accept invitation') }}
@endcomponent

{{ __('The invitation is valid until :date.', ['date' => $expiresAt->isoFormat('LLL')]) }}

{{ __('If you did not expect this invitation, you can ignore this email.') }}
@endcomponent
