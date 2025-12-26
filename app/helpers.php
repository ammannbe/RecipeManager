<?php

function user(): ?\App\Models\User
{
    return auth()->user();
}

function emdash(): string
{
    return '—';
}
