<?php

namespace Tests\Feature;

use App\Services\PublicHttp;
use App\Services\PublicUrlException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicHttpTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    private int $port = 0;

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        parent::tearDown();
    }

    private function refused(string $url, string $message, ?PublicHttp $http = null, int $maxBytes = 1000, int $timeout = 15): void
    {
        try {
            ($http ?? app(PublicHttp::class))->get($url, $maxBytes, $timeout);
            $this->fail('Expected a PublicUrlException for '.$url);
        } catch (PublicUrlException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    /**
     * Starts tests/fixtures/public-http-server.php on a free loopback port and returns a PublicHttp
     * that may reach it: host names resolve to 127.0.0.1 (no DNS, so only CURLOPT_RESOLVE makes
     * them reachable), loopback and the server's port are allowed, everything else is checked as usual.
     */
    private function localServer(): PublicHttp
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $port = $this->port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$port, base_path('tests/fixtures/public-http-server.php')],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        ) ?: null;

        $connection = false;

        for ($try = 0; $try < 50 && ! ($connection = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1)); $try++) {
            usleep(100000);
        }
        $this->assertNotFalse($connection, 'The local test server did not start.');
        fclose($connection);

        return new class($port) extends PublicHttp
        {
            public function __construct(private int $port) {}

            protected function allows(string $ip): bool
            {
                return in_array($ip, ['127.0.0.1', '::1'], true) || parent::allows($ip);
            }

            protected function allowsPort(?int $port): bool
            {
                return $port === $this->port;
            }

            protected function addresses(string $host): array
            {
                return $host === 'dual.test' ? ['::1', '127.0.0.1'] : ['127.0.0.1'];
            }
        };
    }

    public function test_redirects_are_followed_and_checked_again(): void
    {
        $this->fakeDns(['intern.test' => ['10.0.0.5'], 'v6.test' => ['fd00::1']]);
        Http::fake([
            'seite.test/alt' => Http::response('', 301, ['Location' => '/neu']),
            'seite.test/neu' => Http::response('Inhalt'),
            'seite.test/intern' => Http::response('', 302, ['Location' => 'http://intern.test/admin']),
            'seite.test/v6' => Http::response('', 302, ['Location' => 'https://v6.test/']),
            'seite.test/gross' => Http::response(str_repeat('a', 1001)),
            'seite.test/kaputt' => Http::response('', 302, ['Location' => 'http://:80/']),
        ]);

        $this->assertSame('Inhalt', app(PublicHttp::class)->get('https://seite.test/alt', 1000));

        $this->refused('https://seite.test/intern', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://seite.test/v6', PublicUrlException::NOT_PUBLIC);
        $this->refused('http://169.254.169.254/latest/meta-data', PublicUrlException::NOT_PUBLIC);
        $this->refused('http://[::1]/', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://seite.test:8443/', PublicUrlException::NOT_PUBLIC);
        $this->refused('file:///etc/passwd', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://seite.test/gross', PublicUrlException::TOO_LARGE);
        $this->refused('https://seite.test/kaputt', PublicUrlException::FAILED);

        Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), 'seite.test'));
    }

    public function test_internal_ranges_are_not_public(): void
    {
        foreach ([
            '10.0.0.1', '127.0.0.1', '169.254.169.254', '172.16.0.1', '192.168.1.1', '0.0.0.0',
            '100.64.0.1', '100.127.255.254', '192.0.0.8', '198.18.0.1', '198.19.255.255',
            '::1', '::', 'fd00::1', 'fe80::1', '64:ff9b::a00:1', '64:ff9b:1::1', '2002:a00:1::1',
            '::ffff:10.0.0.1', '::ffff:127.0.0.1', '::ffff:a00:1', '::a00:1', 'kein-ip',
        ] as $ip) {
            $this->assertFalse(PublicHttp::isPublicIp($ip), $ip);
        }

        foreach (['93.184.215.14', '100.63.255.255', '100.128.0.1', '198.20.0.1', '2a00:1450:4001::1', '::ffff:93.184.215.14'] as $ip) {
            $this->assertTrue(PublicHttp::isPublicIp($ip), $ip);
        }

        $this->fakeDns(['cgnat.test' => ['100.64.1.2'], 'mapped.test' => ['93.184.215.14', '::ffff:192.168.1.1']]);
        Http::fake();

        $this->refused('https://cgnat.test/', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://mapped.test/', PublicUrlException::NOT_PUBLIC);
        $this->refused('http://[::ffff:10.0.0.1]/', PublicUrlException::NOT_PUBLIC);
        $this->refused('http://[64:ff9b::7f00:1]/', PublicUrlException::NOT_PUBLIC);
        Http::assertNothingSent();
    }

    public function test_the_real_curl_handler_pins_the_host_and_aborts_above_the_size_limit(): void
    {
        $http = $this->localServer();
        $base = 'http://rezepte.test:'.$this->port;

        // rezepte.test has no DNS entry: only the pinned address (CURLOPT_RESOLVE) reaches the server.
        $this->assertSame('Host: rezepte.test:'.$this->port.', Accept-Encoding: identity', $http->get($base.'/redirect', 1000));
        $this->assertSame('Host: dual.test:'.$this->port.', Accept-Encoding: identity', $http->get('http://dual.test:'.$this->port.'/page', 1000));

        $this->refused($base.'/internal', PublicUrlException::NOT_PUBLIC, $http);
        $this->refused($base.'/large', PublicUrlException::TOO_LARGE, $http);
        $this->refused($base.'/missing', PublicUrlException::FAILED, $http);

        $start = microtime(true);
        $this->refused($base.'/stream', PublicUrlException::TOO_LARGE, $http, maxBytes: 1500);
        $this->assertLessThan(1.5, microtime(true) - $start, 'The transfer was not aborted at the limit.');

        // The timeout covers the whole request (last: the single-threaded server stays busy).
        $this->refused($base.'/slow', PublicUrlException::FAILED, $http, timeout: 1);
    }
}
