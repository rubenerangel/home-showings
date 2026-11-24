<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{
    /** R18, each limit on its own. */
    private const REQUESTS_A_MINUTE = 60;

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(self::REQUESTS_A_MINUTE)->by($request->user()->id));
        // Apart from the one of the accounts, so looking at homes never blocks a login.
        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(self::REQUESTS_A_MINUTE)->by($request->ip()));
        RateLimiter::for('api-guest', fn (Request $request) => Limit::perMinute(self::REQUESTS_A_MINUTE)->by($request->ip()));

        // The id of a token is looked up in the database, so it has to fit in the column.
        Sanctum::getAccessTokenFromRequestUsing(
            fn (Request $request) => preg_match('/^[0-9]{1,18}\|./', (string) $request->bearerToken()) === 1 ? $request->bearerToken() : null,
        );
        // Sanctum accepts a token at the very instant it expires.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $valid) => $valid && $token->expires_at?->isFuture() === true,
        );

        // The channel does not throw, so without this a rejected push leaves no trace.
        // The endpoint is left out: whoever has it can identify the browser.
        Event::listen(fn (NotificationFailed $event) => Log::warning('A push notification was rejected.', [
            'subscription' => $event->subscription->id,
            'user' => $event->subscription->subscribable_id,
            'status' => $event->report->getResponse()?->getStatusCode(),
            'reason' => str_replace($event->subscription->endpoint, '[endpoint]', $event->report->getReason()),
        ]));
    }
}
