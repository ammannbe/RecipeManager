<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyPushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Web Push subscriptions of the user's browsers (one per device), turned on in the profile. Having one is the
 * consent to announcements by push. Same as Chorelist.
 */
class PushSubscriptionController extends Controller
{
    /** Hosts of the browsers' push services; the server sends requests to the endpoint (no SSRF). */
    public const PUSH_HOSTS = [
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        '*.push.apple.com',
        '*.notify.windows.com',
    ];

    public function store(StorePushSubscriptionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array{endpoint: string, keys: array{p256dh: string, auth: string}, content_encoding?: string|null} $validated */
        $validated = $request->validated();

        $user->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aes128gcm',
        );

        return response()->noContent();
    }

    public function destroy(DestroyPushSubscriptionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->deletePushSubscription((string) $request->validated('endpoint'));

        return response()->noContent();
    }

    public static function isPushService(string $endpoint): bool
    {
        $parts = parse_url($endpoint);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || isset($parts['port']) || isset($parts['user'])) {
            return false;
        }

        return Str::is(self::PUSH_HOSTS, Str::lower($parts['host'] ?? ''));
    }
}
