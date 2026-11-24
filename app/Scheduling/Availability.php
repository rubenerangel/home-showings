<?php

namespace App\Scheduling;

/** What a buyer is free for, with ranges that touch or overlap merged (R2). */
final readonly class Availability
{
    /** @var list<Interval> */
    public array $ranges;

    /** @param iterable<Interval> $ranges */
    public function __construct(iterable $ranges)
    {
        $ranges = [...$ranges];
        usort($ranges, fn (Interval $a, Interval $b) => $a->start <=> $b->start);

        $merged = [];
        foreach ($ranges as $range) {
            $last = array_key_last($merged);

            if ($last !== null && $range->start <= $merged[$last]->end) {
                $merged[$last] = new Interval($merged[$last]->start, $merged[$last]->end->max($range->end));
            } else {
                $merged[] = $range;
            }
        }

        $this->ranges = $merged;
    }

    /** Whether the slot is fully inside one merged range (R3). */
    public function contains(Interval $slot): bool
    {
        return array_any($this->ranges, fn (Interval $range) => $range->contains($slot));
    }
}
