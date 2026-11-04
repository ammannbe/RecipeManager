<?php

namespace App\Mcp;

/**
 * What an AI client connected over MCP may do with the user's recipes (always limited by the
 * user's own rights through RecipePolicy).
 */
enum McpPermission: string
{
    case Read = 'read';
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';

    public function label(): string
    {
        return match ($this) {
            McpPermission::Read => __('Read recipes'),
            McpPermission::Create => __('Create recipes'),
            McpPermission::Update => __('Change recipes'),
            McpPermission::Delete => __('Delete recipes'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (McpPermission $permission): array => [$permission->value => $permission->label()])->all();
    }
}
