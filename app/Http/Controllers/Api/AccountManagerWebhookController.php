<?php

namespace App\Http\Controllers\Api;

use App\Entitlements\AccountManagerEntitlements;
use App\Entitlements\Entitlements;
use App\Households\AccountManagerClient;
use App\Households\AccountManagerHouseholds;
use App\Http\Controllers\Controller;
use App\Jobs\ApplyAccountManagerEvent;
use App\Jobs\DeleteAccountManagerUser;
use App\Models\AccountManagerWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use JsonException;

/**
 * Events of the AccountManager (contract: AccountManager docs/HOUSEHOLDS-API.md, section 4, and
 * docs/ENTITLEMENTS-API.md, section 5): signed with HMAC-SHA256 over "{timestamp}.{body}", at most
 * 300 seconds old, accepted once per event_id. Household events (HOUSEHOLDS_DRIVER=account-manager)
 * and user.deleted are applied by queued jobs, entitlements.changed (ENTITLEMENTS_DRIVER or
 * AUTH_ADMIN_SOURCE=account-manager) right away.
 */
class AccountManagerWebhookController extends Controller
{
    public const TOLERANCE = 300;

    public function __invoke(Request $request, AccountManagerEntitlements $entitlements): Response
    {
        // Nothing from the AccountManager in use (local drivers or not configured): it should not send here.
        abort_unless(self::enabled(), 404);

        $body = $request->getContent();

        abort_unless(self::verify(
            (string) config('households.account_manager.secret'),
            (string) $request->header('X-Account-Manager-Timestamp'),
            $body,
            (string) $request->header('X-Account-Manager-Signature'),
        ), 401);

        try {
            $event = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(400);
        }

        /** @var array{event_id: string, type: string, data?: array<string, mixed>|null} $event */
        $event = Validator::make(is_array($event) ? $event : [], [
            'event_id' => ['required', 'uuid'],
            'type' => ['required', 'string', 'max:100'],
            'data' => ['nullable', 'array'],
        ])->validate();

        $data = $event['data'] ?? [];

        if (isset($data['household'])) {
            if (! AccountManagerHouseholds::enabled()) {
                return response()->noContent();
            }

            $snapshot = AccountManagerClient::validSnapshot($data['household']);

            if ($this->accept($event)) {
                ApplyAccountManagerEvent::dispatch($event['type'], $snapshot);
            }

            return response()->noContent();
        }

        if ($event['type'] === AccountManagerEntitlements::CHANGED && isset($data['entitlements'])) {
            if (! Entitlements::syncEnabled()) {
                return response()->noContent();
            }

            $snapshot = AccountManagerClient::validEntitlements($data['entitlements']);

            // Applied right away (only the database); a failure rolls back the event, so the AccountManager retries.
            DB::transaction(function () use ($event, $snapshot, $entitlements): void {
                if ($this->accept($event)) {
                    $entitlements->handleChanged($snapshot);
                }
            });

            return response()->noContent();
        }

        if ($event['type'] === AccountManagerEntitlements::USER_DELETED) {
            /** @var array{user: array{sub: string}} $deleted */
            $deleted = Validator::make($data, ['user.sub' => ['required', 'string', 'max:255']])->validate();

            if ($this->accept($event)) {
                DeleteAccountManagerUser::dispatch($deleted['user']['sub']);
            }

            return response()->noContent();
        }

        // Unknown types (later extensions) are acknowledged.
        return response()->noContent();
    }

    /**
     * Mirrored households, stored entitlements or both.
     */
    public static function enabled(): bool
    {
        return AccountManagerHouseholds::enabled() || Entitlements::syncEnabled();
    }

    /**
     * True the first time an event_id arrives.
     *
     * @param  array{event_id: string, type: string}  $event
     */
    private function accept(array $event): bool
    {
        return AccountManagerWebhookEvent::query()->insertOrIgnore([
            'event_id' => strtolower($event['event_id']),
            'type' => $event['type'],
            'created_at' => now(),
        ]) > 0;
    }

    public static function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        if ($secret === '' || ! ctype_digit($timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature);
    }
}
