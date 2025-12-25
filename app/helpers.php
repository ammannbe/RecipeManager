<?php

use App\ValueObjects\Address;
use Carbon\Carbon;

function user(): ?\App\Models\User
{
    return auth()->user();
}

function emdash(): string
{
    return '—';
}
