<?php

namespace App\Services;

/**
 * Swiss German spelling for recipes written by an AI (AI import, MCP tools), RECIPE_SPELLING=de_CH:
 * the prompts ask for it, and "ß" becomes "ss" in the texts as a safety net.
 */
class SwissSpelling
{
    /** Keys whose values are never touched (photo addresses, data URIs, file names). */
    private const SKIPPED_KEYS = ['photos', 'url', 'data', 'filename'];

    public static function enabled(): bool
    {
        return config('app.recipe_spelling') === 'de_CH';
    }

    /**
     * Prompt rule for the German import prompt, empty when disabled.
     */
    public static function promptRule(): string
    {
        return self::enabled()
            ? 'Schreibe in Schweizer Rechtschreibung: immer «ss» statt «ß» (z. B. «gross», «Strasse», «Süsskartoffel»). Schweizer Begriffe übernimmst du, wo die Quelle sie verwendet; übersetze sonst nichts.'
            : '';
    }

    /**
     * Instruction for AI clients (MCP), empty when disabled.
     */
    public static function instruction(): string
    {
        return self::enabled()
            ? 'Write German texts in Swiss spelling: always "ss" instead of "ß" (gross, Strasse, Süsskartoffel); keep Swiss terms where the source uses them (Rüebli, Rahm), do not translate otherwise.'
            : '';
    }

    public static function text(string $text): string
    {
        return self::enabled() ? str_replace(['ß', 'ẞ'], ['ss', 'SS'], $text) : $text;
    }

    /**
     * All texts of a recipe in the import JSON format (names, ingredients, steps, groups, tags),
     * photos left as they are.
     *
     * @param  array<mixed>  $recipe
     * @return array<mixed>
     */
    public static function recipe(array $recipe): array
    {
        if (! self::enabled()) {
            return $recipe;
        }

        $result = [];

        foreach ($recipe as $key => $value) {
            $result[$key] = match (true) {
                in_array($key, self::SKIPPED_KEYS, true) => $value,
                is_string($value) => self::text($value),
                is_array($value) => self::recipe($value),
                default => $value,
            };
        }

        return $result;
    }
}
