<?php

namespace App\Services\RecipeImport;

use App\Enums\Complexity;

/**
 * Single source of truth for the recipe import/export JSON contract.
 *
 * Shared by the AI prompt builder, the validator, the importer and the exporter so the
 * documented shape can never drift from the enforced one.
 */
class RecipeJsonSchema
{
    public const MAX_NAME = 100;

    public const MAX_COOKBOOK_NAME = 20;

    public const MAX_SERVING_TYPE = 20;

    public const MAX_GROUP_NAME = 20;

    public const MAX_UNIT_NAME = 20;

    public const MAX_TAG_NAME = 20;

    public const MAX_CATEGORY_NAME = 50;

    public const MAX_FOOD_NAME = 50;

    public const MAX_ATTRIBUTE_NAME = 40;

    public const MAX_AUTHOR_NAME = 50;

    /** Guards against decompression bombs and oversized payloads. */
    public const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

    public const MAX_PHOTOS = 10;

    /** @var array<int, string> */
    public const ALLOWED_PHOTO_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * @return array<int, string>
     */
    public static function complexities(): array
    {
        return array_map(fn (Complexity $case): string => $case->value, Complexity::cases());
    }

    /**
     * Human readable field reference rendered into the AI prompt and the docs.
     */
    public static function describe(): string
    {
        $complexities = implode('", "', self::complexities());

        return <<<TXT
        Top level object (exactly one recipe, never an array):

        - "name" (string, required, max 100) - the recipe title.
        - "category" (string, required, max 50) - pick one from the known categories below.
        - "cookbook" (string or null, max 20) - the source cookbook, if any.
        - "servings" (integer or null) - number of portions. Whole numbers only.
        - "serving_type" (string or null, max 20) - e.g. "Portionen", "Stück", "Gläser".
        - "complexity" (string, required) - one of "{$complexities}".
        - "preparation_time" (string or null) - total time as "HH:MM", e.g. "01:30".
        - "instructions" (string, required) - the preparation steps as Markdown.
          Use a numbered list, one step per line. Do not include the ingredient list here.
        - "tags" (array of strings, max 20 chars each) - free keywords.
        - "ingredients" (array of ingredient objects) - ingredients that belong to no group.
        - "ingredient_groups" (array of group objects) - only when the source splits the
          ingredients into named sections (e.g. "Teig", "Füllung").

        Group object:

        - "name" (string, required, max 20) - the section title.
        - "ingredients" (array of ingredient objects, required).

        Ingredient object:

        - "amount" (number or null) - the quantity. Convert fractions to decimals
          (1/2 becomes 0.5). Use null when the source gives no quantity.
        - "amount_max" (number or null) - only for ranges like "200-300 g": amount is 200,
          amount_max is 300.
        - "unit" (string or null, max 20) - the measurement unit. Pick one from the known
          units below. Never invent a unit; use null if none fits.
        - "food" (string, required, max 50) - the ingredient itself, singular, without
          quantity, unit or preparation notes. "2 grosse Zwiebeln, gewürfelt" becomes
          food "Zwiebel" with attributes ["gross", "gewürfelt"].
        - "attributes" (array of strings, max 40 chars each) - preparation notes and
          qualifiers. Pick from the known attributes below where possible.
        - "alternatives" (array of ingredient objects) - substitutes for this ingredient,
          e.g. "Butter oder Margarine". Alternatives must not nest further.

        Photos are optional and are normally omitted. When present:

        - "photos" (array, max 10) of objects with "filename" (string) and "data"
          (a base64 data URI such as "data:image/jpeg;base64,...").
        TXT;
    }

    /**
     * A complete, valid example used in the prompt, the docs and the tests.
     *
     * @return array<string, mixed>
     */
    public static function example(): array
    {
        return [
            'name' => 'Apfelkuchen',
            'category' => 'Dessert',
            'cookbook' => 'Omas Backbuch',
            'servings' => 8,
            'serving_type' => 'Stück',
            'complexity' => 'normal',
            'preparation_time' => '01:15',
            'instructions' => "1. Den Backofen auf 180 °C vorheizen.\n2. Butter und Zucker schaumig rühren.\n3. Die Äpfel schälen und in Spalten schneiden.\n4. 45 Minuten backen.",
            'tags' => ['Kuchen', 'Herbst'],
            'ingredients' => [
                [
                    'amount' => 1,
                    'amount_max' => null,
                    'unit' => 'Prise',
                    'food' => 'Salz',
                    'attributes' => [],
                    'alternatives' => [],
                ],
            ],
            'ingredient_groups' => [
                [
                    'name' => 'Teig',
                    'ingredients' => [
                        [
                            'amount' => 250,
                            'amount_max' => null,
                            'unit' => 'g',
                            'food' => 'Mehl',
                            'attributes' => ['gesiebt'],
                            'alternatives' => [],
                        ],
                        [
                            'amount' => 125,
                            'amount_max' => 150,
                            'unit' => 'g',
                            'food' => 'Butter',
                            'attributes' => ['weich'],
                            'alternatives' => [
                                [
                                    'amount' => 125,
                                    'amount_max' => null,
                                    'unit' => 'g',
                                    'food' => 'Margarine',
                                    'attributes' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Belag',
                    'ingredients' => [
                        [
                            'amount' => 4,
                            'amount_max' => null,
                            'unit' => null,
                            'food' => 'Apfel',
                            'attributes' => ['säuerlich', 'geschält'],
                            'alternatives' => [],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function exampleJson(): string
    {
        return json_encode(
            self::example(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';
    }
}
