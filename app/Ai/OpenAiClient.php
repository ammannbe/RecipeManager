<?php

namespace App\Ai;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Minimal client for the OpenAI Responses API (structured JSON output) and the Images API.
 * No SDK: two endpoints are enough, and Http::fake() keeps the tests simple. Same approach as
 * the Shoppinglist app.
 */
class OpenAiClient
{
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws AiException
     */
    private function post(string $path, array $payload, string $name): array
    {
        if (! self::enabled()) {
            throw new AiException('The AI assistant is not configured.');
        }

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout((int) config('services.openai.timeout'))
                ->post(config('services.openai.url').$path, $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            report($exception);

            throw new AiException('The AI request failed.', previous: $exception);
        }

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
