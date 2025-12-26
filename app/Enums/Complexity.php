<?php

namespace App\Enums;

enum Complexity: string
{
    case Simple = 'simple';
    case Normal = 'normal';
    case Difficult = 'difficult';

    public function label(): string
    {
        return match ($this) {
            Complexity::Simple => __('Simple'),
            Complexity::Normal => __('Normal'),
            Complexity::Difficult => __('Difficult'),
        };
    }

    public function key(): int
    {
        return match ($this) {
            Complexity::Simple => 0,
            Complexity::Normal => 1,
            Complexity::Difficult => 2,
        };
    }
}
