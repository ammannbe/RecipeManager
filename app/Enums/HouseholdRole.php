<?php

namespace App\Enums;

/**
 * Admins invite and remove members, change roles, rename and delete the household;
 * members may leave. Every household keeps at least one admin.
 */
enum HouseholdRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            HouseholdRole::Admin => __('Admin'),
            HouseholdRole::Member => __('Member'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (HouseholdRole $role): array => [$role->value => $role->label()])->all();
    }
}
