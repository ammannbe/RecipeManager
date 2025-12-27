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

    public function icon(): string
    {
        return match ($this) {
            Complexity::Simple => 'signal-cellular-1',
            Complexity::Normal => 'signal-cellular-2',
            Complexity::Difficult => 'signal-cellular-3',
        };
    }

    public function color(): string
    {
        return match ($this) {
            Complexity::Simple => 'green',
            Complexity::Normal => 'blue',
            Complexity::Difficult => 'red',
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
