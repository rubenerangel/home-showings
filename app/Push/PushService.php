<?php

namespace App\Push;

/** The push services of the browsers. The server sends requests to them, so no other address is accepted. */
final class PushService
{
    private const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com'];

    private const DOMAINS = ['.notify.windows.com', '.push.apple.com'];

    public static function accepts(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || array_intersect_key($parts, array_flip(['port', 'user', 'pass']))) {
            return false;
        }

        $host = strtolower($parts['host'] ?? '');

        return in_array($host, self::HOSTS, true)
            || array_any(self::DOMAINS, fn (string $domain) => str_ends_with($host, $domain));
    }
}
