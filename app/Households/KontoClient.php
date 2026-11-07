<?php

namespace App\Households;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Households API of the AccountManager (docs/HOUSEHOLDS-API.md there, section 3): HTTP Basic with
 * the service key and the service secret. Every failure is a KontoUnavailable, 404 is an answer.
 *
 * @phpstan-type Snapshot array{uuid: string, name: string, version: int, members: list<array{sub: string, role: string}>}
 * @phpstan-type ImportState array{local_id: string, status: string, household_uuid: string|null, household: Snapshot|null}
 */
class KontoClient
{
    /**
     * Total timeout of a request in seconds (the OIDC login uses a shorter one).
     */
    private int $timeout = 10;

    public static function configured(): bool
    {
        return filled(config('households.konto.url'))
            && filled(config('households.konto.key'))
            && filled(config('households.konto.secret'));
    }

    public function withTimeout(int $seconds): static
    {
        $client = clone $this;
        $client->timeout = $seconds;

        return $client;
    }

    /**
     * All households of a user (3.2).
     *
     * @return list<Snapshot>
     */
    public function households(string $sub): array
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get('households', ['sub' => $sub]));

        return array_map(fn (mixed $snapshot): array => $this->snapshot($snapshot), $this->list($response->json('data')));
    }

    /**
     * One household (3.3), null if it is gone or no longer concerns a user of this app (404).
     *
     * @return Snapshot|null
     */
    public function household(string $uuid): ?array
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get('households/'.rawurlencode($uuid)), allowNotFound: true);

        return $response->notFound() ? null : $this->snapshot($response->json('data'));
    }

    /**
     * Replaces what this app shares with the household (3.4). False if the household is unknown there.
     *
     * @param  list<array{resource_type: string, resource_id: string, label: string}>  $shares
     */
    public function putShares(string $uuid, array $shares): bool
    {
        $response = $this->send(
            fn (PendingRequest $http): Response => $http->put('households/'.rawurlencode($uuid).'/shares', ['shares' => $shares]),
            allowNotFound: true,
        );

        return ! $response->notFound();
    }

    /**
     * Proposes local households for the switch (3.5), idempotent by local_id.
     *
     * @param  list<array{local_id: string, name: string, members: list<array{sub: string, role: string}>}>  $households
     * @return list<ImportState>
     */
    public function propose(array $households): array
    {
        return $this->importStates($this->send(fn (PendingRequest $http): Response => $http->post('households/import', ['households' => $households])));
    }

    /**
     * State of all proposals of this app (3.5).
     *
     * @return list<ImportState>
     */
    public function importStatus(): array
    {
        return $this->importStates($this->send(fn (PendingRequest $http): Response => $http->get('households/import')));
    }

    /**
     * Validates a snapshot from a webhook or an API answer.
     *
     * @return array{uuid: string, name: string, version: int, members: list<array{sub: string, role: string}>, deleted?: bool}
     *
     * @throws ValidationException
     */
    public static function validSnapshot(mixed $snapshot): array
    {
        /** @var array{uuid: string, name: string, version: int|string, members: array<int, array{sub: string, role?: string|null}>, deleted?: bool} $data */
        $data = Validator::make(['household' => $snapshot], [
            'household' => ['required', 'array'],
            'household.uuid' => ['required', 'uuid'],
            'household.name' => ['required', 'string', 'max:255'],
            'household.version' => ['required', 'integer', 'min:0'],
            'household.members' => ['present', 'array'],
            'household.members.*.sub' => ['required', 'string', 'max:255'],
            'household.members.*.role' => ['nullable', 'string'],
            'household.deleted' => ['sometimes', 'boolean'],
        ])->validate()['household'];

        return [
            'uuid' => strtolower($data['uuid']),
            'name' => trim($data['name']),
            'version' => (int) $data['version'],
            'members' => array_values(array_map(fn (array $member): array => [
                'sub' => (string) $member['sub'],
                'role' => ($member['role'] ?? null) === 'admin' ? 'admin' : 'member',
            ], $data['members'])),
            ...(isset($data['deleted']) ? ['deleted' => (bool) $data['deleted']] : []),
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('households.konto.url'), '/').'/api')
            ->withBasicAuth((string) config('households.konto.key'), (string) config('households.konto.secret'))
            ->acceptJson()
            ->connectTimeout(min(3, $this->timeout))
            ->timeout($this->timeout)
            ->withoutRedirecting();
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request, bool $allowNotFound = false): Response
    {
        if (! self::configured()) {
            throw new KontoUnavailable('The AccountManager is not configured (KONTO_URL, KONTO_SERVICE_KEY, KONTO_SECRET).');
        }

        try {
            $response = $request($this->http());
        } catch (ConnectionException $exception) {
            throw new KontoUnavailable('The AccountManager is not reachable: '.$exception->getMessage(), previous: $exception);
        }

        if ($response->successful() || ($allowNotFound && $response->notFound())) {
            return $response;
        }

        throw new KontoUnavailable("The AccountManager answered with HTTP {$response->status()}.");
    }

    /**
     * @return Snapshot
     */
    private function snapshot(mixed $data): array
    {
        try {
            $snapshot = self::validSnapshot($data);
        } catch (ValidationException) {
            throw new KontoUnavailable('The AccountManager returned an invalid household.');
        }

        unset($snapshot['deleted']);

        return $snapshot;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $data): array
    {
        if (! is_array($data) || ! array_is_list($data)) {
            throw new KontoUnavailable('The AccountManager returned an unexpected answer.');
        }

        return $data;
    }

    /**
     * @return list<ImportState>
     */
    private function importStates(Response $response): array
    {
        $states = [];

        foreach ($this->list($response->json('data')) as $state) {
            if (! is_array($state) || ! isset($state['local_id'], $state['status'])) {
                continue;
            }

            $confirmed = $state['status'] === 'confirmed' && is_array($state['household'] ?? null);

            $states[] = [
                'local_id' => (string) $state['local_id'],
                'status' => (string) $state['status'],
                'household_uuid' => $confirmed ? (string) $state['household_uuid'] : null,
                'household' => $confirmed ? $this->snapshot($state['household']) : null,
            ];
        }

        return $states;
    }
}
