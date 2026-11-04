<?php

namespace App\Ai;

use DOMDocument;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads a public recipe page for the AI import: the schema.org Recipe data (JSON-LD, most recipe
 * sites have it) plus the visible text. Only public http(s) hosts; redirects are checked again,
 * so a page cannot point the server at internal addresses.
 */
class RecipePage
{
    private const MAX_BYTES = 3 * 1024 * 1024;

    private const MAX_TEXT = 20000;

    private const MAX_REDIRECTS = 3;

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
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $this->guard($url);

            try {
                $response = Http::timeout(15)
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders(['Accept' => 'text/html', 'User-Agent' => 'Mozilla/5.0 (compatible; RecipeManager)'])
                    ->get($url);
            } catch (ConnectionException) {
                throw new AiException('The recipe page could not be loaded.');
            }

            if ($response->redirect() && $response->header('Location') !== '') {
                $url = (string) UriResolver::resolve(
                    new Uri($url),
                    new Uri($response->header('Location')),
                );

                continue;
            }

            if (! $response->successful() || strlen($response->body()) > self::MAX_BYTES) {
                throw new AiException('The recipe page could not be loaded.');
            }

            return $response->body();
        }

        throw new AiException('The recipe page could not be loaded.');
    }

    /**
     * @throws AiException
     */
    private function guard(string $url): void
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['port'])) {
            throw new AiException('Only public web addresses can be read.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->addresses($host);

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new AiException('Only public web addresses can be read.');
            }
        }

        if ($ips === []) {
            throw new AiException('The recipe page could not be loaded.');
        }
    }

    /**
     * IPv4 and IPv6 addresses of the host.
     *
     * @return list<string>
     */
    protected function addresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
    }
}
