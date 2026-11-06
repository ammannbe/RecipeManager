<?php

namespace App\Services;

use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * GET requests to addresses users or AI clients hand in (recipe pages, photos): only public
 * http(s) hosts; redirects are followed by hand and checked again, so a page cannot point the
 * server at internal addresses. The connection is pinned to the checked addresses (CURLOPT_RESOLVE),
 * so a second DNS answer cannot swap in an internal one. cURL aborts the transfer above the size
 * limit (Content-Length and received bytes); the body is not decompressed (no gzip bombs).
 */
class PublicHttp
{
    private const MAX_REDIRECTS = 3;

    /**
     * Never public, listed explicitly instead of relying on the PHP version's FILTER_FLAG_GLOBAL_RANGE:
     * shared address space (CGNAT), IETF protocol assignments, benchmarking, and the 6to4, NAT64 and
     * IPv4-compatible ranges, which embed an IPv4 address that may be internal.
     */
    private const BLOCKED_RANGES = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '198.18.0.0/15',
        '2002::/16',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '::/96',
    ];

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
            $tooLarge = false;

            try {
                $response = Http::timeout($timeout)
                    ->withOptions([
                        'allow_redirects' => false,
                        'decode_content' => false,
                        'curl' => $resolve !== null ? [CURLOPT_RESOLVE => [$resolve]] : [],
                        'on_headers' => function (ResponseInterface $response) use ($maxBytes, &$tooLarge): void {
                            if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                                $tooLarge = true;

                                throw new RuntimeException('Content-Length above the limit.');
                            }
                        },
                        'progress' => function (int $downloadTotal, int $downloaded) use ($maxBytes, &$tooLarge): bool {
                            return $tooLarge = $tooLarge || $downloaded > $maxBytes;
                        },
                    ])
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; RecipeManager)', ...$headers, 'Accept-Encoding' => 'identity'])
                    ->get($url);

                $contents = $response->body();
            } catch (HttpClientException|TransferException|InvalidArgumentException|RuntimeException) {
                throw new PublicUrlException($tooLarge ? PublicUrlException::TOO_LARGE : PublicUrlException::FAILED);
            }

            if ($response->redirect() && $response->header('Location') !== '') {
                try {
                    $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
                } catch (InvalidArgumentException) {
                    throw new PublicUrlException(PublicUrlException::FAILED);
                }

                continue;
            }

            if (! $response->successful()) {
                throw new PublicUrlException(PublicUrlException::FAILED);
            }

            if (strlen($contents) > $maxBytes) {
                throw new PublicUrlException(PublicUrlException::TOO_LARGE);
            }

            return $contents;
        }

        throw new PublicUrlException(PublicUrlException::FAILED);
    }

    /**
     * Whether the address is a public one: no private, reserved, shared (CGNAT), benchmark or
     * transition range; IPv4-mapped IPv6 addresses are judged by their IPv4 address.
     */
    public static function isPublicIp(string $ip): bool
    {
        $binary = @inet_pton($ip);

        if ($binary === false) {
            return false;
        }

        if (strlen($binary) === 16 && str_starts_with($binary, str_repeat("\0", 10)."\xff\xff")) {
            return self::isPublicIp((string) inet_ntop(substr($binary, 12)));
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! IpUtils::checkIp($ip, self::BLOCKED_RANGES);
    }

    /**
     * Checks the address and returns the CURLOPT_RESOLVE entry ("host:port:ip,…", IPv4 first) for
     * a host name.
     *
     * @throws PublicUrlException
     */
    private function guard(string $url): ?string
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || $host === '' || isset($parts['user']) || ! $this->allowsPort($parts['port'] ?? null)) {
            throw new PublicUrlException(PublicUrlException::NOT_PUBLIC);
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->addresses($host);

        foreach ($ips as $ip) {
            if (! $this->allows($ip)) {
                throw new PublicUrlException(PublicUrlException::NOT_PUBLIC);
            }
        }

        if ($ips === []) {
            throw new PublicUrlException(PublicUrlException::FAILED);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        usort($ips, fn (string $a, string $b): int => str_contains($a, ':') <=> str_contains($b, ':'));
        $port = $parts['port'] ?? (strtolower((string) $parts['scheme']) === 'https' ? 443 : 80);

        return $host.':'.$port.':'.implode(',', array_map(fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip, $ips));
    }

    /**
     * Whether the server may connect to the address. Tests override it to reach a local test server.
     */
    protected function allows(string $ip): bool
    {
        return self::isPublicIp($ip);
    }

    /**
     * Only the scheme's default port. Tests override it to reach a local test server.
     */
    protected function allowsPort(?int $port): bool
    {
        return $port === null;
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
