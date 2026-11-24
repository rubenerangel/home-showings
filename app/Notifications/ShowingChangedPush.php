<?php

namespace App\Notifications;

use App\Enums\ShowingEvent;
use App\Models\Showing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The push version of ShowingChanged. The queue is a table of the same database, so the job is
 * written in the transaction of the action, and the worker only sees it once that is committed.
 */
class ShowingChangedPush extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly Showing $showing, private readonly ShowingEvent $event) {}

    /** @return list<string> */
    public function via(): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(User $user): WebPushMessage
    {
        $listing = $this->showing->listing;
        $startsAt = $this->showing->starts_at->setTimezone($listing->timezone->value)->format('D, M j, g:i A T');

        return (new WebPushMessage)
            ->title(match ($this->event) {
                ShowingEvent::Booked => 'New showing booked',
                ShowingEvent::CancelledByBuyer => 'Showing cancelled by the buyer',
                ShowingEvent::CancelledBySeller => 'Showing cancelled by the seller',
            })
            ->body("{$this->event->author($this->showing)->name}: {$listing->fullAddress()}. $startsAt")
            ->data(['url' => "/{$user->role->value}/notifications"]);
    }
}
