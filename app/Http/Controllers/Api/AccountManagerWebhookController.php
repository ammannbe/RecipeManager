<?php

namespace App\Http\Controllers\Api;

use App\Households\AccountManagerClient;
use App\Households\AccountManagerHouseholds;
use App\Http\Controllers\Controller;
use App\Jobs\ApplyAccountManagerEvent;
use App\Models\AccountManagerWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use JsonException;

/**
 * Household events of the AccountManager (HOUSEHOLDS_DRIVER=account-manager, contract: AccountManager
 * docs/HOUSEHOLDS-API.md, section 4): signed with HMAC-SHA256 over "{timestamp}.{body}", at most
 * 300 seconds old, accepted once per event_id and applied by a queued job.
 */
class AccountManagerWebhookController extends Controller
{
    public const TOLERANCE = 300;

    public function __invoke(Request $request): Response
    {
        // Not mirrored (local driver or not configured): the AccountManager should not send here.
        abort_unless(AccountManagerHouseholds::enabled(), 404);

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

        // Unknown types without a household (later extensions) are acknowledged.
        if (! isset($event['data']['household'])) {
            return response()->noContent();
        }

        $snapshot = AccountManagerClient::validSnapshot($event['data']['household']);

        $new = AccountManagerWebhookEvent::query()->insertOrIgnore([
            'event_id' => strtolower($event['event_id']),
            'type' => $event['type'],
            'created_at' => now(),
        ]);

        if ($new > 0) {
            ApplyAccountManagerEvent::dispatch($event['type'], $snapshot);
        }

        return response()->noContent();
    }

    public static function verify(string $secret, string $timestamp, string $body, string $signature): bool
    {
        if ($secret === '' || ! ctype_digit($timestamp) || abs(now()->getTimestamp() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret), $signature);
    }
}
