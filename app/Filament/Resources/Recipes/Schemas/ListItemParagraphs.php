<?php

namespace App\Filament\Resources\Recipes\Schemas;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/**
 * Wraps the inline content of list items in a paragraph, like the rich editor's JavaScript does on
 * load (normalize-list-item-content.js). Tight Markdown lists (`<li>Text</li>`, e.g. from the import)
 * otherwise reach the browser without paragraphs, the editor rewrites them at once and the form
 * looks changed (unsaved changes indicator and leave warning) before the user did anything.
 */
class ListItemParagraphs implements StateCast
{
    public function get(mixed $state): mixed
    {
        return $state;
    }

    public function set(mixed $state): mixed
    {
        return is_array($state) ? self::normalize($state) : $state;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public static function normalize(array $node): array
    {
        if (! is_array($node['content'] ?? null) && ($node['type'] ?? null) !== 'listItem') {
            return $node;
        }

        $content = array_map(
            fn (mixed $child): mixed => is_array($child) ? self::normalize($child) : $child,
            array_values((array) ($node['content'] ?? [])),
        );

        if (($node['type'] ?? null) === 'listItem') {
            $normalized = [];
            $paragraph = null;

            foreach ($content as $child) {
                if (! self::isInline($child)) {
                    if ($paragraph !== null) {
                        $normalized[] = $paragraph;
                        $paragraph = null;
                    }

                    $normalized[] = $child;

                    continue;
                }

                $paragraph ??= ['type' => 'paragraph', 'content' => []];
                $paragraph['content'][] = $child;
            }

            if ($paragraph !== null) {
                $normalized[] = $paragraph;
            }

            if (($normalized[0]['type'] ?? null) !== 'paragraph') {
                array_unshift($normalized, ['type' => 'paragraph', 'content' => []]);
            }

            $content = $normalized;
        }

        $node['content'] = $content;

        return $node;
    }

    private static function isInline(mixed $node): bool
    {
        return is_array($node) && in_array($node['type'] ?? null, ['text', 'hardBreak', 'image', 'mention', 'mergeTag'], true);
    }
}
