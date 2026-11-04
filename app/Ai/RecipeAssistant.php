<?php

namespace App\Ai;

use App\Models\AiUsage;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Document;
use App\Services\RecipeImport\ImportPromptBuilder;
use App\Services\RecipeImport\RecipeJsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * AI features of the recipe app: turns a photo, PDF or text into the import JSON (the same
 * prompt as "manually via chat", the user still resolves and confirms the import) and creates
 * a recipe photo. Verified users with users.ai_enabled, limited per hour.
 */
class RecipeAssistant
{
    public function __construct(
        private OpenAiClient $client,
        private ImportPromptBuilder $prompt,
        private RecipePage $page,
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

        return $this->limited($user, 'recipe', fn (): array => $this->client->structured(
            $this->prompt->build($user)."\n\nAntworte direkt mit dem JSON-Objekt gemäss Schema, ohne Code-Block.",
            $content,
            'recipe',
            RecipeJsonSchema::aiSchema(),
        ));
    }

    /**
     * Generates a photo of the dish and adds it to the recipe, marked as AI generated.
     *
     * @throws AiException
     */
    public function image(?User $user, Recipe $recipe): void
    {
        $recipe->loadMissing('ingredients.food');

        $foods = $recipe->ingredients->pluck('food.name')->filter()->unique()->take(15)->implode(', ');
        $prompt = "Appetizing, realistic food photograph of the finished dish \"{$recipe->name}\""
            .($foods !== '' ? " (made with {$foods})" : '')
            .', served on a plate, natural daylight, shallow depth of field, no text, no people, no hands.';

        $binary = $this->limited($user, 'image', fn (): string => $this->client->image($prompt));

        $filename = Str::slug($recipe->name).'-ai-'.Str::lower(Str::random(6)).'.jpg';
        Storage::disk('recipes')->put($recipe->getKey().'/'.$filename, $binary);

        $photos = array_map(fn (Document $photo): array => $photo->jsonSerialize(), array_values($recipe->photos->all()));
        $photos[] = ['path' => $filename, 'source' => null, 'is_ai_generated' => true];

        $recipe->photos = $photos;
        $recipe->save();
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
            'images' => $feature === 'image' ? 1 : 0,
        ]);

        return $result;
    }
}
