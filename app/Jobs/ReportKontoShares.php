<?php

namespace App\Jobs;

use App\Households\KontoClient;
use App\Households\KontoHouseholds;
use App\Households\KontoUnavailable;
use App\Models\Cookbook;
use App\Models\Household;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells the AccountManager which cookbooks are shared with a mirrored household (PUT shares, only
 * for the overview there). Only changes are sent (hash of the last report); failures are harmless,
 * households:sync reports again.
 */
class ReportKontoShares implements ShouldQueue
{
    use Queueable;

    public const RESOURCE_TYPE = 'cookbook';

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Household $household) {}

    /**
     * @param  Household|iterable<Household>  $households
     */
    public static function dispatchFor(Household|iterable $households): void
    {
        if (! KontoHouseholds::enabled() || ! config('households.konto.report_shares')) {
            return;
        }

        foreach ($households instanceof Household ? [$households] : $households as $household) {
            if ($household->konto_household_uuid !== null) {
                static::dispatch($household)->afterCommit();
            }
        }
    }

    /**
     * Cookbooks in the trash are not reported.
     *
     * @return list<array{resource_type: string, resource_id: string, label: string}>
     */
    public static function shares(Household $household): array
    {
        return array_values($household->cookbooks()
            ->orderBy('cookbooks.id')
            ->limit(500)
            ->get()
            ->map(fn (Cookbook $cookbook): array => [
                'resource_type' => self::RESOURCE_TYPE,
                'resource_id' => (string) $cookbook->getKey(),
                'label' => mb_substr(trim($cookbook->name), 0, 255),
            ])
            ->all());
    }

    public function handle(KontoClient $client): void
    {
        $household = $this->household->fresh();

        if ($household?->konto_household_uuid === null || ! KontoHouseholds::enabled()) {
            return;
        }

        $shares = self::shares($household);
        $hash = hash('sha256', json_encode($shares, JSON_THROW_ON_ERROR));

        if ($hash === $household->konto_shares_hash) {
            return;
        }

        try {
            if ($client->putShares($household->konto_household_uuid, $shares)) {
                $household->forceFill(['konto_shares_hash' => $hash])->saveQuietly();
            }
        } catch (KontoUnavailable $exception) {
            KontoHouseholds::logUnavailable($exception, 'reporting the shares');

            if ($this->attempts() < $this->tries) {
                $this->release(300);
            }
        }
    }
}
