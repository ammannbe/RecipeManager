<?php

namespace App\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal client for the OpenAI Responses API (structured JSON output) and the Images API
 * (generations, edits). No SDK: three endpoints are enough, and Http::fake() keeps the tests simple. Same approach as
 * the Shoppinglist app.
 */
class OpenAiClient
{
    /**
     * Model and usage of the last request (Responses API: input_tokens/output_tokens), recorded by
     * RecipeAssistant for billing by usage.
     *
     * @var array{model: string|null, usage: array<string, mixed>}|null
     */
    public ?array $lastUsage = null;

    public static function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @param  list<array<string, mixed>>  $content  input parts, see text(), file()
     * @param  array<string, mixed>  $schema  JSON schema of the answer (strict)
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    public function structured(string $instructions, array $content, string $name, array $schema): array
    {
        $payload = [
            'model' => config('services.openai.model'),
            'instructions' => $instructions,
            'input' => [['role' => 'user', 'content' => $content]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => $name, 'schema' => $schema, 'strict' => true]],
            'store' => false,
        ];

        if (filled(config('services.openai.reasoning_effort'))) {
            $payload['reasoning'] = ['effort' => config('services.openai.reasoning_effort')];
        }

        $response = $this->post('/responses', $payload, $name);

        /** @var list<array<string, mixed>> $output */
        $output = $response['output'] ?? [];

        $text = collect($output)
            ->where('type', 'message')
            ->flatMap(fn (array $message): array => $message['content'] ?? [])
            ->where('type', 'output_text')
            ->pluck('text')
            ->implode('');

        $data = json_decode($text, true);

        if (! is_array($data)) {
            report(new RuntimeException('Unexpected AI response: '.substr((string) json_encode($response), 0, 500)));

            throw new AiException('The AI answer could not be read.');
        }

        return $data;
    }

    /**
     * A generated JPEG (binary) for the prompt.
     *
     * @throws AiException
     */
    public function image(string $prompt): string
    {
        $response = $this->post('/images/generations', [
            'model' => config('services.openai.image_model'),
            'prompt' => $prompt,
            'size' => '1536x1024',
            'quality' => config('services.openai.image_quality'),
            'output_format' => 'jpeg',
            'output_compression' => 85,
            'n' => 1,
        ], 'image');

        $binary = base64_decode((string) ($response['data'][0]['b64_json'] ?? ''), true);

        if ($binary === false || $binary === '') {
            report(new RuntimeException('Unexpected AI image response: '.substr((string) json_encode($response), 0, 500)));

            throw new AiException('The AI answer could not be read.');
        }

        return $binary;
    }

    /**
     * An edited version (JPEG binary) of the image (JPEG, PNG or WebP binary) following the
     * prompt: Images API edit endpoint, multipart. GPT image models always answer with b64_json
     * (response_format is not supported for them); "size" auto keeps the aspect ratio.
     *
     * @throws AiException
     */
    public function editImage(string $image, string $mime, string $prompt): string
    {
        $payload = [
            'model' => config('services.openai.image_model'),
            'prompt' => $prompt,
            'size' => 'auto',
            'quality' => config('services.openai.image_quality'),
            'output_format' => 'jpeg',
            'output_compression' => 85,
            'n' => 1,
        ];

        // Not every GPT image model accepts it (gpt-image-2 always works with high fidelity).
        if (filled(config('services.openai.image_input_fidelity'))) {
            $payload['input_fidelity'] = config('services.openai.image_input_fidelity');
        }

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $response = $this->post('/images/edits', array_filter($payload, fn (mixed $value): bool => $value !== null), 'image_edit', ['image', $image, 'photo.'.$extension, $mime]);

        $binary = base64_decode((string) ($response['data'][0]['b64_json'] ?? ''), true);

        if ($binary === false || $binary === '') {
            report(new RuntimeException('Unexpected AI image response: '.substr((string) json_encode($response), 0, 500)));

            throw new AiException('The AI answer could not be read.');
        }

        return $binary;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{0: string, 1: string, 2: string, 3: string}|null  $file  multipart field, contents, filename, mime type
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    private function post(string $path, array $payload, string $name, ?array $file = null): array
    {
        if (! self::enabled()) {
            throw new AiException('The AI assistant is not configured.');
        }

        try {
            $request = Http::withToken((string) config('services.openai.key'))
                ->timeout((int) config('services.openai.timeout'));

            if ($file !== null) {
                $request->attach($file[0], $file[1], $file[2], ['Content-Type' => $file[3]]);
            }

            $response = $request
                ->post(config('services.openai.url').$path, $payload)
                ->throw()
                ->json();
        } catch (RequestException|ConnectionException $exception) {
            // Also timeouts: image edits can take long.
            report($exception);

            throw new AiException('The AI request failed.', previous: $exception);
        }

        $this->lastUsage = ['model' => $response['model'] ?? $payload['model'] ?? null, 'usage' => is_array($response['usage'] ?? null) ? $response['usage'] : []];

        // Cost control of the global key: tokens per request in the log.
        Log::info('OpenAI request', ['name' => $name, 'model' => $response['model'] ?? $payload['model'] ?? null, 'usage' => $response['usage'] ?? null]);

        return is_array($response) ? $response : [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function text(string $text): array
    {
        return ['type' => 'input_text', 'text' => $text];
    }

    /**
     * Image or PDF as inline data.
     *
     * @return array<string, mixed>
     */
    public static function file(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $data = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($file->getRealPath()));

        return $mime === 'application/pdf'
            ? ['type' => 'input_file', 'filename' => $file->getClientOriginalName() ?: 'document.pdf', 'file_data' => $data]
            : ['type' => 'input_image', 'image_url' => $data, 'detail' => 'high'];
    }
}
