<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;

/** Half-open, [start, end). The wall clock of a house: the zone of these objects has no meaning. */
final readonly class Interval
{
    public function __construct(public CarbonImmutable $start, public CarbonImmutable $end) {}

    public static function parse(string $start, string $end): self
    {
        return new self(CarbonImmutable::parse($start, 'UTC'), CarbonImmutable::parse($end, 'UTC'));
    }

    public function contains(self $other): bool
    {
        return $this->start <= $other->start && $other->end <= $this->end;
    }
}
