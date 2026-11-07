<?php

namespace App\Jobs;

use App\Households\KontoHouseholds;
use App\Households\KontoUnavailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Applies a household webhook of the AccountManager (queued, so the webhook answers fast). If the
 * AccountManager cannot be asked about an unknown household, the job is tried again later; after
 * the last try the daily households:sync heals the state.
 */
class ApplyKontoEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @param  array{uuid: string, name: string, version: int, members: list<array{sub: string, role: string}>, deleted?: bool}  $snapshot
     */
    public function __construct(public string $type, public array $snapshot) {}

    public function handle(KontoHouseholds $households): void
    {
        if (! KontoHouseholds::enabled()) {
            return;
        }

        try {
            $households->handleEvent($this->type, $this->snapshot);
        } catch (KontoUnavailable $exception) {
            KontoHouseholds::logUnavailable($exception, "applying {$this->type} of {$this->snapshot['uuid']}");

            if ($this->attempts() < $this->tries) {
                $this->release([60, 300, 900, 3600][$this->attempts() - 1] ?? 3600);
            } else {
                Log::error('Households: giving up on a webhook event, households:sync will heal it.', ['household' => $this->snapshot['uuid']]);
            }
        }
    }
}
