<?php

namespace App\Push;

use App\Models\User;
use Illuminate\Contracts\Session\Session;

/** Remembers in the session which push subscription is of this browser, to remove it when the user changes. */
final class BrowserSubscription
{
    private const KEY = 'push_endpoint';

    /** @param array{endpoint: string, keys: array{p256dh: string, auth: string}} $subscription */
    public static function add(User $user, Session $session, array $subscription): void
    {
        $user->updatePushSubscription($subscription['endpoint'], $subscription['keys']['p256dh'], $subscription['keys']['auth'], 'aes128gcm');
        $session->put(self::KEY, $subscription['endpoint']);
    }

    public static function forget(?User $user, Session $session): void
    {
        $endpoint = $session->pull(self::KEY);
        if ($user !== null && $endpoint !== null) {
            $user->deletePushSubscription($endpoint);
        }
    }
}
