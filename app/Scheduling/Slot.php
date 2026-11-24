<?php

namespace App\Scheduling;

use App\Models\Showing;
use App\Models\ShowingWindow;
use Carbon\CarbonImmutable;

/** A slot of a window as instants. */
final readonly class Slot
{
    public CarbonImmutable $startsAt;

    public CarbonImmutable $endsAt;

    public function __construct(public ShowingWindow $window, public Interval $local)
    {
        $this->startsAt = HouseClock::toInstant($local->start, $window->listing->timezone);
        $this->endsAt = HouseClock::toInstant($local->end, $window->listing->timezone);
    }

    public function overlaps(Showing $showing): bool
    {
        return $this->startsAt < $showing->ends_at && $showing->starts_at < $this->endsAt;
    }

    /** Not started, and with the notice the listing asks for (R4). */
    public function hasNoticeAt(CarbonImmutable $now): bool
    {
        return $this->startsAt > $now
            && $this->startsAt >= $now->addHours($this->window->listing->min_notice_hours);
    }
}
