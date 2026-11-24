<?php

namespace App\Scheduling;

use App\Enums\ShowingEvent;
use App\Models\Showing;
use App\Notifications\ShowingChanged;
use App\Notifications\ShowingChangedPush;

/** Notifies the other side, inside the transaction of the action that calls it. */
class TellOtherSide
{
    public function that(ShowingEvent $event, Showing $showing): void
    {
        $recipient = $event->recipient($showing);

        $recipient->notify(new ShowingChanged($showing, $event));
        if ($recipient->pushSubscriptions()->exists()) {
            $recipient->notify(new ShowingChangedPush($showing, $event));
        }
    }
}
