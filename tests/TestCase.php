<?php

namespace Tests;

use App\Services\PublicHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Resolves hosts for PublicHttp without DNS: the given ones to their addresses, all others to a
     * public address.
     *
     * @param  array<string, list<string>>  $addresses
     */
    protected function fakeDns(array $addresses = []): void
    {
        $this->app->instance(PublicHttp::class, new class($addresses) extends PublicHttp
        {
            /**
             * @param  array<string, list<string>>  $addresses
             */
            public function __construct(private array $addresses) {}

            protected function addresses(string $host): array
            {
                return $this->addresses[$host] ?? ['93.184.215.14'];
            }
        });
    }
}
