<?php

use App\Models\Author;
use App\Models\User;

function user(): ?User
{
    return auth()->user();
}

function author(): ?Author
{
    return user()?->author;
}

function emdash(): string
{
    return '—';
}
