<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * GET requests to addresses users or AI clients hand in (recipe pages, photos): only public
 * http(s) hosts; redirects are followed by hand and checked again, so a page cannot point the
 * server at internal addresses. The connection is pinned to the checked address (CURLOPT_RESOLVE),
 * so a second DNS answer cannot swap in an internal one. The body is read in chunks up to a size limit.
 */
class PublicHttp
{
    private const MAX_REDIRECTS = 3;

    /**
     * @param  array<string, string>  $headers
     *
     * @throws PublicUrlException
     */
    public function get(string $url, int $maxBytes, int $timeout = 15, array $headers = []): string
    {
        $url = trim($url);

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $resolve = $this->guard($url);

            try {
                $response = Http::timeout($timeout)
                    ->withOptions(['allow_redirects' => false, 'stream' => true, 'curl' => $resolve !== null ? [CURLOPT_RESOLVE => [$resolve]] : []])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; RecipeManager)', ...$headers])
                    ->get($url);
            } catch (ConnectionException) {
                throw new PublicUrlException(PublicUrlException::FAILED);
            }

            if ($response->redirect() && $response->header('Location') !== '') {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));

                continue;
            }

            if (! $response->successful()) {
                throw new PublicUrlException(PublicUrlException::FAILED);
            }

            $body = $response->toPsrResponse()->getBody();
            $contents = '';

            while (! $body->eof()) {
                $contents .= $body->read(65536);

                if (strlen($contents) > $maxBytes) {
                    throw new PublicUrlException(PublicUrlException::TOO_LARGE);
                }
            }

            return $contents;
        }

        throw new PublicUrlException(PublicUrlException::FAILED);
    }

    /**
     * Checks the address and returns the CURLOPT_RESOLVE entry ("host:port:ip") for a host name.
     *
     * @throws PublicUrlException
     */
    private function guard(string $url): ?string
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['port'])) {
            throw new PublicUrlException(PublicUrlException::NOT_PUBLIC);
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->addresses($host);

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new PublicUrlException(PublicUrlException::NOT_PUBLIC);
            }
        }

        if ($ips === []) {
            throw new PublicUrlException(PublicUrlException::FAILED);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $port = strtolower((string) $parts['scheme']) === 'https' ? 443 : 80;

        return $host.':'.$port.':'.(str_contains($ips[0], ':') ? '['.$ips[0].']' : $ips[0]);
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
