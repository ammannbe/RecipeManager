<?php

namespace App\Ai;

use App\Models\AiUsage;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Document;
use App\Services\FoodConversions;
use App\Services\RecipeImport\ImportPromptBuilder;
use App\Services\RecipeImport\RecipeJsonSchema;
use App\Services\RecipePhotos;
use App\Services\SwissSpelling;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * AI features of the recipe app: turns a photo, PDF or text into the import JSON (the same
 * prompt as "manually via chat", the user still resolves and confirms the import), estimates
 * conversion data of new foods, creates and enhances recipe photos. Verified users with
 * users.ai_enabled, limited per hour.
 */
class RecipeAssistant
{
    public const DEFAULT_ENHANCE_INSTRUCTION = 'Improve the light, colors and sharpness. Do not change the dish, the plate or the composition.';

    /** Input formats of the Images edit endpoint. */
    private const EDITABLE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private OpenAiClient $client,
        private ImportPromptBuilder $prompt,
        private RecipePage $page,
        private RecipePhotos $photos,
        private FoodConversions $conversions,
    ) {}

    public static function allows(?User $user): bool
    {
        return OpenAiClient::enabled()
            && $user !== null
            && $user->ai_enabled
            && $user->hasVerifiedEmail();
    }

    /**
     * @return array<string, mixed> recipe in the import JSON format
     *
     * @throws AiException
     */
    public function recipe(?User $user, ?UploadedFile $file, ?string $text): array
    {
        if (! self::allows($user)) {
            throw new AiException('You may not use the AI features.');
        }

        $content = [];

        if ($file !== null) {
            $content[] = OpenAiClient::file($file);
        }

        if (filled($text)) {
            // A web address: the page's recipe data and text instead of the address itself.
            $content[] = OpenAiClient::text(RecipePage::isUrl($text) ? $this->page->text((string) $text) : (string) $text);
        }

        if ($content === []) {
            throw new AiException('Add a photo, PDF or text of the recipe.');
        }

        $recipe = $this->limited($user, 'recipe', fn (): array => $this->client->structured(
            $this->prompt->build($user)."\n\nAntworte direkt mit dem JSON-Objekt gemäss Schema, ohne Code-Block.",
            $content,
            'recipe',
            RecipeJsonSchema::aiSchema(),
        ));

        // The prompt asks for Swiss spelling; this makes sure of it.
        return SwissSpelling::recipe($recipe);
    }

    /**
     * Generates a photo of the dish and adds it to the recipe, marked as AI generated.
     *
     * @return array{path: string, source: string|null, is_ai_generated: bool}
     *
     * @throws AiException
     */
    public function image(?User $user, Recipe $recipe): array
    {
        $this->ensureRoom($recipe);
        $recipe->loadMissing('ingredients.food');

        $foods = $recipe->ingredients->pluck('food.name')->filter()->unique()->take(15)->implode(', ');
        $prompt = "Appetizing, realistic food photograph of the finished dish \"{$recipe->name}\""
            .($foods !== '' ? " (made with {$foods})" : '')
            .', served on a plate, natural daylight, shallow depth of field, no text, no people, no hands.';

        $binary = $this->limited($user, 'image', fn (): string => $this->client->image($prompt));

        return $this->store($recipe, $binary);
    }

    /**
     * Improves a photo of the recipe (light, colors, sharpness or the user's instruction) and adds
     * the result as an additional photo, marked as AI generated; the original stays.
     *
     * @return array{path: string, source: string|null, is_ai_generated: bool}
     *
     * @throws AiException
     */
    public function enhance(?User $user, Recipe $recipe, string $filename, ?string $instruction = null): array
    {
        $this->ensureRoom($recipe);
        $document = $recipe->photos->first(fn (Document $photo): bool => $photo->name() === basename($filename));
        $path = $recipe->getKey().'/'.basename($filename);

        if ($document === null || ! Storage::disk('recipes')->exists($path)) {
            throw new AiException('The photo was not found.');
        }

        $binary = (string) Storage::disk('recipes')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        if (! in_array($mime, self::EDITABLE_MIMES, true)) {
            throw new AiException('Only JPEG, PNG and WebP photos can be enhanced.');
        }

        $instruction = trim((string) $instruction) !== '' ? trim((string) $instruction) : self::DEFAULT_ENHANCE_INSTRUCTION;
        $prompt = "Edit this food photograph of the dish \"{$recipe->name}\". {$instruction} Keep it realistic, no text, no people, no hands.";

        $edited = $this->limited($user, 'image_edit', fn (): string => $this->client->editImage($binary, $mime, $prompt));

        return $this->store($recipe, $edited);
    }

    /**
     * Refused before the AI request when the recipe already has the maximum of photos.
     *
     * @throws AiException
     */
    private function ensureRoom(Recipe $recipe): void
    {
        if (! $this->photos->hasRoom($recipe)) {
            throw new AiException(RecipePhotos::FULL);
        }
    }

    /**
     * @return array{path: string, source: string|null, is_ai_generated: bool}
     *
     * @throws AiException
     */
    private function store(Recipe $recipe, string $binary): array
    {
        try {
            return $this->photos->add($recipe, $binary, 'jpg', (string) config('services.openai.image_source'), true, 'ai');
        } catch (InvalidArgumentException $exception) {
            throw new AiException($exception->getMessage());
        }
    }

    /**
     * Estimates measure, density, piece weights and Swiss season months of new foods in one request.
     *
     * @param  array<string, list<string>>  $foods  food name => units used with it in the recipe
     * @return array<string, array{measure: string|null, density: float|null, weights: array<int, float>, season_months: list<int>, storage_months: list<int>}> by food name as given
     *
     * @throws AiException
     */
    public function conversions(?User $user, array $foods): array
    {
        $pieceUnits = $this->conversions->pieceUnits($user);
        $names = array_keys($foods);

        $instructions = 'You estimate unit conversion data for foods of a Swiss recipe app (German food names, Swiss units). '
            .'For every given food answer with: "measure": "mass" when amounts of it are best given in grams (flour, sugar, vegetables, meat, cheese), '
            .'"volume" for liquids measured in ml/dl (milk, water, oil, wine, cream), null when neither fits; '
            .'"density": average grams per milliliter of the food as used in recipes (water 1.0, oil 0.92, flour 0.55, sugar 0.85, honey 1.4), null if unknown; '
            .'"piece_weights": average grams of one piece unit, only for piece units from the list that make sense for the food '
            .'(e.g. Stück Ei 60, Zehe Knoblauch 5, Bund Petersilie 30), especially the units used with it in the recipe; empty when none fits. '
            .'"season_months": months (1-12) of fresh Swiss harvest according to the Swiss seasonal calendar (open field or unheated greenhouse, '
            .'e.g. Erdbeeren 5-8, Spargel 4-6); "storage_months": months it is only available from Swiss storage, not fresh (e.g. Äpfel 1-7, Rüebli 1-5), '
            .'never the same month in both. Both empty for foods without a Swiss season (spices, oil, salt, sugar, flour, pasta, rice, dairy, meat, '
            .'imported fruit such as bananas or lemons, processed products). '
            .'Use realistic averages for Switzerland. Answer every food once, with its name exactly as given.';

        $input = json_encode([
            'foods' => array_map(fn (string $name): array => ['name' => $name, 'units_in_recipe' => $foods[$name]], $names),
            'piece_units' => array_values($pieceUnits),
        ], JSON_UNESCAPED_UNICODE);

        $unit = $pieceUnits !== [] ? ['type' => 'string', 'enum' => array_values($pieceUnits)] : ['type' => 'string'];
        $months = ['type' => 'array', 'items' => ['type' => 'integer', 'enum' => range(1, 12)]];
        $schema = [
            'type' => 'object',
            'properties' => [
                'foods' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'measure' => ['type' => ['string', 'null'], 'enum' => ['mass', 'volume', null]],
                            'density' => ['type' => ['number', 'null']],
                            'piece_weights' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => ['unit' => $unit, 'grams' => ['type' => 'number']],
                                    'required' => ['unit', 'grams'],
                                    'additionalProperties' => false,
                                ],
                            ],
                            'season_months' => $months,
                            'storage_months' => $months,
                        ],
                        'required' => ['name', 'measure', 'density', 'piece_weights', 'season_months', 'storage_months'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['foods'],
            'additionalProperties' => false,
        ];

        $answer = $this->limited($user, 'conversion', fn (): array => $this->client->structured(
            $instructions,
            [OpenAiClient::text((string) $input)],
            'conversions',
            $schema,
        ));

        $estimates = [];

        foreach ((array) ($answer['foods'] ?? []) as $estimate) {
            if (is_array($estimate) && is_string($estimate['name'] ?? null)) {
                $estimates[mb_strtolower(trim($estimate['name']))] = $estimate;
            }
        }

        $result = [];

        foreach ($names as $name) {
            $estimate = $estimates[mb_strtolower(trim($name))] ?? null;

            if ($estimate !== null) {
                $result[$name] = $this->conversions->clean($estimate, $pieceUnits);
            }
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $request
     * @return T
     *
     * @throws AiException
     */
    private function limited(?User $user, string $feature, callable $request): mixed
    {
        if ($user === null || ! self::allows($user)) {
            throw new AiException('You may not use the AI features.');
        }

        $key = 'ai:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, (int) config('services.openai.hourly_limit'))) {
            throw new AiException('Too many AI requests. Please try again later.');
        }

        RateLimiter::hit($key, 3600);

        $result = $request();

        $usage = $this->client->lastUsage['usage'] ?? [];
        AiUsage::query()->create([
            'user_id' => $user->getKey(),
            'feature' => $feature,
            'model' => $this->client->lastUsage['model'] ?? null,
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'images' => in_array($feature, ['image', 'image_edit'], true) ? 1 : 0,
        ]);

        return $result;
    }
}
