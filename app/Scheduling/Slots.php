<?php

namespace App\Scheduling;

/** The fixed slots of a window, anchored at its start (R1). */
final readonly class Slots
{
    public function __construct(private Interval $window, private int $minutes) {}

    /** @return list<Interval> */
    public function all(): array
    {
        $slots = [];
        $start = $this->window->start;

        while (($end = $start->addMinutes($this->minutes)) <= $this->window->end) {
            $slots[] = new Interval($start, $end);
            $start = $end;
        }

        return $slots;
    }

    public function unusedMinutes(): int
    {
        return (int) $this->window->start->diffInMinutes($this->window->end) % $this->minutes;
    }
}
