<?php

use App\Models\User;

function user(): ?User
{
    return auth()->user();
}

function emdash(): string
{
    return '—';
}
