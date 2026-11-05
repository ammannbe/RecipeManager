<?php

namespace Tests\Feature;

use App\Services\PublicHttp;
use App\Services\PublicUrlException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicHttpTest extends TestCase
{
    private function refused(string $url, string $message): void
    {
        try {
            app(PublicHttp::class)->get($url, 1000);
            $this->fail('Expected a PublicUrlException for '.$url);
        } catch (PublicUrlException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
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
        ]);

        $this->assertSame('Inhalt', app(PublicHttp::class)->get('https://seite.test/alt', 1000));

        $this->refused('https://seite.test/intern', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://seite.test/v6', PublicUrlException::NOT_PUBLIC);
        $this->refused('http://169.254.169.254/latest/meta-data', PublicUrlException::NOT_PUBLIC);
        $this->refused('http://[::1]/', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://seite.test:8443/', PublicUrlException::NOT_PUBLIC);
        $this->refused('file:///etc/passwd', PublicUrlException::NOT_PUBLIC);
        $this->refused('https://seite.test/gross', PublicUrlException::TOO_LARGE);

        Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), 'seite.test'));
    }
}
