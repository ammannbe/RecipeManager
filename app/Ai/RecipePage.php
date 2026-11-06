<?php

namespace App\Ai;

use App\Services\PublicHttp;
use App\Services\PublicUrlException;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Reads a public recipe page for the AI import: the schema.org Recipe data (JSON-LD, most recipe
 * sites have it) plus the visible text. Only public http(s) hosts, redirects checked again
 * (PublicHttp), so a page cannot point the server at internal addresses.
 */
class RecipePage
{
    private const MAX_BYTES = 3 * 1024 * 1024;

    private const MAX_TEXT = 20000;

    public function __construct(private PublicHttp $http) {}

    public static function isUrl(?string $text): bool
    {
        $text = trim((string) $text);

        return $text !== '' && ! str_contains($text, "\n") && preg_match('#^https?://\S+$#i', $text) === 1;
    }

    /**
     * @throws AiException
     */
    public function text(string $url): string
    {
        $html = $this->fetch(trim($url));

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        $recipes = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $node) {
            if ($node instanceof DOMNode && str_contains($node->textContent, 'Recipe')) {
                $recipes[] = trim($node->textContent);
            }
        }

        foreach ($xpath->query('//script|//style|//noscript|//svg|//nav|//footer|//header') ?: [] as $node) {
            if ($node instanceof DOMNode) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Text nodes joined with spaces, otherwise "4 Portionen" and "Menge" from separate cells run together.
        $parts = [];
        foreach ($xpath->query('//body//text()') ?: [] as $node) {
            if ($node instanceof DOMNode) {
                $parts[] = $node->textContent;
            }
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', implode(' ', $parts)));

        return Str::limit(
            "Rezeptseite: {$url}\n\n"
            .($recipes !== [] ? "Strukturierte Rezeptdaten (schema.org):\n".implode("\n", $recipes)."\n\n" : '')
            ."Seitentext:\n".$text,
            self::MAX_TEXT,
        );
    }

    /**
     * @throws AiException
     */
    private function fetch(string $url): string
    {
        try {
            return $this->http->get($url, self::MAX_BYTES, 15, ['Accept' => 'text/html']);
        } catch (PublicUrlException $exception) {
            throw new AiException($exception->isNotPublic() ? PublicUrlException::NOT_PUBLIC : 'The recipe page could not be loaded.');
        }
    }
}
