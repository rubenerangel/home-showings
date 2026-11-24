<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use NotificationChannels\WebPush\Events\NotificationFailed;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
