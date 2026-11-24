<?php

namespace App\Notifications;

use App\Enums\ShowingEvent;
use App\Models\Showing;
use Illuminate\Notifications\Notification;

/**
 * Tells the other side what happened to a showing. Not queued, so it is written in the
 * transaction of the action (D17). It keeps the details of that moment.
 */
class ShowingChanged extends Notification
{
    public function __construct(private readonly Showing $showing, private readonly ShowingEvent $event) {}

    /** @return list<string> */
    public function via(): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $listing = $this->showing->listing;
        $author = $this->event === ShowingEvent::CancelledBySeller ? $listing->seller : $this->showing->buyer;

        return [
            'kind' => $this->event->value,
            'showing_id' => $this->showing->id,
            'name' => $author->name,
            'address' => "{$listing->address}, {$listing->city}, {$listing->state}",
            'timezone' => $listing->timezone->value,
            'starts_at' => $this->showing->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->showing->ends_at->toIso8601ZuluString(),
        ];
    }
}
