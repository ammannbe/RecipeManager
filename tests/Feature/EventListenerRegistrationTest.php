<?php

namespace Tests\Feature;

use App\Listeners\AcceptHouseholdInvitationFromSession;
use App\Listeners\AcceptPendingCookbookInvitations;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventListenerRegistrationTest extends TestCase
{
    public function test_auth_listeners_are_registered_exactly_once(): void
    {
        $this->assertSame(1, $this->countListeners(Registered::class, AcceptPendingCookbookInvitations::class));
        $this->assertSame(1, $this->countListeners(Verified::class, AcceptPendingCookbookInvitations::class));
        $this->assertSame(1, $this->countListeners(Verified::class, AcceptHouseholdInvitationFromSession::class));
    }

    private function countListeners(string $event, string $listener): int
    {
        $listeners = Event::getRawListeners()[$event] ?? [];

        if (! is_array($listeners)) {
            return 0;
        }

        return count(array_filter($listeners, function ($registered) use ($listener): bool {
            $class = is_array($registered) ? $registered[0] : (is_string($registered) ? explode('@', $registered)[0] : null);

            return $class === $listener;
        }));
    }
}
